<?php

declare(strict_types=1);

namespace PdfLib\Parser\Object;

use ArrayAccess;
use Countable;
use Iterator;

/**
 * PDF Array object.
 *
 * Arrays are one-dimensional collections of objects that may be of different types.
 * Arrays are enclosed in square brackets.
 *
 * @implements ArrayAccess<int, PdfObject>
 * @implements Iterator<int, PdfObject>
 */
final class PdfArray extends PdfObject implements ArrayAccess, Countable, Iterator
{
    /** @var array<int, PdfObject> */
    private array $items;

    private int $position = 0;

    /**
     * @param array<int, PdfObject> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    /**
     * Create from an array of values (auto-converts to PDF objects).
     *
     * @param array<int, mixed> $values
     */
    public static function fromValues(array $values): self
    {
        $items = array_map(fn ($value) => self::toPdfObject($value), $values);
        return new self($items);
    }

    /**
     * @return array<int, PdfObject>
     */
    public function getValue(): array
    {
        return $this->items;
    }

    /**
     * Get all items as native PHP values.
     *
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        return array_map(fn (PdfObject $obj) => $obj->getValue(), $this->items);
    }

    /**
     * Get item at index.
     */
    public function get(int $index): ?PdfObject
    {
        return $this->items[$index] ?? null;
    }

    /**
     * Add an item to the array.
     */
    public function push(PdfObject $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    /**
     * Check if array is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    public function toPdfString(): string
    {
        $parts = array_map(fn (PdfObject $obj) => $obj->toPdfString(), $this->items);
        return '[' . implode(' ', $parts) . ']';
    }

    // ArrayAccess implementation

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): ?PdfObject
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
        $this->items = array_values($this->items);
    }

    // Countable implementation

    public function count(): int
    {
        return count($this->items);
    }

    // Iterator implementation

    public function current(): PdfObject
    {
        return $this->items[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }

    /**
     * Convert a PHP value to a PDF object.
     */
    private static function toPdfObject(mixed $value): PdfObject
    {
        if ($value instanceof PdfObject) {
            return $value;
        }

        return match (true) {
            $value === null => PdfNull::instance(),
            is_bool($value) => PdfBoolean::create($value),
            is_int($value) => PdfNumber::int($value),
            is_float($value) => PdfNumber::real($value),
            is_string($value) => PdfString::literal($value),
            is_array($value) => self::fromValues($value),
            default => throw new \InvalidArgumentException('Cannot convert value to PDF object'),
        };
    }
}
