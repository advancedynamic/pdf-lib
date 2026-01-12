<?php

declare(strict_types=1);

namespace PdfLib\Form;

use ArrayAccess;
use Countable;
use Iterator;
use PdfLib\Exception\FormException;

/**
 * Collection of form fields.
 *
 * Provides array-like access to form fields with support for
 * iteration, counting, and array access by field name.
 *
 * @implements ArrayAccess<string, FormField>
 * @implements Iterator<string, FormField>
 */
final class FormFieldCollection implements ArrayAccess, Countable, Iterator
{
    /** @var array<string, FormField> */
    private array $fields = [];

    /** @var array<int, string> */
    private array $keys = [];

    private int $position = 0;

    /**
     * Add a form field.
     *
     * @throws FormException if field with same name already exists
     */
    public function add(FormField $field): self
    {
        $name = $field->getName();
        if (isset($this->fields[$name])) {
            throw FormException::duplicateField($name);
        }
        $this->fields[$name] = $field;
        $this->keys[] = $name;
        return $this;
    }

    /**
     * Add or replace a form field.
     */
    public function set(FormField $field): self
    {
        $name = $field->getName();
        if (!isset($this->fields[$name])) {
            $this->keys[] = $name;
        }
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Get a field by name.
     */
    public function get(string $name): ?FormField
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * Check if field exists.
     */
    public function has(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    /**
     * Remove a field.
     */
    public function remove(string $name): self
    {
        if (isset($this->fields[$name])) {
            unset($this->fields[$name]);
            $this->keys = array_values(array_diff($this->keys, [$name]));
        }
        return $this;
    }

    /**
     * Get all fields.
     *
     * @return array<string, FormField>
     */
    public function all(): array
    {
        return $this->fields;
    }

    /**
     * Get all field names.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return $this->keys;
    }

    /**
     * Get fields for a specific page.
     *
     * @return array<string, FormField>
     */
    public function forPage(int $page): array
    {
        return array_filter(
            $this->fields,
            static fn(FormField $field): bool => $field->getPage() === $page
        );
    }

    /**
     * Get fields of a specific type.
     *
     * @param string $fieldType Field type: Tx, Btn, Ch, Sig
     * @return array<string, FormField>
     */
    public function ofType(string $fieldType): array
    {
        return array_filter(
            $this->fields,
            static fn(FormField $field): bool => $field->getFieldType() === $fieldType
        );
    }

    /**
     * Get all text fields.
     *
     * @return array<string, TextField>
     */
    public function textFields(): array
    {
        return array_filter(
            $this->fields,
            static fn(FormField $field): bool => $field instanceof TextField
        );
    }

    /**
     * Get all checkbox fields.
     *
     * @return array<string, CheckboxField>
     */
    public function checkboxFields(): array
    {
        return array_filter(
            $this->fields,
            static fn(FormField $field): bool => $field instanceof CheckboxField
        );
    }

    /**
     * Clear all fields.
     */
    public function clear(): self
    {
        $this->fields = [];
        $this->keys = [];
        $this->position = 0;
        return $this;
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->fields);
    }

    /**
     * Get field values as associative array.
     *
     * @return array<string, mixed>
     */
    public function getValues(): array
    {
        $values = [];
        foreach ($this->fields as $name => $field) {
            $values[$name] = $field->getValue();
        }
        return $values;
    }

    /**
     * Set multiple field values at once.
     *
     * @param array<string, mixed> $values
     * @throws FormException if a field is not found
     */
    public function setValues(array $values): self
    {
        foreach ($values as $name => $value) {
            if (!isset($this->fields[$name])) {
                throw FormException::fieldNotFound($name);
            }
            $this->fields[$name]->setValue($value);
        }
        return $this;
    }

    // ArrayAccess implementation

    /**
     * @param string $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->fields[$offset]);
    }

    /**
     * @param string $offset
     */
    public function offsetGet(mixed $offset): ?FormField
    {
        return $this->fields[$offset] ?? null;
    }

    /**
     * @param string|null $offset
     * @param FormField $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$value instanceof FormField) {
            throw new \InvalidArgumentException('Value must be a FormField instance');
        }

        if ($offset === null) {
            $this->add($value);
        } else {
            if (!isset($this->fields[$offset])) {
                $this->keys[] = $offset;
            }
            $value->setName($offset);
            $this->fields[$offset] = $value;
        }
    }

    /**
     * @param string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    // Countable implementation

    public function count(): int
    {
        return count($this->fields);
    }

    // Iterator implementation

    public function current(): FormField
    {
        return $this->fields[$this->keys[$this->position]];
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
     * Convert all fields to array representation.
     *
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->fields as $name => $field) {
            $result[$name] = $field->toArray();
        }
        return $result;
    }
}
