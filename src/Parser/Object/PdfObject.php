<?php

declare(strict_types=1);

namespace PdfLib\Parser\Object;

/**
 * Abstract base class for all PDF objects.
 *
 * PDF supports these object types (ISO 32000-2):
 * - Boolean (true/false)
 * - Integer and Real numbers
 * - Strings (literal and hexadecimal)
 * - Names (symbolic identifiers)
 * - Arrays (ordered collections)
 * - Dictionaries (key-value pairs)
 * - Streams (sequences of bytes)
 * - Null
 * - Indirect references (object number, generation number)
 */
abstract class PdfObject
{
    /**
     * Object number for indirect objects.
     */
    protected ?int $objectNumber = null;

    /**
     * Generation number for indirect objects.
     */
    protected ?int $generationNumber = null;

    /**
     * Get the native PHP value of this object.
     */
    abstract public function getValue(): mixed;

    /**
     * Convert this object to its PDF string representation.
     */
    abstract public function toPdfString(): string;

    /**
     * Check if this is an indirect object.
     */
    public function isIndirect(): bool
    {
        return $this->objectNumber !== null;
    }

    /**
     * Set indirect object identifiers.
     */
    public function setIndirect(int $objectNumber, int $generationNumber = 0): self
    {
        $this->objectNumber = $objectNumber;
        $this->generationNumber = $generationNumber;
        return $this;
    }

    /**
     * Get object number.
     */
    public function getObjectNumber(): ?int
    {
        return $this->objectNumber;
    }

    /**
     * Get generation number.
     */
    public function getGenerationNumber(): ?int
    {
        return $this->generationNumber;
    }

    /**
     * Get the indirect reference string (e.g., "5 0 R").
     */
    public function getReference(): string
    {
        if (!$this->isIndirect()) {
            throw new \LogicException('Cannot get reference for direct object');
        }
        return sprintf('%d %d R', $this->objectNumber, $this->generationNumber);
    }

    /**
     * Get the indirect object definition string (e.g., "5 0 obj ... endobj").
     */
    public function toIndirectString(): string
    {
        if (!$this->isIndirect()) {
            throw new \LogicException('Cannot create indirect string for direct object');
        }
        return sprintf(
            "%d %d obj\n%s\nendobj",
            $this->objectNumber,
            $this->generationNumber,
            $this->toPdfString()
        );
    }
}
