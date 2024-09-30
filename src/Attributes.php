<?php

namespace EvoMark\EvoPostalWpTransport;

class Attributes
{

    public array $to = [];
    public string $from = "";
    public string $fromName = "";
    public string $fromAddress = "";
    public array $cc = [];
    public array $bcc = [];
    public string $replyTo = "";

    public string $subject = "";
    public string $message = "";

    public array $headers = [];

    public array $attachments = [];
    public string $boundary = "";
    public string $contentType = "text/html";
    public string $charset = "";

    /**
     * @param array $attributes {
     *     Array of the `wp_mail()` arguments.
     *
     *     @type string|string[] $to          Array or comma-separated list of email addresses to send message.
     *     @type string          $subject     Email subject.
     *     @type string          $message     Message contents.
     *     @type string|string[] $headers     Additional headers.
     *     @type string|string[] $attachments Paths to files to attach.
     * }
     * @param string $defaultFromAddress The fallback sender's email address
     * @param string $defaultFromName The fallback sender's name
     */
    public function __construct(array $attributes, string $defaultFromAddress, string $defaultFromName)
    {
        $this->to = $this->processTo($attributes);
        $this->subject = !empty($attributes['subject']) ? trim($attributes['subject']) : "";
        $this->message = !empty($attributes['message']) ? trim($attributes['message']) : "";
        $this->attachments = $this->processAttachments($attributes);

        $this->headers = $this->normaliseHeaders($attributes);
        foreach ($this->headers as $name => $content) {
            $shouldRemove = $this->checkForSpecialHeaders($name, $content);
            if (true === $shouldRemove) {
                unset($this->headers[$name]);
            }
        }


        $this->fromName = $this->resolveFromName($defaultFromName);
        $this->fromAddress = $this->resolveFromAddress($defaultFromAddress);

        $this->from = !empty($this->fromName) ? $this->fromName . " <" . $this->fromAddress . ">" : $this->fromAddress;
        $this->contentType = apply_filters('wp_mail_content_type', $this->contentType);
        $this->charset = apply_filters('wp_mail_charset', $this->charset);
    }

    private function resolveFromName($name)
    {
        if (!empty($this->from) && stripos($this->from, "<") !== false) {
            $maybeNameParts = explode("<", $this->from, 2);
            $maybeName = trim($maybeNameParts[0] ?? "");
            if (!empty($maybeName)) {
                $name = $maybeName;
            }
        }

        return apply_filters('wp_mail_from_name', $name);
    }

    private function resolveFromAddress($email)
    {
        if (!empty($this->from) && stripos($this->from, "<") !== false) {
            $maybeEmailParts = explode("<", $this->from, 2);
            $maybeEmail = trim($maybeEmailParts[1] ?? "", " \n\r\t\v\0>");
            if (!empty($maybeEmail)) {
                $email = $maybeEmail;
            }
        }

        return apply_filters('wp_mail_from', $email);
    }

    public function isMultipart(): bool
    {
        return false !== stripos($this->contentType, 'multipart') ? true : false;
    }

    public function isPlainText(): bool
    {
        return false !== stripos($this->contentType, 'plain') ? true : false;
    }

    public function isHtml(): bool
    {
        return false !== stripos($this->contentType, 'html') ? true : false;
    }

