<?php

namespace Gueoth\Objects;

use Gueoth\Repository;

abstract class BaseObject
{
    protected Repository $repository;

    private bool         $dirty = false;

    public function __construct(Repository $repo, $data = null)
    {
        $this->repository = $repo;
        $this->unserialise($data);
    }

    public function getRepository(): Repository
    {
        return $this->repository;
    }

    abstract public function getType(): string;

    abstract public function serialise(): string;

    abstract public function unserialise($data): void;

    protected function isDirty(): bool
    {
        return $this->dirty;
    }

    protected function setDirty(): self
    {
        $this->dirty = true;

        return $this;
    }

    protected function clean(): self
    {
        $this->dirty = false;

        return $this;
    }
}