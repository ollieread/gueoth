<?php

namespace Gueoth;

use Ds\Map;
use IteratorAggregate;

class Config implements IteratorAggregate
{
    protected Map $config;

    public function __construct(array $config = [])
    {
        $this->config = new Map();
        $this->setConfig($config);
    }

    public function get(string $key, $default = null)
    {
        return $this->config->get($key, $default);
    }

    public function set(string $key, $value): Config
    {
        $this->config->put($key, $value);

        return $this;
    }

    public function getIterator()
    {
        return $this->config->getIterator();
    }

    private function setConfig(array $config): void
    {
        if (! empty($config)) {
            foreach ($config as $key => $value) {
                $this->set($key, $value);
            }
        }
    }
}