<?php

namespace Radiergummi\FlysystemGitHub;

use function base64_decode;
use function fopen;

class File extends Entry
{
    /**
     * @var int
     */
    private $size;

    /**
     * @var string
     */
    private $contents;

    public function __construct(array $data)
    {
        $this->setName($data['name']);
        $this->setPath($data['path']);
        $this->setHash($data['sha']);
        $this->size = $data['size'];

        // Empty files don't have a content property
        $this->contents = $data['content'] ?? '';
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getContents(): string
    {
        return $this->contents;
    }

    public function getDecodedContents(): string
    {
        return base64_decode($this->getContents());
    }

    /**
     * @return false|resource
     */
    public function getStream()
    {
        return fopen("data://text/plain;base64,{$this->getContents()}", 'rb');
    }

    public function getType(): string
    {
        return 'file';
    }
}
