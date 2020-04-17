<?php

namespace Gueoth\Support;

use Gueoth\Objects\Blob;
use Gueoth\Objects\Commit;
use Gueoth\Objects\Tag;
use Gueoth\Objects\Tree;
use Gueoth\Repository;
use RuntimeException;

class ObjectHelper
{
    public static function path(string $sha): array
    {
        return [substr($sha, 0, 2), substr($sha, 2)];
    }

    public static function create(Repository $repository, string $type, string $data)
    {
        $method = 'create' . ucwords($type) . 'Object';

        if (method_exists(self::class, $method)) {
            return self::$method($repository, $data);
        }

        throw new RuntimeException(sprintf('Unknown object type %s', $type));
    }

    public static function createCommitObject(Repository $repository, string $contents): Commit
    {
        return new Commit($repository, $contents);
    }

    public static function createTreeObject(Repository $repository, string $contents): Tree
    {
        return new Tree($repository, $contents);
    }

    public static function createTagObject(Repository $repository, string $contents): Tag
    {
        return new Tag($repository, $contents);
    }

    public static function createBlobObject(Repository $repository, string $contents): Blob
    {
        return new Blob($repository, $contents);
    }
}