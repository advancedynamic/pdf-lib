<?php

declare(strict_types=1);

namespace PdfLib\Font;

/**
 * Factory for creating and loading fonts.
 */
final class FontFactory
{
    /**
     * Font aliases for common names.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'courier' => Type1Font::COURIER,
        'courier-bold' => Type1Font::COURIER_BOLD,
        'courier-italic' => Type1Font::COURIER_OBLIQUE,
        'courier-oblique' => Type1Font::COURIER_OBLIQUE,
        'courier-bolditalic' => Type1Font::COURIER_BOLD_OBLIQUE,
        'courier-boldoblique' => Type1Font::COURIER_BOLD_OBLIQUE,

        'helvetica' => Type1Font::HELVETICA,
        'arial' => Type1Font::HELVETICA,
        'helvetica-bold' => Type1Font::HELVETICA_BOLD,
        'arial-bold' => Type1Font::HELVETICA_BOLD,
        'helvetica-italic' => Type1Font::HELVETICA_OBLIQUE,
        'helvetica-oblique' => Type1Font::HELVETICA_OBLIQUE,
        'arial-italic' => Type1Font::HELVETICA_OBLIQUE,
        'helvetica-bolditalic' => Type1Font::HELVETICA_BOLD_OBLIQUE,
        'helvetica-boldoblique' => Type1Font::HELVETICA_BOLD_OBLIQUE,
        'arial-bolditalic' => Type1Font::HELVETICA_BOLD_OBLIQUE,

        'times' => Type1Font::TIMES_ROMAN,
        'times-roman' => Type1Font::TIMES_ROMAN,
        'timesroman' => Type1Font::TIMES_ROMAN,
        'times-new-roman' => Type1Font::TIMES_ROMAN,
        'times-bold' => Type1Font::TIMES_BOLD,
        'timesbold' => Type1Font::TIMES_BOLD,
        'times-italic' => Type1Font::TIMES_ITALIC,
        'timesitalic' => Type1Font::TIMES_ITALIC,
        'times-bolditalic' => Type1Font::TIMES_BOLD_ITALIC,
        'timesbolditalic' => Type1Font::TIMES_BOLD_ITALIC,

        'symbol' => Type1Font::SYMBOL,
        'zapfdingbats' => Type1Font::ZAPF_DINGBATS,
        'dingbats' => Type1Font::ZAPF_DINGBATS,
    ];

    /**
     * Create a font by name.
     *
     * Supports:
     * - Standard 14 font names (Helvetica, Times-Roman, Courier, etc.)
     * - Common aliases (Arial -> Helvetica, etc.)
     */
    public static function create(string $name): Font
    {
        $normalizedName = self::normalizeName($name);

        // Check aliases
        if (isset(self::ALIASES[$normalizedName])) {
            return Type1Font::create(self::ALIASES[$normalizedName]);
        }

        // Check if it's a standard 14 font
        if (Type1Font::isStandard($name)) {
            return Type1Font::create($name);
        }

        throw new \InvalidArgumentException("Unknown font: $name");
    }

    /**
     * Get a standard Type 1 font.
     */
    public static function standard(string $name): Type1Font
    {
        return Type1Font::create($name);
    }

    // Convenience methods for standard fonts

    public static function courier(): Type1Font
    {
        return Type1Font::courier();
    }

    public static function courierBold(): Type1Font
    {
        return Type1Font::courierBold();
    }

    public static function courierOblique(): Type1Font
    {
        return Type1Font::courierOblique();
    }

    public static function courierBoldOblique(): Type1Font
    {
        return Type1Font::courierBoldOblique();
    }

    public static function helvetica(): Type1Font
    {
        return Type1Font::helvetica();
    }

    public static function helveticaBold(): Type1Font
    {
        return Type1Font::helveticaBold();
    }

    public static function helveticaOblique(): Type1Font
    {
        return Type1Font::helveticaOblique();
    }

    public static function helveticaBoldOblique(): Type1Font
    {
        return Type1Font::helveticaBoldOblique();
    }

    public static function timesRoman(): Type1Font
    {
        return Type1Font::timesRoman();
    }

    public static function timesBold(): Type1Font
    {
        return Type1Font::timesBold();
    }

    public static function timesItalic(): Type1Font
    {
        return Type1Font::timesItalic();
    }

    public static function timesBoldItalic(): Type1Font
    {
        return Type1Font::timesBoldItalic();
    }

    public static function symbol(): Type1Font
    {
        return Type1Font::symbol();
    }

    public static function zapfDingbats(): Type1Font
    {
        return Type1Font::zapfDingbats();
    }

    /**
     * Get list of available standard font names.
     *
     * @return array<int, string>
     */
    public static function getStandardFontNames(): array
    {
        return Type1Font::getStandardFontNames();
    }

    /**
     * Get list of font aliases.
     *
     * @return array<string, string>
     */
    public static function getAliases(): array
    {
        return self::ALIASES;
    }

    /**
     * Check if a font name is available.
     */
    public static function isAvailable(string $name): bool
    {
        $normalizedName = self::normalizeName($name);

        return isset(self::ALIASES[$normalizedName]) || Type1Font::isStandard($name);
    }

    /**
     * Normalize a font name for lookup.
     */
    private static function normalizeName(string $name): string
    {
        return strtolower(str_replace([' ', '_'], '-', trim($name)));
    }
}
