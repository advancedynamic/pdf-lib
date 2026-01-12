<?php

declare(strict_types=1);

namespace PdfLib\Font;

/**
 * Font metrics data.
 *
 * All measurements are in font units (1/1000 em) unless otherwise specified.
 */
final class FontMetrics
{
    /**
     * @param array<int, int> $widths Character widths indexed by character code
     * @param array<string, int> $bbox Font bounding box [llx, lly, urx, ury]
     */
    public function __construct(
        private int $ascender,
        private int $descender,
        private int $lineGap,
        private int $capHeight,
        private int $xHeight,
        private int $unitsPerEm,
        private int $underlinePosition,
        private int $underlineThickness,
        private int $stemV,
        private int $stemH,
        private int $italicAngle,
        private int $flags,
        private int $defaultWidth,
        private array $widths,
        private array $bbox,
        private int $missingWidth = 0,
    ) {
    }

    public function getAscender(): int
    {
        return $this->ascender;
    }

    public function getDescender(): int
    {
        return $this->descender;
    }

    public function getLineGap(): int
    {
        return $this->lineGap;
    }

    public function getLineHeight(): int
    {
        return $this->ascender - $this->descender + $this->lineGap;
    }

    public function getCapHeight(): int
    {
        return $this->capHeight;
    }

    public function getXHeight(): int
    {
        return $this->xHeight;
    }

    public function getUnitsPerEm(): int
    {
        return $this->unitsPerEm;
    }

    public function getUnderlinePosition(): int
    {
        return $this->underlinePosition;
    }

    public function getUnderlineThickness(): int
    {
        return $this->underlineThickness;
    }

    public function getStemV(): int
    {
        return $this->stemV;
    }

    public function getStemH(): int
    {
        return $this->stemH;
    }

    public function getItalicAngle(): int
    {
        return $this->italicAngle;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }

    public function getDefaultWidth(): int
    {
        return $this->defaultWidth;
    }

    /**
     * Get the width of a character by code point.
     */
    public function getWidth(int $charCode): int
    {
        return $this->widths[$charCode] ?? $this->missingWidth ?: $this->defaultWidth;
    }

    /**
     * Get all character widths.
     *
     * @return array<int, int>
     */
    public function getWidths(): array
    {
        return $this->widths;
    }

    /**
     * Get the font bounding box.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} [llx, lly, urx, ury]
     */
    public function getBoundingBox(): array
    {
        return $this->bbox;
    }

    public function getMissingWidth(): int
    {
        return $this->missingWidth;
    }

    // Font flags (PDF Reference 9.8.2)
    public function isFixedPitch(): bool
    {
        return ($this->flags & 0x0001) !== 0;
    }

    public function isSerif(): bool
    {
        return ($this->flags & 0x0002) !== 0;
    }

    public function isSymbolic(): bool
    {
        return ($this->flags & 0x0004) !== 0;
    }

    public function isScript(): bool
    {
        return ($this->flags & 0x0008) !== 0;
    }

    public function isNonSymbolic(): bool
    {
        return ($this->flags & 0x0020) !== 0;
    }

    public function isItalic(): bool
    {
        return ($this->flags & 0x0040) !== 0;
    }

    public function isAllCap(): bool
    {
        return ($this->flags & 0x10000) !== 0;
    }

    public function isSmallCap(): bool
    {
        return ($this->flags & 0x20000) !== 0;
    }

    public function isForceBold(): bool
    {
        return ($this->flags & 0x40000) !== 0;
    }

    /**
     * Scale a font unit value to a specific point size.
     */
    public function scale(int $value, float $fontSize): float
    {
        return ($value * $fontSize) / $this->unitsPerEm;
    }

    /**
     * Calculate text width at a specific point size.
     *
     * @param array<int, int> $charCodes Array of character codes
     */
    public function calculateWidth(array $charCodes, float $fontSize): float
    {
        $width = 0;
        foreach ($charCodes as $code) {
            $width += $this->getWidth($code);
        }
        return $this->scale($width, $fontSize);
    }
}
