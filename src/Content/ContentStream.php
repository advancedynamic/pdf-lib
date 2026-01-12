<?php

declare(strict_types=1);

namespace PdfLib\Content;

use PdfLib\Color\Color;
use PdfLib\Font\Font;

/**
 * PDF content stream builder.
 *
 * Builds PDF content stream operators for graphics, text, and images.
 * All coordinates use PDF coordinate system (origin at bottom-left).
 */
final class ContentStream
{
    /** @var array<int, string> */
    private array $operations = [];

    /** @var array<string, Font> */
    private array $fonts = [];

    /** @var array<string, mixed> */
    private array $images = [];

    private int $fontIndex = 0;
    private int $imageIndex = 0;

    /**
     * Get the content stream string.
     */
    public function toString(): string
    {
        return implode("\n", $this->operations);
    }

    /**
     * Get the content stream as bytes.
     */
    public function getBytes(): string
    {
        return $this->toString();
    }

    /**
     * Get registered fonts.
     *
     * @return array<string, Font>
     */
    public function getFonts(): array
    {
        return $this->fonts;
    }

    /**
     * Get registered images.
     *
     * @return array<string, mixed>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * Register a font and get its resource name.
     */
    public function registerFont(Font $font): string
    {
        $name = $font->getName();

        // Check if already registered
        foreach ($this->fonts as $resourceName => $registeredFont) {
            if ($registeredFont->getName() === $name) {
                return $resourceName;
            }
        }

        $resourceName = 'F' . (++$this->fontIndex);
        $this->fonts[$resourceName] = $font;

        return $resourceName;
    }

    /**
     * Register an image and get its resource name.
     *
     * @param mixed $image Image data
     */
    public function registerImage(mixed $image): string
    {
        $resourceName = 'Im' . (++$this->imageIndex);
        $this->images[$resourceName] = $image;
        return $resourceName;
    }

    // Graphics State Operators

    /**
     * Save graphics state (q).
     */
    public function saveState(): self
    {
        $this->operations[] = 'q';
        return $this;
    }

    /**
     * Restore graphics state (Q).
     */
    public function restoreState(): self
    {
        $this->operations[] = 'Q';
        return $this;
    }

    /**
     * Set current transformation matrix (cm).
     */
    public function transform(float $a, float $b, float $c, float $d, float $e, float $f): self
    {
        $this->operations[] = sprintf(
            '%.4f %.4f %.4f %.4f %.4f %.4f cm',
            $a,
            $b,
            $c,
            $d,
            $e,
            $f
        );
        return $this;
    }

    /**
     * Apply translation.
     */
    public function translate(float $x, float $y): self
    {
        return $this->transform(1, 0, 0, 1, $x, $y);
    }

    /**
     * Apply scaling.
     */
    public function scale(float $sx, float $sy): self
    {
        return $this->transform($sx, 0, 0, $sy, 0, 0);
    }

    /**
     * Apply rotation (degrees).
     */
    public function rotate(float $angle): self
    {
        $rad = deg2rad($angle);
        $cos = cos($rad);
        $sin = sin($rad);
        return $this->transform($cos, $sin, -$sin, $cos, 0, 0);
    }

    /**
     * Set line width (w).
     */
    public function setLineWidth(float $width): self
    {
        $this->operations[] = sprintf('%.4f w', $width);
        return $this;
    }

    /**
     * Set line cap style (J).
     * 0 = butt, 1 = round, 2 = square
     */
    public function setLineCap(int $style): self
    {
        $this->operations[] = "$style J";
        return $this;
    }

    /**
     * Set line join style (j).
     * 0 = miter, 1 = round, 2 = bevel
     */
    public function setLineJoin(int $style): self
    {
        $this->operations[] = "$style j";
        return $this;
    }

    /**
     * Set miter limit (M).
     */
    public function setMiterLimit(float $limit): self
    {
        $this->operations[] = sprintf('%.4f M', $limit);
        return $this;
    }

    /**
     * Set dash pattern (d).
     *
     * @param array<int, float> $pattern Dash pattern
     * @param float $phase Phase offset
     */
    public function setDashPattern(array $pattern, float $phase = 0): self
    {
        $patternStr = '[' . implode(' ', array_map(
            fn($v) => sprintf('%.4f', $v),
            $pattern
        )) . ']';
        $this->operations[] = sprintf('%s %.4f d', $patternStr, $phase);
        return $this;
    }

    /**
     * Set solid line (no dash).
     */
    public function setSolidLine(): self
    {
        $this->operations[] = '[] 0 d';
        return $this;
    }

    // Color Operators

    /**
     * Set stroke color.
     */
    public function setStrokeColor(Color $color): self
    {
        $this->operations[] = $color->getStrokeOperator();
        return $this;
    }

    /**
     * Set fill color.
     */
    public function setFillColor(Color $color): self
    {
        $this->operations[] = $color->getFillOperator();
        return $this;
    }

    // Path Construction Operators

