<?php

declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\Persistence;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * A query result wrapper for occurrence arrays that mimics QueryResultInterface
 */
class OccurrenceQueryResult implements QueryResultInterface, \Iterator, \Countable, \ArrayAccess
{
    private array $items;
    private int $position = 0;

    public function __construct(array $items)
    {
        $this->items = array_values($items); // Re-index
    }

    public function getQuery()
    {
        // Return null - pagination will detect this and use array methods
        return null;
    }

    public function setQuery(\TYPO3\CMS\Extbase\Persistence\QueryInterface $query): void
    {
        // Not supported for occurrence arrays
        throw new \BadMethodCallException('setQuery is not supported for OccurrenceQueryResult', 1732546199);
    }

    public function getFirst()
    {
        return $this->items[0] ?? null;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function current(): mixed
    {
        return $this->items[$this->position] ?? null;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
