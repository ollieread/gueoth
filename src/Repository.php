<?php

namespace Gueoth;

use AssertionError;
use Gueoth\Support\ConfigHelper;
use Gueoth\Support\PathHelper;
use RuntimeException;

class Repository
{
    protected string  $workTree;

    protected string  $gitDir;

    protected ?Config $config;

    protected bool    $exists = false;

    public function __construct(string $workTree, ?string $gitDir = null, ?Config $config = null)
    {
        $this->workTree = realpath($workTree);
        $this->gitDir   = $gitDir ?? '.git';
        $this->config   = $config ?? ConfigHelper::repoConfig();
        $this->exists   = PathHelper::repoExists($this, $this->getGitDir());
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
}