<?php

declare(strict_types=1);

namespace PdfLib\Parser\Object;

/**
 * PDF Null object.
 *
 * The null object has a type and value that are unequal to those of any other object.
 */
final class PdfNull extends PdfObject
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    /**
     * Get the singleton null instance.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getValue(): null
    {
        return null;
    }

    public function toPdfString(): string
    {
        return 'null';
    }
}
