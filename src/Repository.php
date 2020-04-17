<?php

namespace Gueoth;

use AssertionError;
use Ds\Map;
use Gueoth\Objects\BaseObject;
use Gueoth\Objects\Commit;
use Gueoth\Objects\Tree;
use Gueoth\Support\ConfigHelper;
use Gueoth\Support\ObjectHelper;
use Gueoth\Support\PathHelper;
use InvalidArgumentException;
use RuntimeException;

class Repository
{
    protected string  $workTree;

    protected string  $gitDir;

    protected ?Config $config;

    protected bool    $exists = false;

    protected Map     $objects;

    protected Map     $trees;

    protected Map     $commits;

    protected Map     $tags;

    protected Map     $blobs;

    public function __construct(string $workTree, ?string $gitDir = null, ?Config $config = null)
    {
        $this->workTree = realpath($workTree);
        $this->gitDir   = $gitDir ?? '.git';
        $this->config   = $config ?? ConfigHelper::repoConfig();
        $this->exists   = PathHelper::repoExists($this, $this->getGitDir());
        $this->objects  = new Map;
        $this->trees    = new Map;
        $this->commits  = new Map;
        $this->tags     = new Map;
        $this->blobs    = new Map;
    }

    public function getWorkTree()
    {
        return $this->workTree;
    }

    public function getGitDir(): string
    {
        return $this->gitDir;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function init(): void
    {
        if ($this->checkPresence()) {
            throw new RuntimeException('Repository already initialised');
        }

        if (! PathHelper::repoHasDir($this)) {
            if (PathHelper::gitDir($this, '', true) === null) {
                throw new RuntimeException(sprintf('Unable to create work tree directory %s', $this->getWorkTree()));
            }
        } else if (! PathHelper::isEmpty($this)) {
            throw new RuntimeException(sprintf('Target directory is not empty %s', $this->getWorkTree()));
        }

        assert(PathHelper::gitDir($this, 'branches', true), new AssertionError('Unable to create git branches directory'));
        assert(PathHelper::gitDir($this, 'objects', true), new AssertionError('Unable to create git objects directory'));
        assert(PathHelper::gitDir($this, 'refs/tags', true), new AssertionError('Unable to create git refs/tags directory'));
        assert(PathHelper::gitDir($this, 'refs/heads', true), new AssertionError('Unable to create git refs/heads directory'));

        $descriptionFile = PathHelper::gitFile($this, 'description');
        $headFile        = PathHelper::gitFile($this, 'HEAD');
        $configFile      = PathHelper::gitFile($this, 'config');

        assert($descriptionFile !== null, new AssertionError('Unable to create description file'));
        assert($headFile !== null, new AssertionError('Unable to create HEAD file'));
        assert($configFile !== null, new AssertionError('Unable to create config file'));

        assert(file_put_contents($descriptionFile, 'Unnamed repository; edit this file \'description\' to name the repository.' . PHP_EOL), new AssertionError('Unable to write default description file'));
        assert(file_put_contents($headFile, 'ref: refs/heads/master' . PHP_EOL), new AssertionError('Unable to write default HEAD file'));
        assert(ConfigHelper::writeConfig($configFile, $this->config), new AssertionError('Unable to write default config file'));
    }

    public function getObject(string $sha): ?BaseObject
    {
        if (! $this->objects->hasKey($sha)) {
            $object = $this->readObject($sha);

            if ($object !== null) {
                $this->placeObject($sha, $object);
            }
        }

        return $this->objects->get($sha);
    }

    public function getCommit(string $sha): ?Commit
    {
        if (! $this->commits->hasKey($sha)) {
            $commit = $this->getObject($sha);

            if (! ($commit instanceof Commit)) {
                throw new InvalidArgumentException(sprintf('Object %s is not a commit', $sha));
            }
        }

        return $this->commits->get($sha);
    }

    public function getTree(string $sha): ?Tree
    {
        if (! $this->trees->hasKey($sha)) {
            $tree = $this->getObject($sha);

            if (! ($tree instanceof Tree)) {
                throw new InvalidArgumentException(sprintf('Object %s is not a tree', $sha));
            }
        }

        return $this->trees->get($sha);
    }

    protected function readObject(string $sha): ?BaseObject
    {
        $path = PathHelper::repoPath($this, ...ObjectHelper::path($sha));

        if (! file_exists($path)) {
            return null;
        }

        $rawContents = gzdecode(file_get_contents($path));

        assert($spaceSeparator = strpos($rawContents, "\x20"), new InvalidArgumentException(sprintf('Invalid object file %s', $path)));
        assert($nullSeparator = strpos($rawContents, "\x00", $spaceSeparator), new InvalidArgumentException(sprintf('Invalid object file %s', $path)));

        $type = substr($rawContents, 0, $spaceSeparator);
        $size = (int) hexdec(substr($rawContents, $spaceSeparator, $nullSeparator));

        assert($size === (strlen($rawContents) - $nullSeparator - 1), new RuntimeException(sprintf('Malformed object %s: bad length', $sha)));

        return ObjectHelper::create($this, $type, substr($rawContents, $nullSeparator + 1));
    }

    protected function writeObject(BaseObject $object, bool $simulate = false): string
    {
        $data   = $object->serialise();
        $result = $object->getType() . "\x20" . strlen($data) . "\x00" . $data;
        $sha    = bin2hex(sha1($result));

        if (! $simulate) {
            $path = PathHelper::gitFile($this, PathHelper::path('objects', ...ObjectHelper::path($sha)));

            if (! file_put_contents($path, gzencode($result))) {
                throw new RuntimeException(sprintf('Unable to write object %s', $sha));
            }
        }

        return $sha;
    }

    protected function checkPresence(): bool
    {
        return $this->exists = PathHelper::repoExists($this, $this->getGitDir());
    }

    protected function validate(bool $checkPresence = true): void
    {
        if ($checkPresence) {
            if (! $this->checkPresence()) {
                throw new RuntimeException(sprintf('Not a Git repository %s', $this->getWorkTree()));
            }

            if (! PathHelper::repoExists($this, $this->getGitDir() . DIRECTORY_SEPARATOR . 'config')) {
                throw new RuntimeException('Configuration file missing');
            }
        }

        $formatVersion = $this->getConfig()->get('core.repositoryformatversion');

        if ($formatVersion !== 0) {
            throw new RuntimeException(sprintf('Unsupported repositoryformatversion %s', $formatVersion));
        }
    }

    private function placeObject(string $sha, BaseObject $object): void
    {
        $this->objects->put($sha, $object);

        switch ($object->getType()) {
            case 'commit':
                $this->commits->put($sha, $object);
                break;
            case 'tree':
                $this->trees->put($sha, $object);
                break;
            case 'tag':
                $this->tags->put($sha, $object);
                break;
            case 'blobs':
                $this->blobs->put($sha, $object);
                break;
        }
    }
}