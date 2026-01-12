<?php

declare(strict_types=1);

namespace PdfLib\Color;

/**
 * CMYK color (DeviceCMYK color space).
 *
 * Components are stored as floats in range 0-1.
 * Used for professional printing.
 */
final class CmykColor implements Color
{
    private float $cyan;
    private float $magenta;
    private float $yellow;
    private float $black;

    public function __construct(float $cyan, float $magenta, float $yellow, float $black)
    {
        $this->cyan = max(0.0, min(1.0, $cyan));
        $this->magenta = max(0.0, min(1.0, $magenta));
        $this->yellow = max(0.0, min(1.0, $yellow));
        $this->black = max(0.0, min(1.0, $black));
    }

    /**
     * Create from 0-100 percentage values.
     */
    public static function fromPercent(float $cyan, float $magenta, float $yellow, float $black): self
    {
        return new self($cyan / 100, $magenta / 100, $yellow / 100, $black / 100);
    }

    // Named colors (print-optimized)
    public static function black(): self
    {
        return new self(0, 0, 0, 1);
    }

    public static function white(): self
    {
        return new self(0, 0, 0, 0);
    }

    public static function red(): self
    {
        return new self(0, 1, 1, 0);
    }

    public static function green(): self
    {
        return new self(1, 0, 1, 0);
    }

    public static function blue(): self
    {
        return new self(1, 1, 0, 0);
    }

    public static function cyan(): self
    {
        return new self(1, 0, 0, 0);
    }

    public static function magenta(): self
    {
        return new self(0, 1, 0, 0);
    }

    public static function yellow(): self
    {
        return new self(0, 0, 1, 0);
    }

    // Getters
    public function getCyan(): float
    {
        return $this->cyan;
    }

    public function getMagenta(): float
    {
        return $this->magenta;
    }

    public function getYellow(): float
    {
        return $this->yellow;
    }

    public function getBlack(): float
    {
        return $this->black;
    }

    /**
     * Get CMYK values as 0-100 percentages.
     *
     * @return array{cyan: float, magenta: float, yellow: float, black: float}
     */
    public function toPercentArray(): array
    {
        return [
            'cyan' => $this->cyan * 100,
            'magenta' => $this->magenta * 100,
            'yellow' => $this->yellow * 100,
            'black' => $this->black * 100,
        ];
    }

    // Color interface implementation
    public function getColorSpace(): string
    {
        return 'DeviceCMYK';
    }

    public function getComponents(): array
    {
        return [$this->cyan, $this->magenta, $this->yellow, $this->black];
    }

    public function getStrokeOperator(): string
    {
        return sprintf(
            '%.4f %.4f %.4f %.4f K',
            $this->cyan,
            $this->magenta,
            $this->yellow,
            $this->black
        );
    }

    public function getFillOperator(): string
    {
        return sprintf(
            '%.4f %.4f %.4f %.4f k',
            $this->cyan,
            $this->magenta,
            $this->yellow,
            $this->black
        );
    }

    public function toRgb(): RgbColor
    {
        $r = (1 - $this->cyan) * (1 - $this->black);
        $g = (1 - $this->magenta) * (1 - $this->black);
        $b = (1 - $this->yellow) * (1 - $this->black);

        return new RgbColor($r, $g, $b);
    }

    public function toCmyk(): CmykColor
    {
        return $this;
    }

    public function toGray(): GrayColor
    {
        return $this->toRgb()->toGray();
    }

    public function toHex(): string
    {
        return $this->toRgb()->toHex();
    }

    public function equals(Color $other): bool
    {
        $cmyk = $other->toCmyk();
        $epsilon = 0.001;

        return abs($this->cyan - $cmyk->cyan) < $epsilon
            && abs($this->magenta - $cmyk->magenta) < $epsilon
            && abs($this->yellow - $cmyk->yellow) < $epsilon
            && abs($this->black - $cmyk->black) < $epsilon;
    }

    /**
     * Calculate total ink coverage (TAC).
     * Print shops often require TAC < 300%.
     */
    public function getTotalInkCoverage(): float
    {
        return ($this->cyan + $this->magenta + $this->yellow + $this->black) * 100;
    }

    /**
     * Reduce ink coverage to a maximum percentage.
     * Useful for print preparation.
     */
    public function limitInkCoverage(float $maxPercent): self
    {
        $current = $this->getTotalInkCoverage();

        if ($current <= $maxPercent) {
            return $this;
        }

        $factor = $maxPercent / $current;
        return new self(
            $this->cyan * $factor,
            $this->magenta * $factor,
            $this->yellow * $factor,
            $this->black * $factor
        );
    }
}
