<?php

declare(strict_types=1);

namespace PdfLib\Parser\Object;

/**
 * PDF Indirect Reference object.
 *
 * An indirect reference is a pointer to an indirect object elsewhere in the PDF file.
 * Format: "object_number generation_number R" (e.g., "5 0 R")
 */
final class PdfReference extends PdfObject
{
    private int $refObjectNumber;
    private int $refGenerationNumber;

    public function __construct(int $objectNumber, int $generationNumber = 0)
    {
        $this->refObjectNumber = $objectNumber;
        $this->refGenerationNumber = $generationNumber;
    }

    /**
     * Create a reference from object and generation numbers.
     */
    public static function create(int $objectNumber, int $generationNumber = 0): self
    {
        return new self($objectNumber, $generationNumber);
    }

    /**
     * @return array{objectNumber: int, generationNumber: int}
     */
    public function getValue(): array
    {
        return [
            'objectNumber' => $this->refObjectNumber,
            'generationNumber' => $this->refGenerationNumber,
        ];
    }

    /**
     * Get the referenced object number.
     */
    public function getObjectNumber(): int
    {
        return $this->refObjectNumber;
    }

    /**
     * Get the generation number.
     */
    public function getGenerationNumber(): int
    {
        return $this->refGenerationNumber;
    }

    /**
     * Get a unique key for this reference (for use in hash maps).
     */
    public function getKey(): string
    {
        return sprintf('%d_%d', $this->refObjectNumber, $this->refGenerationNumber);
    }

    /**
     * Check if this reference points to the same object as another.
     */
    public function equals(self $other): bool
    {
        return $this->refObjectNumber === $other->refObjectNumber
            && $this->refGenerationNumber === $other->refGenerationNumber;
    }

    public function toPdfString(): string
    {
        return sprintf('%d %d R', $this->refObjectNumber, $this->refGenerationNumber);
    }
}