    /**
     * Move to point (m).
     */
    public function moveTo(float $x, float $y): self
    {
        $this->operations[] = sprintf('%.4f %.4f m', $x, $y);
        return $this;
    }

    /**
     * Line to point (l).
     */
    public function lineTo(float $x, float $y): self
    {
        $this->operations[] = sprintf('%.4f %.4f l', $x, $y);
        return $this;
    }

    /**
     * Cubic Bezier curve (c).
     */
    public function curveTo(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3
    ): self {
        $this->operations[] = sprintf(
            '%.4f %.4f %.4f %.4f %.4f %.4f c',
            $x1,
            $y1,
            $x2,
            $y2,
            $x3,
            $y3
        );
        return $this;
    }

    /**
     * Cubic Bezier curve with first control point on current point (v).
     */
    public function curveToV(float $x2, float $y2, float $x3, float $y3): self
    {
        $this->operations[] = sprintf('%.4f %.4f %.4f %.4f v', $x2, $y2, $x3, $y3);
        return $this;
    }

    /**
     * Cubic Bezier curve with second control point on end point (y).
     */
    public function curveToY(float $x1, float $y1, float $x3, float $y3): self
    {
        $this->operations[] = sprintf('%.4f %.4f %.4f %.4f y', $x1, $y1, $x3, $y3);
        return $this;
    }

    /**
     * Close subpath (h).
     */
    public function closePath(): self
    {
        $this->operations[] = 'h';
        return $this;
    }

    /**
     * Rectangle (re).
     */
    public function rectangle(float $x, float $y, float $width, float $height): self
    {
        $this->operations[] = sprintf('%.4f %.4f %.4f %.4f re', $x, $y, $width, $height);
        return $this;
    }

    // Path Painting Operators

    /**
     * Stroke path (S).
     */
    public function stroke(): self
    {
        $this->operations[] = 'S';
        return $this;
    }

    /**
     * Close and stroke path (s).
     */
    public function closeAndStroke(): self
    {
        $this->operations[] = 's';
        return $this;
    }

    /**
     * Fill path using nonzero winding rule (f).
     */
    public function fill(): self
    {
        $this->operations[] = 'f';
        return $this;
    }

    /**
     * Fill path using even-odd rule (f*).
     */
    public function fillEvenOdd(): self
    {
        $this->operations[] = 'f*';
        return $this;
    }

    /**
     * Fill and stroke path using nonzero winding rule (B).
     */
    public function fillAndStroke(): self
    {
        $this->operations[] = 'B';
        return $this;
    }

    /**
     * Fill and stroke path using even-odd rule (B*).
     */
    public function fillAndStrokeEvenOdd(): self
    {
        $this->operations[] = 'B*';
        return $this;
    }

    /**
     * Close, fill, and stroke path (b).
     */
    public function closeFillAndStroke(): self
    {
        $this->operations[] = 'b';
        return $this;
    }

    /**
     * End path without filling or stroking (n).
     */
    public function endPath(): self
    {
        $this->operations[] = 'n';
        return $this;
    }

    // Clipping Path Operators

    /**
     * Set clipping path using nonzero winding rule (W).
     */
    public function clip(): self
    {
        $this->operations[] = 'W';
        return $this;
    }

    /**
     * Set clipping path using even-odd rule (W*).
     */
    public function clipEvenOdd(): self
    {
        $this->operations[] = 'W*';
        return $this;
    }

    // Text Operators

    /**
     * Begin text object (BT).
     */
    public function beginText(): self
    {
        $this->operations[] = 'BT';
        return $this;
    }

    /**
     * End text object (ET).
     */
    public function endText(): self
    {
        $this->operations[] = 'ET';
        return $this;
    }

    /**
     * Set text font and size (Tf).
     */
    public function setFont(string $fontName, float $size): self
    {
        $this->operations[] = sprintf('/%s %.4f Tf', $fontName, $size);
        return $this;
    }

    /**
     * Set text matrix (Tm).
     */
    public function setTextMatrix(float $a, float $b, float $c, float $d, float $e, float $f): self
    {
        $this->operations[] = sprintf(
            '%.4f %.4f %.4f %.4f %.4f %.4f Tm',
            $a,
            $b,
            $c,
            $d,
            $e,
            $f
        );
        return $this;
    }

    /**
     * Move text position (Td).
     */
    public function moveTextPosition(float $tx, float $ty): self
    {
        $this->operations[] = sprintf('%.4f %.4f Td', $tx, $ty);
        return $this;
    }

    /**
     * Move text position and set leading (TD).
     */
    public function moveTextPositionWithLeading(float $tx, float $ty): self
    {
        $this->operations[] = sprintf('%.4f %.4f TD', $tx, $ty);
        return $this;
    }

    /**
     * Move to start of next line (T*).
     */
    public function nextLine(): self
    {
        $this->operations[] = 'T*';
        return $this;
    }

    /**
     * Set character spacing (Tc).
     */
    public function setCharacterSpacing(float $spacing): self
    {
        $this->operations[] = sprintf('%.4f Tc', $spacing);
        return $this;
    }

