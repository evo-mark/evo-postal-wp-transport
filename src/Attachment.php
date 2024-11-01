<?php

namespace EvoMark\EvoPostalWpTransport;

class Attachment
{
    public string $filename;
    public string $contentType;
    public string $data;
    public string $originalPath;

    public function __construct(string $filename, string $contentType, string $data, ?string $originalPath = null)
    {
        $this->filename = trim($filename);
        $this->contentType = trim($contentType);
        $this->data = $data;
        $this->originalPath = trim($originalPath);
    }
}
