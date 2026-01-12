<?php

declare(strict_types=1);

namespace PdfLib\Exception;

/**
 * Exception thrown for form-related errors.
 */
class FormException extends PdfException
{
    public static function noAcroForm(): self
    {
        return new self('Document does not contain an AcroForm');
    }

    public static function fieldNotFound(string $fieldName): self
    {
        return new self(sprintf('Form field not found: %s', $fieldName));
    }

    public static function invalidFieldType(string $expected, string $actual): self
    {
        return new self(sprintf('Expected field type %s but got %s', $expected, $actual));
    }

    public static function readOnlyField(string $fieldName): self
    {
        return new self(sprintf('Cannot modify read-only field: %s', $fieldName));
    }

    public static function invalidValue(string $fieldName, string $reason): self
    {
        return new self(sprintf('Invalid value for field %s: %s', $fieldName, $reason));
    }

    public static function noAppearance(string $fieldName): self
    {
        return new self(sprintf('No appearance stream for field: %s', $fieldName));
    }

    public static function invalidAction(string $reason): self
    {
        return new self(sprintf('Invalid action: %s', $reason));
    }

    public static function hierarchyError(string $message): self
    {
        return new self(sprintf('Field hierarchy error: %s', $message));
    }

    public static function duplicateField(string $fieldName): self
    {
        return new self(sprintf('Field with name already exists: %s', $fieldName));
    }
}
