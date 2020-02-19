<?php

namespace Radiergummi\FlysystemGitHub;

class Directory extends Entry
{
    public function __construct(array $data)
    {
        $this->setName($data['name']);
        $this->setPath($data['path']);
        $this->setHash($data['sha']);
    }

    public function getType(): string
    {
        return 'directory';
    }
}
