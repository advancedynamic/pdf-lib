<?php

declare(strict_types=1);

namespace PdfLib\Exception;

/**
 * Exception thrown when PDF parsing fails.
 */
class ParseException extends PdfException
{
    public static function unexpectedToken(string $expected, string $actual, int $offset): self
    {
        return new self(sprintf(
            'Expected %s but found %s at offset %d',
            $expected,
            $actual,
            $offset
        ));
    }

    public static function unexpectedEndOfFile(int $offset): self
    {
        return new self(sprintf('Unexpected end of file at offset %d', $offset));
    }

    public static function invalidObject(string $message, int $offset): self
    {
        return new self(sprintf('%s at offset %d', $message, $offset));
    }

    public static function invalidXref(string $message): self
    {
        return new self(sprintf('Invalid cross-reference table: %s', $message));
    }

    public static function corruptedFile(string $reason): self
    {
        return new self(sprintf('PDF file is corrupted: %s', $reason));
    }

    public static function unsupportedFeature(string $feature): self
    {
        return new self(sprintf('Unsupported PDF feature: %s', $feature));
    }
}