    /**
     * Set word spacing (Tw).
     */
    public function setWordSpacing(float $spacing): self
    {
        $this->operations[] = sprintf('%.4f Tw', $spacing);
        return $this;
    }

    /**
     * Set horizontal scaling (Tz).
     */
    public function setHorizontalScaling(float $scale): self
    {
        $this->operations[] = sprintf('%.4f Tz', $scale);
        return $this;
    }

    /**
     * Set text leading (TL).
     */
    public function setTextLeading(float $leading): self
    {
        $this->operations[] = sprintf('%.4f TL', $leading);
        return $this;
    }

    /**
     * Set text rise (Ts).
     */
    public function setTextRise(float $rise): self
    {
        $this->operations[] = sprintf('%.4f Ts', $rise);
        return $this;
    }

    /**
     * Set text rendering mode (Tr).
     * 0=fill, 1=stroke, 2=fill+stroke, 3=invisible, 4-7=clip variants
     */
    public function setTextRenderingMode(int $mode): self
    {
        $this->operations[] = "$mode Tr";
        return $this;
    }

    /**
     * Show text string (Tj).
     */
    public function showText(string $text): self
    {
        $escaped = $this->escapeString($text);
        $this->operations[] = "($escaped) Tj";
        return $this;
    }

    /**
     * Show text with individual character positioning (TJ).
     *
     * @param array<int, string|float> $array Array of strings and position adjustments
     */
    public function showTextWithPositioning(array $array): self
    {
        $parts = [];
        foreach ($array as $item) {
            if (is_string($item)) {
                $parts[] = '(' . $this->escapeString($item) . ')';
            } else {
                $parts[] = sprintf('%.4f', $item);
            }
        }
        $this->operations[] = '[' . implode(' ', $parts) . '] TJ';
        return $this;
    }

    /**
     * Show text on next line (').
     */
    public function showTextOnNextLine(string $text): self
    {
        $escaped = $this->escapeString($text);
        $this->operations[] = "($escaped) '";
        return $this;
    }

    // XObject (Image) Operators

    /**
     * Draw XObject (Do).
     */
    public function drawXObject(string $name): self
    {
        $this->operations[] = "/$name Do";
        return $this;
    }

    /**
     * Draw image at position with size.
     */
    public function drawImage(string $name, float $x, float $y, float $width, float $height): self
    {
        $this->saveState();
        $this->transform($width, 0, 0, $height, $x, $y);
        $this->drawXObject($name);
        $this->restoreState();
        return $this;
    }

    // Convenience methods

    /**
     * Draw a line.
     */
    public function line(float $x1, float $y1, float $x2, float $y2): self
    {
        return $this->moveTo($x1, $y1)->lineTo($x2, $y2)->stroke();
    }

    /**
     * Draw a stroked rectangle.
     */
    public function strokeRect(float $x, float $y, float $width, float $height): self
    {
        return $this->rectangle($x, $y, $width, $height)->stroke();
    }

    /**
     * Draw a filled rectangle.
     */
    public function fillRect(float $x, float $y, float $width, float $height): self
    {
        return $this->rectangle($x, $y, $width, $height)->fill();
    }

    /**
     * Draw an ellipse (approximated with Bezier curves).
     */
    public function ellipse(float $cx, float $cy, float $rx, float $ry): self
    {
        $kappa = 0.5522847498; // 4 * (sqrt(2) - 1) / 3

        $ox = $rx * $kappa;
        $oy = $ry * $kappa;

        $this->moveTo($cx - $rx, $cy);
        $this->curveTo($cx - $rx, $cy + $oy, $cx - $ox, $cy + $ry, $cx, $cy + $ry);
        $this->curveTo($cx + $ox, $cy + $ry, $cx + $rx, $cy + $oy, $cx + $rx, $cy);
        $this->curveTo($cx + $rx, $cy - $oy, $cx + $ox, $cy - $ry, $cx, $cy - $ry);
        $this->curveTo($cx - $ox, $cy - $ry, $cx - $rx, $cy - $oy, $cx - $rx, $cy);

        return $this;
    }

    /**
     * Draw a circle.
     */
    public function circle(float $cx, float $cy, float $radius): self
    {
        return $this->ellipse($cx, $cy, $radius, $radius);
    }

    /**
     * Draw text at a position.
     */
    public function text(string $text, float $x, float $y): self
    {
        return $this
            ->beginText()
            ->setTextMatrix(1, 0, 0, 1, $x, $y)
            ->showText($text)
            ->endText();
    }

    /**
     * Add raw content.
     */
    public function raw(string $content): self
    {
        $this->operations[] = $content;
        return $this;
    }

    /**
     * Escape special characters in a PDF string.
     */
    private function escapeString(string $text): string
    {
        return strtr($text, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
            "\r" => '\\r',
            "\n" => '\\n',
            "\t" => '\\t',
        ]);
    }
}
