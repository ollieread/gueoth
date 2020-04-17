<?php

namespace Gueoth\Objects;

use Gueoth\Repository;
use RuntimeException;

abstract class BaseObject
{
    /**
     * @var \Gueoth\Repository
     */
    protected Repository $repo;

    public function __construct(Repository $repo, $data = null)
    {
        $this->repo = $repo;
        $this->unserialize($data);
    }

    public function serialise(): void
    {
        throw new RuntimeException('Unimplemented');
    }

    public function unserialise($data): void
    {
        throw new RuntimeException('Unimplemented');
    }
}