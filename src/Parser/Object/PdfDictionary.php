<?php

declare(strict_types=1);

namespace PdfLib\Parser\Object;

use ArrayAccess;
use Countable;
use Iterator;

/**
 * PDF Dictionary object.
 *
 * Dictionaries are associative collections of key-value pairs.
 * Keys must be name objects, values can be any PDF object type.
 * Dictionaries are enclosed in double angle brackets << >>.
 *
 * @implements ArrayAccess<string, PdfObject>
 * @implements Iterator<string, PdfObject>
 */
final class PdfDictionary extends PdfObject implements ArrayAccess, Countable, Iterator
{
    /** @var array<string, PdfObject> */
    private array $entries;

    /** @var array<int, string> */
    private array $keys;

    private int $position = 0;

    /**
     * @param array<string, PdfObject> $entries
     */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
        $this->keys = array_keys($entries);
    }

    /**
     * Create from an associative array of values (auto-converts to PDF objects).
     *
     * @param array<string, mixed> $values
     */
    public static function fromValues(array $values): self
    {
        $entries = [];
        foreach ($values as $key => $value) {
            $entries[$key] = self::toPdfObject($value);
        }
        return new self($entries);
    }

    /**
     * @return array<string, PdfObject>
     */
    public function getValue(): array
    {
        return $this->entries;
    }

    /**
     * Get all entries as native PHP values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->entries as $key => $obj) {
            $result[$key] = $obj->getValue();
        }
        return $result;
    }

    /**
     * Get entry by key name.
     */
    public function get(string $key): ?PdfObject
    {
        return $this->entries[$key] ?? null;
    }

    /**
     * Check if key exists.
     */
    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }

    /**
     * Set an entry.
     */
    public function set(string $key, PdfObject $value): self
    {
        if (!isset($this->entries[$key])) {
            $this->keys[] = $key;
        }
        $this->entries[$key] = $value;
        return $this;
    }

    /**
     * Remove an entry.
     */
    public function remove(string $key): self
    {
        unset($this->entries[$key]);
        $this->keys = array_values(array_diff($this->keys, [$key]));
        return $this;
    }

    /**
     * Get the /Type entry value if present.
     */
    public function getType(): ?string
    {
        $type = $this->get(PdfName::TYPE);
        if ($type instanceof PdfName) {
            return $type->getValue();
        }
        return null;
    }

    /**
     * Get the /Subtype entry value if present.
     */
    public function getSubtype(): ?string
    {
        $subtype = $this->get(PdfName::SUBTYPE);
        if ($subtype instanceof PdfName) {
            return $subtype->getValue();
        }
        return null;
    }

    /**
     * Get all keys.
     *
     * @return array<int, string>
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    /**
     * Check if dictionary is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->entries) === 0;
    }

    /**
     * Merge another dictionary into this one.
     */
    public function merge(self $other): self
    {
        foreach ($other->entries as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    public function toPdfString(): string
    {
        if ($this->isEmpty()) {
            return '<< >>';
        }

        $parts = [];
        foreach ($this->entries as $key => $value) {
            $nameObj = PdfName::create($key);
            $parts[] = $nameObj->toPdfString() . ' ' . $value->toPdfString();
        }

        return "<<\n" . implode("\n", $parts) . "\n>>";
    }

    // ArrayAccess implementation

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->entries[$offset]);
    }

    public function offsetGet(mixed $offset): ?PdfObject
    {
        return $this->entries[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    // Countable implementation

    public function count(): int
    {
        return count($this->entries);
    }

    // Iterator implementation

    public function current(): PdfObject
    {
        return $this->entries[$this->keys[$this->position]];
    }

    public function key(): string
    {
        return $this->keys[$this->position];
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
        return isset($this->keys[$this->position]);
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
            is_array($value) && array_is_list($value) => PdfArray::fromValues($value),
            is_array($value) => self::fromValues($value),
            default => throw new \InvalidArgumentException('Cannot convert value to PDF object'),
        };
    }
}
