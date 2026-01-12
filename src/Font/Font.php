<?php

declare(strict_types=1);

namespace PdfLib\Font;

use PdfLib\Parser\Object\PdfDictionary;

/**
 * Interface for PDF fonts.
 *
 * PDF supports several font types:
 * - Type 1: PostScript fonts (includes the standard 14 fonts)
 * - TrueType: TTF/OTF fonts
 * - Type 0: Composite fonts (for CJK and Unicode)
 * - Type 3: User-defined fonts
 */
interface Font
{
    /**
     * Get the font name as it appears in the PDF.
     */
    public function getName(): string;

    /**
     * Get the PostScript name of the font.
     */
    public function getPostScriptName(): string;

    /**
     * Get the PDF font type.
     */
    public function getType(): string;

    /**
     * Get the encoding used by this font.
     */
    public function getEncoding(): string;

    /**
     * Check if this font is embedded in the PDF.
     */
    public function isEmbedded(): bool;

    /**
     * Check if this font supports a specific character.
     */
    public function hasCharacter(string $char): bool;

    /**
     * Get the width of a string in font units.
     *
     * @param string $text The text to measure
     * @param float $fontSize The font size in points
     * @return float Width in points
     */
    public function getTextWidth(string $text, float $fontSize): float;

    /**
     * Get the width of a single character in font units (1/1000 em).
     */
    public function getCharWidth(string $char): int;

    /**
     * Get the font metrics.
     */
    public function getMetrics(): FontMetrics;

    /**
     * Get the ascender height in font units (1/1000 em).
     */
    public function getAscender(): int;

    /**
     * Get the descender depth in font units (1/1000 em).
     * Usually negative.
     */
    public function getDescender(): int;

    /**
     * Get the line height (ascender - descender + line gap).
     */
    public function getLineHeight(): int;

    /**
     * Get the cap height in font units.
     */
    public function getCapHeight(): int;

    /**
     * Get the x-height in font units.
     */
    public function getXHeight(): int;

    /**
     * Get the underline position in font units.
     */
    public function getUnderlinePosition(): int;

    /**
     * Get the underline thickness in font units.
     */
    public function getUnderlineThickness(): int;

    /**
     * Convert to PDF dictionary for embedding.
     */
    public function toDictionary(): PdfDictionary;
}
