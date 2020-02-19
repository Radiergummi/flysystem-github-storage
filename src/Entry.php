<?php

namespace Radiergummi\FlysystemGitHub;

use ArrayAccess;
use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

use function get_object_vars;
use function ucfirst;

abstract class Entry implements ArrayAccess, IteratorAggregate
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $hash;

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Retrieves the type of a filesystem entry
     *
     * @return string
     */
    abstract public function getType(): string;

    public function offsetExists($offset): bool
    {
        return isset($this->$offset);
    }

    public function offsetGet($offset)
    {
        $method = 'get' . ucfirst($offset);

        return $this->$method();
    }

    public function offsetSet($offset, $value): void
    {
        // noop
    }

    public function offsetUnset($offset): void
    {
        // noop
    }

    /**
     * Intercepts any calls to invalid methods, probably triggered by invalid array accesses.
     *
     * @param string $method
     * @param array  $args
     *
     * @throws \InvalidArgumentException
     */
    public function __call(string $method, array $args): void
    {
        throw new InvalidArgumentException("No such property: {$method}");
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator(get_object_vars($this));
    }

    protected function setName(string $name): void
    {
        $this->name = $name;
    }

    protected function setPath(string $path): void
    {
        $this->path = $path;
    }

    protected function setHash(string $hash): void
    {
        $this->hash = $hash;
    }
}
