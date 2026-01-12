<?php

declare(strict_types=1);

namespace PdfLib\Content\Graphics;

use PdfLib\Content\ContentStream;

/**
 * Represents a vector path that can be stroked or filled.
 */
final class Path
{
    /** @var array<int, array{op: string, args: array<int, float>}> */
    private array $operations = [];

    private bool $closed = false;

    /**
     * Create a new path.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Move to a point (start a new subpath).
     */
    public function moveTo(float $x, float $y): self
    {
        $this->operations[] = ['op' => 'm', 'args' => [$x, $y]];
        return $this;
    }

    /**
     * Draw a line to a point.
     */
    public function lineTo(float $x, float $y): self
    {
        $this->operations[] = ['op' => 'l', 'args' => [$x, $y]];
        return $this;
    }

    /**
     * Draw a cubic Bezier curve.
     */
    public function curveTo(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3
    ): self {
        $this->operations[] = ['op' => 'c', 'args' => [$x1, $y1, $x2, $y2, $x3, $y3]];
        return $this;
    }

    /**
     * Draw a quadratic Bezier curve (converted to cubic).
     */
    public function quadraticCurveTo(float $cpx, float $cpy, float $x, float $y): self
    {
        // Get current point (last operation's endpoint)
        $lastOp = end($this->operations);
        if ($lastOp === false) {
            throw new \LogicException('Cannot draw curve without a starting point');
        }

        $args = $lastOp['args'];
        $x0 = $args[count($args) - 2];
        $y0 = $args[count($args) - 1];

        // Convert quadratic to cubic Bezier
        $cp1x = $x0 + 2 / 3 * ($cpx - $x0);
        $cp1y = $y0 + 2 / 3 * ($cpy - $y0);
        $cp2x = $x + 2 / 3 * ($cpx - $x);
        $cp2y = $y + 2 / 3 * ($cpy - $y);

        return $this->curveTo($cp1x, $cp1y, $cp2x, $cp2y, $x, $y);
    }

    /**
     * Draw an arc.
     */
    public function arc(
        float $cx,
        float $cy,
        float $radius,
        float $startAngle,
        float $endAngle,
        bool $counterClockwise = false
    ): self {
        return $this->ellipticArc($cx, $cy, $radius, $radius, $startAngle, $endAngle, $counterClockwise);
    }

    /**
     * Draw an elliptic arc.
     */
    public function ellipticArc(
        float $cx,
        float $cy,
        float $rx,
        float $ry,
        float $startAngle,
        float $endAngle,
        bool $counterClockwise = false
    ): self {
        if ($counterClockwise) {
            [$startAngle, $endAngle] = [$endAngle, $startAngle];
        }

        // Normalize angles
        $startRad = deg2rad($startAngle);
        $endRad = deg2rad($endAngle);

        // Start point
        $x0 = $cx + $rx * cos($startRad);
        $y0 = $cy + $ry * sin($startRad);

        // Move to start if this is the first operation
        if (count($this->operations) === 0) {
            $this->moveTo($x0, $y0);
        } else {
            $this->lineTo($x0, $y0);
        }

        // Draw arc using cubic Bezier segments
        $angleRange = $endRad - $startRad;
        $numSegments = (int) ceil(abs($angleRange) / (M_PI / 2));
        $segmentAngle = $angleRange / $numSegments;

        for ($i = 0; $i < $numSegments; $i++) {
            $theta1 = $startRad + $i * $segmentAngle;
            $theta2 = $theta1 + $segmentAngle;

            $this->addArcSegment($cx, $cy, $rx, $ry, $theta1, $theta2);
        }

        return $this;
    }

    /**
     * Add a single arc segment as a cubic Bezier curve.
     */
    private function addArcSegment(
        float $cx,
        float $cy,
        float $rx,
        float $ry,
        float $theta1,
        float $theta2
    ): void {
        $dtheta = $theta2 - $theta1;
        $t = tan($dtheta / 2);
        $alpha = sin($dtheta) * (sqrt(4 + 3 * $t * $t) - 1) / 3;

        $x1 = $cx + $rx * cos($theta1);
        $y1 = $cy + $ry * sin($theta1);
        $x2 = $cx + $rx * cos($theta2);
        $y2 = $cy + $ry * sin($theta2);

        $cp1x = $x1 - $alpha * $rx * sin($theta1);
        $cp1y = $y1 + $alpha * $ry * cos($theta1);
        $cp2x = $x2 + $alpha * $rx * sin($theta2);
        $cp2y = $y2 - $alpha * $ry * cos($theta2);

        $this->curveTo($cp1x, $cp1y, $cp2x, $cp2y, $x2, $y2);
    }

    /**
     * Close the current subpath.
     */
    public function close(): self
    {
        $this->closed = true;
        return $this;
    }

    /**
     * Check if the path is closed.
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Get the operations.
     *
     * @return array<int, array{op: string, args: array<int, float>}>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Apply the path to a content stream (without stroking or filling).
     */
    public function applyTo(ContentStream $stream): void
    {
        foreach ($this->operations as $operation) {
            match ($operation['op']) {
                'm' => $stream->moveTo($operation['args'][0], $operation['args'][1]),
                'l' => $stream->lineTo($operation['args'][0], $operation['args'][1]),
                'c' => $stream->curveTo(
                    $operation['args'][0],
                    $operation['args'][1],
                    $operation['args'][2],
                    $operation['args'][3],
                    $operation['args'][4],
                    $operation['args'][5]
                ),
                default => null,
            };
        }

        if ($this->closed) {
            $stream->closePath();
        }
    }

    /**
     * Get the bounding box of the path.
     *
     * @return array{x: float, y: float, width: float, height: float}|null
     */
    public function getBoundingBox(): ?array
    {
        if (count($this->operations) === 0) {
            return null;
        }

        $minX = $minY = PHP_FLOAT_MAX;
        $maxX = $maxY = PHP_FLOAT_MIN;

        foreach ($this->operations as $operation) {
            $args = $operation['args'];
            for ($i = 0; $i < count($args); $i += 2) {
                $minX = min($minX, $args[$i]);
                $maxX = max($maxX, $args[$i]);
                $minY = min($minY, $args[$i + 1]);
                $maxY = max($maxY, $args[$i + 1]);
            }
        }

        return [
            'x' => $minX,
            'y' => $minY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
        ];
    }

    /**
     * Translate the path.
     */
    public function translate(float $dx, float $dy): self
    {
        $clone = clone $this;
        foreach ($clone->operations as &$operation) {
            for ($i = 0; $i < count($operation['args']); $i += 2) {
                $operation['args'][$i] += $dx;
                $operation['args'][$i + 1] += $dy;
            }
        }
        return $clone;
    }

    /**
     * Scale the path.
     */
    public function scale(float $sx, float $sy, float $cx = 0, float $cy = 0): self
    {
        $clone = clone $this;
        foreach ($clone->operations as &$operation) {
            for ($i = 0; $i < count($operation['args']); $i += 2) {
                $operation['args'][$i] = $cx + ($operation['args'][$i] - $cx) * $sx;
                $operation['args'][$i + 1] = $cy + ($operation['args'][$i + 1] - $cy) * $sy;
            }
        }
        return $clone;
    }
}
