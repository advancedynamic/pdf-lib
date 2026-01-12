<?php

declare(strict_types=1);

namespace PdfLib\Parser\Object;

/**
 * PDF Boolean object.
 *
 * Boolean objects represent the logical values true and false.
 */
final class PdfBoolean extends PdfObject
{
    private static ?self $true = null;
    private static ?self $false = null;

    private function __construct(
        private readonly bool $value
    ) {
    }

    /**
     * Create a boolean from a PHP bool.
     */
    public static function create(bool $value): self
    {
        if ($value) {
            return self::true();
        }
        return self::false();
    }

    /**
     * Get the true singleton.
     */
    public static function true(): self
    {
        if (self::$true === null) {
            self::$true = new self(true);
        }
        return self::$true;
    }

    /**
     * Get the false singleton.
     */
    public static function false(): self
    {
        if (self::$false === null) {
            self::$false = new self(false);
        }
        return self::$false;
    }

    public function getValue(): bool
    {
        return $this->value;
    }

    public function toPdfString(): string
    {
        return $this->value ? 'true' : 'false';
    }

    public function isTrue(): bool
    {
        return $this->value;
    }

    public function isFalse(): bool
    {
        return !$this->value;
    }
}
