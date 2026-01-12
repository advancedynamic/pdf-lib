<?php

declare(strict_types=1);

namespace PdfLib\Content\Text;

use PdfLib\Content\ContentStream;

/**
 * A block of text with position and style.
 *
 * Represents a single-line text element that can be rendered to a content stream.
 */
final class TextBlock
{
    private string $text;
    private TextStyle $style;
    private float $x = 0;
    private float $y = 0;
    private float $angle = 0;

    public function __construct(string $text, ?TextStyle $style = null)
    {
        $this->text = $text;
        $this->style = $style ?? new TextStyle();
    }

    /**
     * Create a new text block.
     */
    public static function create(string $text, ?TextStyle $style = null): self
    {
        return new self($text, $style);
    }

    // Getters

    public function getText(): string
    {
        return $this->text;
    }

    public function getStyle(): TextStyle
    {
        return $this->style;
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function getAngle(): float
    {
        return $this->angle;
    }

    /**
     * Get the width of the text.
     */
    public function getWidth(): float
    {
        return $this->style->getTextWidth($this->text);
    }

    /**
     * Get the height of the text (font size).
     */
    public function getHeight(): float
    {
        return $this->style->getFontSize();
    }

    // Fluent setters

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function setStyle(TextStyle $style): self
    {
        $this->style = $style;
        return $this;
    }

    public function setPosition(float $x, float $y): self
    {
        $this->x = $x;
        $this->y = $y;
        return $this;
    }

    public function setX(float $x): self
    {
        $this->x = $x;
        return $this;
    }

    public function setY(float $y): self
    {
        $this->y = $y;
        return $this;
    }

    public function setAngle(float $angle): self
    {
        $this->angle = $angle;
        return $this;
    }

    /**
     * Render the text block to a content stream.
     */
    public function render(ContentStream $stream): void
    {
        $style = $this->style;
        $fontName = $stream->registerFont($style->getFont());

        $stream->saveState();

        // Set text color
        $stream->setFillColor($style->getColor());

        // Begin text object
        $stream->beginText();

        // Set font
        $stream->setFont($fontName, $style->getFontSize());

        // Set text properties
        if ($style->getCharacterSpacing() !== 0.0) {
            $stream->setCharacterSpacing($style->getCharacterSpacing());
        }
        if ($style->getWordSpacing() !== 0.0) {
            $stream->setWordSpacing($style->getWordSpacing());
        }
        if ($style->getHorizontalScaling() !== 100.0) {
            $stream->setHorizontalScaling($style->getHorizontalScaling());
        }
        if ($style->getRise() !== 0.0) {
            $stream->setTextRise($style->getRise());
        }
        if ($style->getRenderingMode() !== 0) {
            $stream->setTextRenderingMode($style->getRenderingMode());
        }

        // Position and rotation
        if ($this->angle !== 0.0) {
            $rad = deg2rad($this->angle);
            $cos = cos($rad);
            $sin = sin($rad);
            $stream->setTextMatrix($cos, $sin, -$sin, $cos, $this->x, $this->y);
        } else {
            $stream->setTextMatrix(1, 0, 0, 1, $this->x, $this->y);
        }

        // Show text
        $stream->showText($this->text);

        // End text object
        $stream->endText();

        // Draw underline if enabled
        if ($style->isUnderline()) {
            $this->drawUnderline($stream);
        }

        // Draw strikethrough if enabled
        if ($style->isStrikethrough()) {
            $this->drawStrikethrough($stream);
        }

        $stream->restoreState();
    }

    /**
     * Draw underline decoration.
     */
    private function drawUnderline(ContentStream $stream): void
    {
        $font = $this->style->getFont();
        $fontSize = $this->style->getFontSize();

        $position = ($font->getUnderlinePosition() * $fontSize) / 1000;
        $thickness = ($font->getUnderlineThickness() * $fontSize) / 1000;
        $width = $this->getWidth();

        $y = $this->y + $position;

        $stream->setStrokeColor($this->style->getUnderlineColor());
        $stream->setLineWidth($thickness);

        if ($this->angle !== 0.0) {
            $stream->saveState();
            $stream->translate($this->x, $this->y);
            $stream->rotate($this->angle);
            $stream->line(0, $position, $width, $position);
            $stream->restoreState();
        } else {
            $stream->line($this->x, $y, $this->x + $width, $y);
        }
    }

    /**
     * Draw strikethrough decoration.
     */
    private function drawStrikethrough(ContentStream $stream): void
    {
        $font = $this->style->getFont();
        $fontSize = $this->style->getFontSize();

        // Position at approximately middle of x-height
        $position = ($font->getXHeight() * $fontSize) / 2000;
        $thickness = ($font->getUnderlineThickness() * $fontSize) / 1000;
        $width = $this->getWidth();

        $y = $this->y + $position;

        $stream->setStrokeColor($this->style->getStrikethroughColor());
        $stream->setLineWidth($thickness);

        if ($this->angle !== 0.0) {
            $stream->saveState();
            $stream->translate($this->x, $this->y);
            $stream->rotate($this->angle);
            $stream->line(0, $position, $width, $position);
            $stream->restoreState();
        } else {
            $stream->line($this->x, $y, $this->x + $width, $y);
        }
    }
}
