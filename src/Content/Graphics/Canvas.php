<?php

declare(strict_types=1);

namespace PdfLib\Content\Graphics;

use PdfLib\Color\Color;
use PdfLib\Color\RgbColor;
use PdfLib\Content\ContentStream;
use PdfLib\Content\Text\TextBlock;
use PdfLib\Content\Text\TextStyle;
use PdfLib\Font\Font;

/**
 * High-level drawing canvas for PDF pages.
 *
 * Provides a convenient API for drawing shapes, text, and images.
 */
final class Canvas
{
    private ContentStream $stream;
    private float $width;
    private float $height;

    // Current drawing state
    private ?Color $fillColor = null;
    private ?Color $strokeColor = null;
    private float $lineWidth = 1.0;
    private ?Font $font = null;
    private float $fontSize = 12;

    public function __construct(float $width, float $height)
    {
        $this->width = $width;
        $this->height = $height;
        $this->stream = new ContentStream();
        $this->fillColor = RgbColor::black();
        $this->strokeColor = RgbColor::black();
    }

    /**
     * Create a new canvas with the given dimensions.
     */
    public static function create(float $width, float $height): self
    {
        return new self($width, $height);
    }

    /**
     * Get the content stream.
     */
    public function getContentStream(): ContentStream
    {
        return $this->stream;
    }

    /**
     * Get canvas width.
     */
    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * Get canvas height.
     */
    public function getHeight(): float
    {
        return $this->height;
    }

    // State management

    /**
     * Save the current graphics state.
     */
    public function save(): self
    {
        $this->stream->saveState();
        return $this;
    }

    /**
     * Restore the previous graphics state.
     */
    public function restore(): self
    {
        $this->stream->restoreState();
        return $this;
    }

    // Color and style

    /**
     * Set the fill color.
     */
    public function setFillColor(Color $color): self
    {
        $this->fillColor = $color;
        return $this;
    }

    /**
     * Set the stroke color.
     */
    public function setStrokeColor(Color $color): self
    {
        $this->strokeColor = $color;
        return $this;
    }

    /**
     * Set both fill and stroke color.
     */
    public function setColor(Color $color): self
    {
        $this->fillColor = $color;
        $this->strokeColor = $color;
        return $this;
    }

    /**
     * Set the line width for strokes.
     */
    public function setLineWidth(float $width): self
    {
        $this->lineWidth = $width;
        return $this;
    }

    /**
     * Set the current font.
     */
    public function setFont(Font $font, float $size): self
    {
        $this->font = $font;
        $this->fontSize = $size;
        return $this;
    }

    // Transformations

    /**
     * Translate the coordinate system.
     */
    public function translate(float $x, float $y): self
    {
        $this->stream->translate($x, $y);
        return $this;
    }

    /**
     * Scale the coordinate system.
     */
    public function scale(float $sx, float $sy): self
    {
        $this->stream->scale($sx, $sy);
        return $this;
    }

    /**
     * Rotate the coordinate system.
     */
    public function rotate(float $angle): self
    {
        $this->stream->rotate($angle);
        return $this;
    }

    // Basic shapes

    /**
     * Draw a line.
     */
    public function line(float $x1, float $y1, float $x2, float $y2): self
    {
        $this->stream->saveState();
        if ($this->strokeColor !== null) {
            $this->stream->setStrokeColor($this->strokeColor);
        }
        $this->stream->setLineWidth($this->lineWidth);
        $this->stream->line($x1, $y1, $x2, $y2);
        $this->stream->restoreState();
        return $this;
    }

    /**
     * Draw a rectangle.
     */
    public function rect(float $x, float $y, float $width, float $height, bool $fill = true, bool $stroke = false): self
    {
        $shape = Shape::rectangle($x, $y, $width, $height);

        if ($fill && $this->fillColor !== null) {
            $shape->fill($this->fillColor);
        }
        if ($stroke && $this->strokeColor !== null) {
            $shape->stroke($this->strokeColor, $this->lineWidth);
        }

        $shape->render($this->stream);
        return $this;
    }

    /**
     * Draw a filled rectangle.
     */
    public function fillRect(float $x, float $y, float $width, float $height): self
    {
        return $this->rect($x, $y, $width, $height, true, false);
    }

    /**
     * Draw a stroked rectangle.
     */
    public function strokeRect(float $x, float $y, float $width, float $height): self
    {
        return $this->rect($x, $y, $width, $height, false, true);
    }

