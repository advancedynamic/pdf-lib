<?php

declare(strict_types=1);

namespace PdfLib\Parser\Object;

/**
 * PDF Name object.
 *
 * Names are symbolic identifiers that begin with a forward slash.
 * They are case-sensitive and unique (e.g., /Type, /Page, /Font).
 */
final class PdfName extends PdfObject
{
    /** @var array<string, self> */
    private static array $cache = [];

    private function __construct(
        private readonly string $value
    ) {
    }

    /**
     * Create a name from a string (without the leading slash).
     */
    public static function create(string $name): self
    {
        // Cache commonly used names
        if (!isset(self::$cache[$name])) {
            self::$cache[$name] = new self($name);
        }
        return self::$cache[$name];
    }

    /**
     * Create a name from an encoded PDF name string (may include #XX escapes).
     */
    public static function fromEncoded(string $encoded): self
    {
        $decoded = self::decode($encoded);
        return self::create($decoded);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Check if this name equals another name or string.
     */
    public function equals(self|string $other): bool
    {
        if ($other instanceof self) {
            return $this->value === $other->value;
        }
        return $this->value === $other;
    }

    public function toPdfString(): string
    {
        return '/' . self::encode($this->value);
    }

    /**
     * Encode a name value for PDF output.
     * Characters outside the regular set are encoded as #XX.
     */
    private static function encode(string $value): string
    {
        $result = '';
        $len = strlen($value);

        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];
            $ord = ord($char);

            // Regular characters (! to ~ except #)
            if ($ord >= 33 && $ord <= 126 && $char !== '#') {
                // Delimiter characters must be escaped
                if (in_array($char, ['(', ')', '<', '>', '[', ']', '{', '}', '/', '%'], true)) {
                    $result .= sprintf('#%02X', $ord);
                } else {
                    $result .= $char;
                }
            } else {
                $result .= sprintf('#%02X', $ord);
            }
        }

        return $result;
    }

    /**
     * Decode a PDF name with #XX escape sequences.
     */
    private static function decode(string $encoded): string
    {
        return preg_replace_callback(
            '/#([0-9A-Fa-f]{2})/',
            fn (array $matches) => chr(hexdec($matches[1])),
            $encoded
        ) ?? $encoded;
    }

    // Common PDF names as constants
    public const TYPE = 'Type';
    public const SUBTYPE = 'Subtype';
    public const PAGE = 'Page';
    public const PAGES = 'Pages';
    public const CATALOG = 'Catalog';
    public const FONT = 'Font';
    public const XOBJECT = 'XObject';
    public const IMAGE = 'Image';
    public const CONTENTS = 'Contents';
    public const RESOURCES = 'Resources';
    public const MEDIABOX = 'MediaBox';
    public const CROPBOX = 'CropBox';
    public const BLEEDBOX = 'BleedBox';
    public const TRIMBOX = 'TrimBox';
    public const ARTBOX = 'ArtBox';
    public const PARENT = 'Parent';
    public const KIDS = 'Kids';
    public const COUNT = 'Count';
    public const LENGTH = 'Length';
    public const FILTER = 'Filter';
    public const FLATE_DECODE = 'FlateDecode';
    public const ASCII_HEX_DECODE = 'ASCIIHexDecode';
    public const ASCII85_DECODE = 'ASCII85Decode';
    public const LZW_DECODE = 'LZWDecode';
    public const DCTDECODE = 'DCTDecode';
}
