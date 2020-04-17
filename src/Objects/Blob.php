<?php

namespace Gueoth\Objects;

class Blob extends BaseObject
{
    private string $data;

    public function getType(): string
    {
        return 'blob';
    }

    public function serialise(): string
    {
        return $this->data;
    }

    public function unserialise($data): void
    {
        $this->data = $data;
    }
}