    /**
     * Draw a rounded rectangle.
     */
    public function roundedRect(
        float $x,
        float $y,
        float $width,
        float $height,
        float $radius,
        bool $fill = true,
        bool $stroke = false
    ): self {
        $shape = Shape::roundedRectangle($x, $y, $width, $height, $radius);

        if ($fill && $this->fillColor !== null) {
            $shape->fill($this->fillColor);
        }
        if ($stroke && $this->strokeColor !== null) {
            $shape->stroke($this->strokeColor, $this->lineWidth);
        }

        $shape->render($this->stream);
        return $this;
    }

    /**
     * Draw a circle.
     */
    public function circle(float $cx, float $cy, float $radius, bool $fill = true, bool $stroke = false): self
    {
        $shape = Shape::circle($cx, $cy, $radius);

        if ($fill && $this->fillColor !== null) {
            $shape->fill($this->fillColor);
        }
        if ($stroke && $this->strokeColor !== null) {
            $shape->stroke($this->strokeColor, $this->lineWidth);
        }

        $shape->render($this->stream);
        return $this;
    }

    /**
     * Draw an ellipse.
     */
    public function ellipse(
        float $cx,
        float $cy,
        float $rx,
        float $ry,
        bool $fill = true,
        bool $stroke = false
    ): self {
        $shape = Shape::ellipse($cx, $cy, $rx, $ry);

        if ($fill && $this->fillColor !== null) {
            $shape->fill($this->fillColor);
        }
        if ($stroke && $this->strokeColor !== null) {
            $shape->stroke($this->strokeColor, $this->lineWidth);
        }

        $shape->render($this->stream);
        return $this;
    }

    /**
     * Draw a polygon.
     *
     * @param array<int, array{0: float, 1: float}> $points
     */
    public function polygon(array $points, bool $fill = true, bool $stroke = false): self
    {
        $shape = Shape::polygon($points);

        if ($fill && $this->fillColor !== null) {
            $shape->fill($this->fillColor);
        }
        if ($stroke && $this->strokeColor !== null) {
            $shape->stroke($this->strokeColor, $this->lineWidth);
        }

        $shape->render($this->stream);
        return $this;
    }

    /**
     * Draw a shape.
     */
    public function shape(Shape $shape): self
    {
        $shape->render($this->stream);
        return $this;
    }

    /**
     * Draw a path.
     */
    public function path(Path $path, bool $fill = false, bool $stroke = true): self
    {
        $shape = Shape::fromPath($path);

        if ($fill && $this->fillColor !== null) {
            $shape->fill($this->fillColor);
        }
        if ($stroke && $this->strokeColor !== null) {
            $shape->stroke($this->strokeColor, $this->lineWidth);
        }

        $shape->render($this->stream);
        return $this;
    }

    // Text

    /**
     * Draw text at a position.
     */
    public function text(string $text, float $x, float $y, ?TextStyle $style = null): self
    {
        if ($style === null) {
            $style = new TextStyle($this->font, $this->fontSize, $this->fillColor);
        }

        $textBlock = new TextBlock($text, $style);
        $textBlock->setPosition($x, $y);
        $textBlock->render($this->stream);

        return $this;
    }

    /**
     * Draw a text block.
     */
    public function textBlock(TextBlock $block): self
    {
        $block->render($this->stream);
        return $this;
    }

    // Clipping

    /**
     * Set a rectangular clipping region.
     */
    public function clipRect(float $x, float $y, float $width, float $height): self
    {
        $this->stream->rectangle($x, $y, $width, $height);
        $this->stream->clip();
        $this->stream->endPath();
        return $this;
    }

    /**
     * Set a circular clipping region.
     */
    public function clipCircle(float $cx, float $cy, float $radius): self
    {
        $this->stream->circle($cx, $cy, $radius);
        $this->stream->clip();
        $this->stream->endPath();
        return $this;
    }

    // Images

    /**
     * Draw an image (XObject reference).
     */
    public function image(string $imageName, float $x, float $y, float $width, float $height): self
    {
        $this->stream->drawImage($imageName, $x, $y, $width, $height);
        return $this;
    }

    // Utility

    /**
     * Add raw content to the stream.
     */
    public function raw(string $content): self
    {
        $this->stream->raw($content);
        return $this;
    }

    /**
     * Get the content as string.
     */
    public function toString(): string
    {
        return $this->stream->toString();
    }
}
