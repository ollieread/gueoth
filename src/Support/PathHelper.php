<?php

namespace Gueoth\Support;

use Gueoth\Repository;
use InvalidArgumentException;
use RuntimeException;

class PathHelper
{
    public const DIR_MODE  = 0755;

    public const FILE_MODE = 0755;

    public static function exists(string $path = ''): bool
    {
        return file_exists($path);
    }

    public static function isDir(string $path = ''): bool
    {
        return is_dir($path);
    }

    public static function repoExists(Repository $repository, string $path = ''): bool
    {
        return file_exists(self::repoPath($repository, $path));
    }

    public static function repoHasDir(Repository $repository, string $path = ''): bool
    {
        return self::isDir(self::repoPath($repository, $path));
    }

    public static function isEmpty(Repository $repository, string $path = ''): bool
    {
        $fullPath = self::repoPath($repository, $path);

        if (! self::repoHasDir($repository, $path)) {
            throw new InvalidArgumentException(sprintf('Target is not a directory %s', $fullPath));
        }

        $listing = scandir($fullPath);

        if ($listing === false) {
            throw new RuntimeException(sprintf('Unexpected error checking if %s is empty', $fullPath));
        }

        return empty($listing);
    }

    public static function dir(string $path = '', bool $make = false, int $mode = self::DIR_MODE): ?string
    {
        if (file_exists($path)) {
            return $path;
        }

        if (! $make) {
            return null;
        }

        if (substr($path, -1) !== DIRECTORY_SEPARATOR) {
            $path .= DIRECTORY_SEPARATOR;
        }

        if (mkdir($path, $mode, true) && is_dir($path)) {
            return $path;
        }

        return null;
    }

    public static function file(string $path = '', bool $make = false, int $mode = self::FILE_MODE, int $dirMode = self::DIR_MODE): ?string
    {
        $dirPath = self::dir(dirname($path), $make, $dirMode);

        if ($dirPath !== null && touch($path) && chmod($path, $mode)) {
            return $path;
        }

        return null;
    }

    public static function repoDir(Repository $repository, string $path = '', bool $make = false, int $mode = self::DIR_MODE): ?string
    {
        return self::dir(self::repoPath($repository, $path), $make, $mode);
    }

    public static function repoFile(Repository $repository, string $path = '', bool $make = false, int $mode = self::FILE_MODE, int $dirMode = self::DIR_MODE): ?string
    {
        return self::file(self::repoPath($repository, $path), $make, $mode, $dirMode);
    }

    public static function gitDir(Repository $repository, string $path = '', bool $make = false, int $mode = self::DIR_MODE): ?string
    {
        return self::repoDir($repository, $repository->getGitDir() . DIRECTORY_SEPARATOR . $path, $make, $mode);
    }

    public static function gitFile(Repository $repository, string $path = '', bool $make = false, int $mode = self::FILE_MODE, int $dirMode = self::DIR_MODE): ?string
    {
        return self::repoFile($repository, $repository->getGitDir() . DIRECTORY_SEPARATOR . $path, $make, $mode, $dirMode);
    }

    public static function repoPath(Repository $repository, string ...$paths): string
    {
        return self::path($repository->getWorkTree(), $paths);
    }

    public static function path(string ...$paths): string
    {
        return implode(DIRECTORY_SEPARATOR, $paths);
    }

    public static function findRepo(string $path, bool $required = true): ?Repository
    {
        $path = realpath($path);

        if (self::isDir(self::path($path, '.git'))) {
            return new Repository($path, null, ConfigHelper::parseConfig(self::path($path, '.git', 'config')));
        }

        $parent = dirname($path);

        if ($parent === $path) {
            if ($required) {
                throw new RuntimeException('No parent git directory found');
            }

            return null;
        }

        return self::findRepo($parent, $required);
    }
}