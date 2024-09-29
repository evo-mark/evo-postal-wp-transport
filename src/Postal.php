<?php

namespace EvoMark\EvoPostalWpTransport;


use Postal\Client;
use Postal\Send\Message as SendMessage;
use Postal\Send\RawMessage;

class Postal
{
    protected array $settings;
    protected Attributes $attrs;
    protected Client $client;

    /**
     *
     */
    public function __construct($settings, $attrs)
    {
        $this->settings = $settings;
        $this->attrs = new Attributes($attrs, $settings['from_address'] ?? "", $settings['from_name'] ?? "");

        $this->client = new Client($this->start($settings['host'], "https://"), $settings['api_key']);
    }

    /**
     * The public send function for a constructed email
     */
    public function send()
    {
        if ($this->attrs->isMultipart()) {
            return $this->sendRaw();
        }
        $message = new SendMessage();

        foreach ($this->attrs->to as $recipient) {
            $message->to($recipient);
        }

        $message->from($this->attrs->from);
        $message->subject($this->attrs->subject);

        if ($this->attrs->isHtml()) {
            $message->htmlBody($this->attrs->message);
            $message->plainBody(strip_tags($this->attrs->message));
        } else {
            $message->plainBody($this->attrs->message);
        }

        foreach ($this->attrs->cc as $recipient) {
            $message->cc($recipient);
        }

        foreach ($this->attrs->bcc as $recipient) {
            $message->bcc($recipient);
        }

        if (!empty($this->attrs->replyTo)) {
            $message->replyTo($this->attrs->replyTo);
        }

        foreach ($this->attrs->headers as $headerName => $headerContent) {
            $message->header($headerName, $headerContent);
        }

        /** @var Attachment $attachment */
        foreach ($this->attrs->attachments as $attachment) {
            $message->attach($attachment->filename, $attachment->contentType, $attachment->data);
        }

        $result = $this->client->send->message($message);
        return $result;
    }

    public function sendRaw()
    {
        $date = date('D, d M Y H:i:s O');
        $id = sprintf("<%s@%s>", uniqid('', true), $this->attrs->getDomain());
        $from = $this->attrs->from;
        $to = $this->attrs->to;
        $subject = $this->attrs->subject;
        $content = $this->attrs->message;
        $cc = implode(", ", $this->attrs->cc);
        $bcc = implode(", ", $this->attrs->bcc);

        $headers = "";
        if (!empty($cc)) {
            $headers .= "Cc: " . $cc . "\n";
        }
        if (!empty($bcc)) {
            $headers .= "Bcc: " . $bcc . "\n";
        }
        if (!empty($this->attrs->replyTo)) {
            $headers .= "Reply-To: " . $this->attrs->replyTo . "\n";
        }
        foreach ($this->attrs->headers as $key => $header) {
            $headers .= $key . ": " . $header . "\n";
        }

        $type = "Content-Type: " . $this->attrs->contentType;
        if (!empty($this->attrs->charset)) {
            $type .= "; charset=" . $this->attrs->charset;
        }
        if (!empty($this->attrs->boundary)) {
            $type .= '; boundary="' . $this->attrs->boundary . '"';
        }
        $headers .= $type . "\r\n";

        foreach ($to as $recipient) {
            $data = <<<EOD
            Date: $date
            From: $from
            To: $recipient
            Subject: $subject
            Message-ID: $id
            MIME-Version: 1.0
            $headers
            $content
            EOD;

            $message = new RawMessage();

            $message->mailFrom($this->attrs->from);
            $message->rcptTo($recipient);
            $message->data($data);

            $this->client->send->raw($message);
        }

        return true;
    }

    public function start($value, $prefix)
    {
        $quoted = preg_quote($prefix, '/');
        return $prefix . preg_replace('/^(?:' . $quoted . ')+/u', '', $value);
    }
}
