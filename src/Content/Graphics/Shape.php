<?php

declare(strict_types=1);

namespace PdfLib\Content\Graphics;

use PdfLib\Color\Color;
use PdfLib\Color\RgbColor;
use PdfLib\Content\ContentStream;

/**
 * A shape with stroke and fill properties.
 */
final class Shape
{
    private Path $path;
    private ?Color $fillColor = null;
    private ?Color $strokeColor = null;
    private float $strokeWidth = 1.0;
    private int $lineCap = 0;
    private int $lineJoin = 0;
    private float $miterLimit = 10.0;
    /** @var array<int, float> */
    private array $dashPattern = [];
    private float $dashPhase = 0.0;
    private bool $evenOddFill = false;

    public const CAP_BUTT = 0;
    public const CAP_ROUND = 1;
    public const CAP_SQUARE = 2;

    public const JOIN_MITER = 0;
    public const JOIN_ROUND = 1;
    public const JOIN_BEVEL = 2;

    public function __construct(Path $path)
    {
        $this->path = $path;
    }

    /**
     * Create a new shape from a path.
     */
    public static function fromPath(Path $path): self
    {
        return new self($path);
    }

    // Factory methods for common shapes

    /**
     * Create a rectangle.
     */
    public static function rectangle(float $x, float $y, float $width, float $height): self
    {
        $path = Path::create()
            ->moveTo($x, $y)
            ->lineTo($x + $width, $y)
            ->lineTo($x + $width, $y + $height)
            ->lineTo($x, $y + $height)
            ->close();

        return new self($path);
    }

