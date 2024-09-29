<?php

namespace EvoMark\EvoPostalWpTransport;

class Attachment
{
    public string $filename;
    public string $contentType;
    public string $data;

    public function __construct(string $filename, string $contentType, string $data)
    {
        $this->filename = trim($filename);
        $this->contentType = trim($contentType);
        $this->data = $data;
    }
}
