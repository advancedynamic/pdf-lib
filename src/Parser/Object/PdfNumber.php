<?php

declare(strict_types=1);

namespace PdfLib\Parser\Object;

/**
 * PDF Number object (integer or real).
 *
 * PDF does not distinguish between integers and reals internally,
 * but this class preserves the distinction for precision.
 */
final class PdfNumber extends PdfObject
{
    private function __construct(
        private readonly int|float $value
    ) {
    }

    /**
     * Create from an integer.
     */
    public static function int(int $value): self
    {
        return new self($value);
    }

    /**
     * Create from a float/real number.
     */
    public static function real(float $value): self
    {
        return new self($value);
    }

    /**
     * Create from any numeric value.
     */
    public static function create(int|float $value): self
    {
        return new self($value);
    }

    public function getValue(): int|float
    {
        return $this->value;
    }

    /**
     * Get value as integer.
     */
    public function toInt(): int
    {
        return (int) $this->value;
    }

    /**
     * Get value as float.
     */
    public function toFloat(): float
    {
        return (float) $this->value;
    }

    /**
     * Check if this is an integer value.
     */
    public function isInteger(): bool
    {
        return is_int($this->value) || $this->value === floor($this->value);
    }

    public function toPdfString(): string
    {
        if (is_int($this->value)) {
            return (string) $this->value;
        }

        // Format real numbers without trailing zeros
        $formatted = sprintf('%.10f', $this->value);
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, '.');

        return $formatted;
    }
}