    /**
     * Create a rounded rectangle.
     */
    public static function roundedRectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        float $radius
    ): self {
        $radius = min($radius, $width / 2, $height / 2);

        $path = Path::create()
            ->moveTo($x + $radius, $y)
            ->lineTo($x + $width - $radius, $y)
            ->arc($x + $width - $radius, $y + $radius, $radius, -90, 0)
            ->lineTo($x + $width, $y + $height - $radius)
            ->arc($x + $width - $radius, $y + $height - $radius, $radius, 0, 90)
            ->lineTo($x + $radius, $y + $height)
            ->arc($x + $radius, $y + $height - $radius, $radius, 90, 180)
            ->lineTo($x, $y + $radius)
            ->arc($x + $radius, $y + $radius, $radius, 180, 270)
            ->close();

        return new self($path);
    }

    /**
     * Create a circle.
     */
    public static function circle(float $cx, float $cy, float $radius): self
    {
        return self::ellipse($cx, $cy, $radius, $radius);
    }

    /**
     * Create an ellipse.
     */
    public static function ellipse(float $cx, float $cy, float $rx, float $ry): self
    {
        $kappa = 0.5522847498;
        $ox = $rx * $kappa;
        $oy = $ry * $kappa;

        $path = Path::create()
            ->moveTo($cx - $rx, $cy)
            ->curveTo($cx - $rx, $cy + $oy, $cx - $ox, $cy + $ry, $cx, $cy + $ry)
            ->curveTo($cx + $ox, $cy + $ry, $cx + $rx, $cy + $oy, $cx + $rx, $cy)
            ->curveTo($cx + $rx, $cy - $oy, $cx + $ox, $cy - $ry, $cx, $cy - $ry)
            ->curveTo($cx - $ox, $cy - $ry, $cx - $rx, $cy - $oy, $cx - $rx, $cy)
            ->close();

        return new self($path);
    }

    /**
     * Create a line.
     */
    public static function line(float $x1, float $y1, float $x2, float $y2): self
    {
        $path = Path::create()
            ->moveTo($x1, $y1)
            ->lineTo($x2, $y2);

        return (new self($path))->stroke(RgbColor::black());
    }

    /**
     * Create a polygon.
     *
     * @param array<int, array{0: float, 1: float}> $points Array of [x, y] points
     */
    public static function polygon(array $points): self
    {
        if (count($points) < 3) {
            throw new \InvalidArgumentException('Polygon requires at least 3 points');
        }

        $path = Path::create()->moveTo($points[0][0], $points[0][1]);

        for ($i = 1; $i < count($points); $i++) {
            $path->lineTo($points[$i][0], $points[$i][1]);
        }

        $path->close();

        return new self($path);
    }

    /**
     * Create a regular polygon (equilateral).
     */
    public static function regularPolygon(float $cx, float $cy, float $radius, int $sides): self
    {
        if ($sides < 3) {
            throw new \InvalidArgumentException('Polygon requires at least 3 sides');
        }

        $points = [];
        $angleStep = 2 * M_PI / $sides;
        $startAngle = -M_PI / 2; // Start at top

        for ($i = 0; $i < $sides; $i++) {
            $angle = $startAngle + $i * $angleStep;
            $points[] = [
                $cx + $radius * cos($angle),
                $cy + $radius * sin($angle),
            ];
        }

        return self::polygon($points);
    }

    /**
     * Create a star.
     */
    public static function star(
        float $cx,
        float $cy,
        float $outerRadius,
        float $innerRadius,
        int $points
    ): self {
        if ($points < 3) {
            throw new \InvalidArgumentException('Star requires at least 3 points');
        }

        $vertices = [];
        $angleStep = M_PI / $points;
        $startAngle = -M_PI / 2;

        for ($i = 0; $i < $points * 2; $i++) {
            $angle = $startAngle + $i * $angleStep;
            $radius = $i % 2 === 0 ? $outerRadius : $innerRadius;
            $vertices[] = [
                $cx + $radius * cos($angle),
                $cy + $radius * sin($angle),
            ];
        }

        return self::polygon($vertices);
    }

    // Getters

    public function getPath(): Path
    {
        return $this->path;
    }

    public function getFillColor(): ?Color
    {
        return $this->fillColor;
    }

    public function getStrokeColor(): ?Color
    {
        return $this->strokeColor;
    }

    public function getStrokeWidth(): float
    {
        return $this->strokeWidth;
    }

    // Fluent setters

    public function fill(?Color $color): self
    {
        $this->fillColor = $color;
        return $this;
    }

    public function stroke(?Color $color, float $width = 1.0): self
    {
        $this->strokeColor = $color;
        $this->strokeWidth = $width;
        return $this;
    }

    public function setStrokeWidth(float $width): self
    {
        $this->strokeWidth = $width;
        return $this;
    }

    public function setLineCap(int $cap): self
    {
        $this->lineCap = $cap;
        return $this;
    }

    public function setLineJoin(int $join): self
    {
        $this->lineJoin = $join;
        return $this;
    }

    public function setMiterLimit(float $limit): self
    {
        $this->miterLimit = $limit;
        return $this;
    }

    /**
     * Set dash pattern.
     *
     * @param array<int, float> $pattern
     */
    public function setDashPattern(array $pattern, float $phase = 0.0): self
    {
        $this->dashPattern = $pattern;
        $this->dashPhase = $phase;
        return $this;
    }

    public function setEvenOddFill(bool $evenOdd): self
    {
        $this->evenOddFill = $evenOdd;
        return $this;
    }

    /**
     * Render the shape to a content stream.
     */
    public function render(ContentStream $stream): void
    {
        $hasFill = $this->fillColor !== null;
        $hasStroke = $this->strokeColor !== null;

        if (!$hasFill && !$hasStroke) {
            return;
        }

        $stream->saveState();

        // Set stroke properties
        if ($hasStroke) {
            $stream->setStrokeColor($this->strokeColor);
            $stream->setLineWidth($this->strokeWidth);
            $stream->setLineCap($this->lineCap);
            $stream->setLineJoin($this->lineJoin);
            $stream->setMiterLimit($this->miterLimit);

            if (count($this->dashPattern) > 0) {
                $stream->setDashPattern($this->dashPattern, $this->dashPhase);
            }
        }

        // Set fill color
        if ($hasFill) {
            $stream->setFillColor($this->fillColor);
        }

        // Apply path
        $this->path->applyTo($stream);

        // Paint
        if ($hasFill && $hasStroke) {
            if ($this->evenOddFill) {
                $stream->fillAndStrokeEvenOdd();
            } else {
                $stream->fillAndStroke();
            }
        } elseif ($hasFill) {
            if ($this->evenOddFill) {
                $stream->fillEvenOdd();
            } else {
                $stream->fill();
            }
        } else {
            $stream->stroke();
        }

        $stream->restoreState();
    }
}
