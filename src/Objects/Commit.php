<?php

namespace Gueoth\Objects;

use Ds\Map;
use Ds\Set;
use InvalidArgumentException;

class Commit extends BaseObject
{
    private Map   $data;

    private ?Tree $tree    = null;

    private ?Set  $parents = null;

    public function getType(): string
    {
        return 'commit';
    }

    public function serialise(): string
    {
        $result = '';

        foreach ($this->data->keys() as $key) {
            if ($key === '') {
                continue;
            }

            $value = $this->data->get($key);

            if (! is_array($value)) {
                $value = [$value];
            }

            foreach ($value as $v) {
                $result .= $key . ' ' . (str_replace("\n", "\n ", $v)) . PHP_EOL;
            }
        }

        $result .= PHP_EOL . $this->data->get('');

        return $result;
    }

    public function unserialise($data): void
    {
        $this->data = new Map;
        $this->parseCommit($data);
    }

    public function parseCommit(string $content, int $start = 0): void
    {
        $space   = strpos($content, ' ', $start);
        $newline = strpos($content, "\n", $start);

        if ($space === false || $newline < $space) {
            assert($newline === $start, new InvalidArgumentException('Incorrectly formatted KVLM'));
            $this->data->put('', substr($content, $start + 1));

            return;
        }

        $key = substr($content, $start, ($space - $start));
        $end = $start;

        while (true) {
            $end = strpos($content, "\n", $end + 1);

            if ($content[$end + 1] !== ord(' ')) {
                break;
            }
        }

        $value = substr($content, $space + 1, $end - ($space + 1));
        $value = str_replace("\n ", "\n", $value);

        if ($this->data->hasKey($key)) {
            $currentValue = $this->data->get($key);

            if (is_array($currentValue)) {
                $currentValue[] = $value;
                $this->data->put($key, $currentValue);
            } else {
                $this->data->put($key, [$currentValue, $value]);
            }
        } else {
            $this->data->put($key, $value);
        }

        $this->parseCommit($content, $end + 1);
    }

    public function getTree(): string
    {
        return $this->data->get('tree');
    }

    public function getTreeObject(): Tree
    {
        if ($this->tree === null) {
            $tree       = $this->getTree();
            $this->tree = $this->repository->getTree($tree);
        }

        return $this->tree;
    }

    public function getParents(): array
    {
        $parents = $this->data->get('parent');

        return is_array($parents) ? $parents : [$parents];
    }

    public function getParentObjects(): Set
    {
        if ($this->parents === null) {
            $parents = $this->getParents();

            if (! empty($parents)) {
                $objects = new Set;

                foreach ($parents as $parent) {
                    $object = $this->getRepository()->getCommit($parent);

                    if ($object !== null) {
                        $objects->add($object);
                    }
                }

                $this->parents = $objects;
            }
        }

        return $this->parents;
    }

    public function getMessage(): ?string
    {
        return $this->data->get('');
    }

    public function __toString()
    {
        return $this->serialise();
    }
}