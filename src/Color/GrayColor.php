<?php

declare(strict_types=1);

namespace PdfLib\Color;

/**
 * Grayscale color (DeviceGray color space).
 *
 * Value is stored as float in range 0-1 (0=black, 1=white).
 */
final class GrayColor implements Color
{
    private float $gray;

    public function __construct(float $gray)
    {
        $this->gray = max(0.0, min(1.0, $gray));
    }

    /**
     * Create from 0-255 integer value.
     */
    public static function fromInt(int $gray): self
    {
        return new self($gray / 255);
    }

    /**
     * Create from 0-100 percentage.
     */
    public static function fromPercent(float $percent): self
    {
        return new self($percent / 100);
    }

    // Named grays
    public static function black(): self
    {
        return new self(0);
    }

    public static function white(): self
    {
        return new self(1);
    }

    public static function darkGray(): self
    {
        return new self(0.25);
    }

    public static function mediumGray(): self
    {
        return new self(0.5);
    }

    public static function lightGray(): self
    {
        return new self(0.75);
    }

    // Getter
    public function getGray(): float
    {
        return $this->gray;
    }

    /**
     * Get as 0-255 integer.
     */
    public function toInt(): int
    {
        return (int) round($this->gray * 255);
    }

    /**
     * Get as 0-100 percentage.
     */
    public function toPercent(): float
    {
        return $this->gray * 100;
    }

    // Color interface implementation
    public function getColorSpace(): string
    {
        return 'DeviceGray';
    }

    public function getComponents(): array
    {
        return [$this->gray];
    }

    public function getStrokeOperator(): string
    {
        return sprintf('%.4f G', $this->gray);
    }

    public function getFillOperator(): string
    {
        return sprintf('%.4f g', $this->gray);
    }

    public function toRgb(): RgbColor
    {
        return new RgbColor($this->gray, $this->gray, $this->gray);
    }

    public function toCmyk(): CmykColor
    {
        return new CmykColor(0, 0, 0, 1 - $this->gray);
    }

    public function toGray(): GrayColor
    {
        return $this;
    }

    public function toHex(): string
    {
        $value = (int) round($this->gray * 255);
        return sprintf('#%02X%02X%02X', $value, $value, $value);
    }

    public function equals(Color $other): bool
    {
        $gray = $other->toGray();
        return abs($this->gray - $gray->gray) < 0.001;
    }

    /**
     * Lighten the gray by a percentage.
     */
    public function lighten(float $percent): self
    {
        $amount = $percent / 100;
        return new self(min(1, $this->gray + (1 - $this->gray) * $amount));
    }

    /**
     * Darken the gray by a percentage.
     */
    public function darken(float $percent): self
    {
        $amount = $percent / 100;
        return new self(max(0, $this->gray * (1 - $amount)));
    }

    /**
     * Invert the gray value.
     */
    public function invert(): self
    {
        return new self(1 - $this->gray);
    }
}
