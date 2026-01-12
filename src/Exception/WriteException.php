<?php

declare(strict_types=1);

namespace PdfLib\Exception;

/**
 * Exception thrown when PDF writing fails.
 */
class WriteException extends PdfException
{
    public static function invalidObject(string $type): self
    {
        return new self(sprintf('Cannot write invalid PDF object of type: %s', $type));
    }

    public static function streamError(string $message): self
    {
        return new self(sprintf('Stream write error: %s', $message));
    }

    public static function fileError(string $path, string $reason): self
    {
        return new self(sprintf('Cannot write to file "%s": %s', $path, $reason));
    }

    public static function compressionError(string $message): self
    {
        return new self(sprintf('Compression error: %s', $message));
    }
}
