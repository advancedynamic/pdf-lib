<?php

declare(strict_types=1);

namespace PdfLib\Content\Text;

use PdfLib\Color\Color;
use PdfLib\Color\RgbColor;
use PdfLib\Font\Font;
use PdfLib\Font\Type1Font;

/**
 * Text style configuration.
 *
 * Defines font, size, color, and other text properties.
 */
final class TextStyle
{
    private Font $font;
    private float $fontSize;
    private Color $color;
    private float $characterSpacing = 0;
    private float $wordSpacing = 0;
    private float $horizontalScaling = 100;
    private float $leading = 0;
    private float $rise = 0;
    private int $renderingMode = 0;
    private bool $underline = false;
    private bool $strikethrough = false;
    private ?Color $underlineColor = null;
    private ?Color $strikethroughColor = null;

    public function __construct(
        ?Font $font = null,
        float $fontSize = 12,
        ?Color $color = null
    ) {
        $this->font = $font ?? Type1Font::helvetica();
        $this->fontSize = $fontSize;
        $this->color = $color ?? RgbColor::black();
        $this->leading = $this->fontSize * 1.2;
    }

    /**
     * Create a new text style.
     */
    public static function create(?Font $font = null, float $fontSize = 12, ?Color $color = null): self
    {
        return new self($font, $fontSize, $color);
    }

    // Getters

    public function getFont(): Font
    {
        return $this->font;
    }

    public function getFontSize(): float
    {
        return $this->fontSize;
    }

    public function getColor(): Color
    {
        return $this->color;
    }

    public function getCharacterSpacing(): float
    {
        return $this->characterSpacing;
    }

    public function getWordSpacing(): float
    {
        return $this->wordSpacing;
    }

    public function getHorizontalScaling(): float
    {
        return $this->horizontalScaling;
    }

    public function getLeading(): float
    {
        return $this->leading;
    }

    public function getRise(): float
    {
        return $this->rise;
    }

    public function getRenderingMode(): int
    {
        return $this->renderingMode;
    }

    public function isUnderline(): bool
    {
        return $this->underline;
    }

    public function isStrikethrough(): bool
    {
        return $this->strikethrough;
    }

    public function getUnderlineColor(): Color
    {
        return $this->underlineColor ?? $this->color;
    }

    public function getStrikethroughColor(): Color
    {
        return $this->strikethroughColor ?? $this->color;
    }

    // Fluent setters (return new instance for immutability)

    public function withFont(Font $font): self
    {
        $clone = clone $this;
        $clone->font = $font;
        return $clone;
    }

    public function withFontSize(float $size): self
    {
        $clone = clone $this;
        $clone->fontSize = $size;
        // Update leading to maintain ratio
        if ($clone->leading === $this->fontSize * 1.2) {
            $clone->leading = $size * 1.2;
        }
        return $clone;
    }

    public function withColor(Color $color): self
    {
        $clone = clone $this;
        $clone->color = $color;
        return $clone;
    }

    public function withCharacterSpacing(float $spacing): self
    {
        $clone = clone $this;
        $clone->characterSpacing = $spacing;
        return $clone;
    }

    public function withWordSpacing(float $spacing): self
    {
        $clone = clone $this;
        $clone->wordSpacing = $spacing;
        return $clone;
    }

    public function withHorizontalScaling(float $scaling): self
    {
        $clone = clone $this;
        $clone->horizontalScaling = $scaling;
        return $clone;
    }

    public function withLeading(float $leading): self
    {
        $clone = clone $this;
        $clone->leading = $leading;
        return $clone;
    }

    public function withRise(float $rise): self
    {
        $clone = clone $this;
        $clone->rise = $rise;
        return $clone;
    }

    public function withRenderingMode(int $mode): self
    {
        $clone = clone $this;
        $clone->renderingMode = $mode;
        return $clone;
    }

    public function withUnderline(bool $underline = true, ?Color $color = null): self
    {
        $clone = clone $this;
        $clone->underline = $underline;
        $clone->underlineColor = $color;
        return $clone;
    }

    public function withStrikethrough(bool $strikethrough = true, ?Color $color = null): self
    {
        $clone = clone $this;
        $clone->strikethrough = $strikethrough;
        $clone->strikethroughColor = $color;
        return $clone;
    }

    // Rendering mode constants
    public const RENDER_FILL = 0;
    public const RENDER_STROKE = 1;
    public const RENDER_FILL_STROKE = 2;
    public const RENDER_INVISIBLE = 3;
    public const RENDER_FILL_CLIP = 4;
    public const RENDER_STROKE_CLIP = 5;
    public const RENDER_FILL_STROKE_CLIP = 6;
    public const RENDER_CLIP = 7;

    /**
     * Calculate the width of text with this style.
     */
    public function getTextWidth(string $text): float
    {
        $baseWidth = $this->font->getTextWidth($text, $this->fontSize);

        // Apply character spacing
        if ($this->characterSpacing !== 0.0) {
            $numChars = mb_strlen($text);
            $baseWidth += ($numChars - 1) * $this->characterSpacing;
        }

        // Apply word spacing
        if ($this->wordSpacing !== 0.0) {
            $numSpaces = substr_count($text, ' ');
            $baseWidth += $numSpaces * $this->wordSpacing;
        }

        // Apply horizontal scaling
        if ($this->horizontalScaling !== 100.0) {
            $baseWidth *= $this->horizontalScaling / 100;
        }

        return $baseWidth;
    }

    /**
     * Get the line height (ascender - descender scaled to font size).
     */
    public function getLineHeight(): float
    {
        return ($this->font->getLineHeight() * $this->fontSize) / 1000;
    }

    /**
     * Get the ascender height scaled to font size.
     */
    public function getAscender(): float
    {
        return ($this->font->getAscender() * $this->fontSize) / 1000;
    }

    /**
     * Get the descender depth scaled to font size (usually negative).
     */
    public function getDescender(): float
    {
        return ($this->font->getDescender() * $this->fontSize) / 1000;
    }

    /**
     * Get the cap height scaled to font size.
     */
    public function getCapHeight(): float
    {
        return ($this->font->getCapHeight() * $this->fontSize) / 1000;
    }

    /**
     * Get the x-height scaled to font size.
     */
    public function getXHeight(): float
    {
        return ($this->font->getXHeight() * $this->fontSize) / 1000;
    }
}