    public function getDomain(): string
    {
        if (preg_match('/<(.+?)>|([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $this->from, $matches)) {
            // Extract the email part, either from angle brackets or as a plain address
            $email = isset($matches[1]) ? $matches[1] : $matches[2];

            // Extract the domain from the email address
            $domain = substr(strrchr($email, "@"), 1);

            return $domain;
        }

        $parsed = parse_url(get_home_url(), PHP_URL_HOST);
        return preg_replace('/^www\./', "", $parsed);
    }

    /**
     * @param string|string[] $to The recipients of the email
     * @return string[] An array of email addresses to send to
     */
    private function processTo($attributes)
    {
        if (empty($attributes['to'])) {
            return [];
        } else if (is_string($attributes['to'])) {
            return array_map('trim', explode(",", $attributes['to']));
        } else {
            return array_map('trim', $attributes['to']);
        }
    }

    /**
     * @param array $attributes The email attributes array
     */
    private function processAttachments($attributes)
    {
        if (empty($attributes['attachments'])) {
            return [];
        }

        $attachments = !is_array($attributes['attachments']) ?
            explode("\n", str_replace("\r\n", "\n", $attributes['attachments'])) :
            $attributes['attachments'];

        $processed = [];

        foreach ($attachments as $filename => $attachment) {
            if (empty($attachment) || !file_exists($attachment)) continue;

            $filename = is_string($filename) ? $filename : basename($attachment);
            $contentType = mime_content_type($attachment) ?? "text/plain";
            $data = file_get_contents($attachment);
            $processed[] = new Attachment($filename, $contentType, $data);
        }

        return $processed;
    }

    private function normaliseHeaders($attributes)
    {
        if (empty($attributes['headers'])) {
            return [];
        }

        $headers = is_array($attributes['headers']) ?
            $attributes['headers'] :
            explode("\n", str_replace("\r\n", "\n", $attributes['headers']));

        return $this->processHeaders($headers);
    }

    private function normaliseHeaderKey(string $header): string
    {
        $words = explode('-', trim(strtolower($header)));
        $words = array_map('ucfirst', $words);
        return implode('-', $words);
    }

    /**
     * Convert string-style headers into an associative array
     */
    private function processHeaders(array $headers)
    {
        $processed = [];

        if ($this->isAssoc($headers)) {
            foreach ($headers as $key => $value) {
                $processed[$this->normaliseHeaderKey($key)] = trim($value);
            }
            return $processed;
        }

        foreach ($headers as $header) {
            // Header does NOT contain a : marker
            if (! str_contains($header, ':')) {
                $boundary = $this->checkForBoundary($header);
                if (!empty($boundary)) {
                    $this->boundary = $boundary;
                }
                continue;
            }

            list($name, $content) = explode(':', trim($header), 2);
            $name = $this->normaliseHeaderKey($name);
            $content = trim($content);

            $processed[$name] = $content;
        }

        return $processed;
    }

    /**
     * Iterate over any headers and filter out special ones
     *
     * @param string $name The header name/key
     * @param string $content The header content
     * @return bool Return true to remove the header
     */
    private function checkForSpecialHeaders($name, $content): bool
    {
        switch (strtolower($name)) {
            case "from":
                $this->from = $content;
                return true;
            case "cc":
                $addresses = is_array($content) ? $content : explode(",", $content);
                $this->cc = array_map('trim', $addresses);
                return true;
            case "bcc":
                $addresses = is_array($content) ? $content : explode(",", $content);
                $this->bcc = array_map('trim', $addresses);
                return true;
            case "reply-to":
                $this->replyTo = trim($content);
                return true;
            case "content-type":
                if (empty($content) && empty($this->contentType)) {
                    $this->contentType = "text/html";
                } else if (str_contains($content, ';')) {
                    list($type, $charset_content) = explode(';', $content);
                    $this->contentType = trim($type);
                    if (false !== stripos($charset_content, 'charset=')) {
                        $this->charset = trim(str_replace(array('charset=', '"'), '', $charset_content));
                    } elseif (false !== stripos($charset_content, 'boundary=')) {
                        $this->boundary = trim(str_replace(array('BOUNDARY=', 'boundary=', '"'), '', $charset_content));
                        $this->charset = '';
                    }
                } else {
                    $this->contentType = trim($content);
                }
                return true;
            default:
                return false;
        }
    }

    /**
     * Checks for a non-standard header that contains a boundary designation
     * @param string $header The header string
     * @return string|false The boundary or false
     */
    private function checkForBoundary(string $header)
    {
        // Header DOES contain a boundary= to split on
        if (false !== stripos($header, 'boundary=')) {
            $parts = preg_split('/boundary=/i', trim($header));
            $boundary = trim(str_replace(array("'", '"'), '', $parts[1]));
            return $boundary;
        }
    }

    private function isAssoc(array $arr): bool
    {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
