<?php

declare(strict_types=1);

namespace PdfLib\Color;

/**
 * RGB color (DeviceRGB color space).
 *
 * Components are stored as floats in range 0-1.
 */
final class RgbColor implements Color
{
    private float $red;
    private float $green;
    private float $blue;

    public function __construct(float $red, float $green, float $blue)
    {
        $this->red = max(0.0, min(1.0, $red));
        $this->green = max(0.0, min(1.0, $green));
        $this->blue = max(0.0, min(1.0, $blue));
    }

    /**
     * Create from 0-255 integer values.
     */
    public static function fromInt(int $red, int $green, int $blue): self
    {
        return new self($red / 255, $green / 255, $blue / 255);
    }

    /**
     * Create from hex string (e.g., "#FF0000", "FF0000", "#F00").
     */
    public static function fromHex(string $hex): self
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            throw new \InvalidArgumentException("Invalid hex color: $hex");
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));

        return self::fromInt((int) $red, (int) $green, (int) $blue);
    }

    /**
     * Create from HSL values.
     *
     * @param float $hue 0-360
     * @param float $saturation 0-100
     * @param float $lightness 0-100
     */
    public static function fromHsl(float $hue, float $saturation, float $lightness): self
    {
        $h = $hue / 360;
        $s = $saturation / 100;
        $l = $lightness / 100;

        if ($s === 0.0) {
            return new self($l, $l, $l);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = self::hueToRgb($p, $q, $h + 1 / 3);
        $g = self::hueToRgb($p, $q, $h);
        $b = self::hueToRgb($p, $q, $h - 1 / 3);

        return new self($r, $g, $b);
    }

    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }
        return $p;
    }

    // Named colors
    public static function black(): self
    {
        return new self(0, 0, 0);
    }

    public static function white(): self
    {
        return new self(1, 1, 1);
    }

    public static function red(): self
    {
        return new self(1, 0, 0);
    }

    public static function green(): self
    {
        return new self(0, 1, 0);
    }

    public static function blue(): self
    {
        return new self(0, 0, 1);
    }

    public static function yellow(): self
    {
        return new self(1, 1, 0);
    }

    public static function cyan(): self
    {
        return new self(0, 1, 1);
    }

    public static function magenta(): self
    {
        return new self(1, 0, 1);
    }

    public static function gray(float $value = 0.5): self
    {
        return new self($value, $value, $value);
    }

    // Getters
    public function getRed(): float
    {
        return $this->red;
    }

    public function getGreen(): float
    {
        return $this->green;
    }

    public function getBlue(): float
    {
        return $this->blue;
    }

    /**
     * Get RGB values as 0-255 integers.
     *
     * @return array{red: int, green: int, blue: int}
     */
    public function toIntArray(): array
    {
        return [
            'red' => (int) round($this->red * 255),
            'green' => (int) round($this->green * 255),
            'blue' => (int) round($this->blue * 255),
        ];
    }

    // Color interface implementation
    public function getColorSpace(): string
    {
        return 'DeviceRGB';
    }

    public function getComponents(): array
    {
        return [$this->red, $this->green, $this->blue];
    }

    public function getStrokeOperator(): string
    {
        return sprintf(
            '%.4f %.4f %.4f RG',
            $this->red,
            $this->green,
            $this->blue
        );
    }

    public function getFillOperator(): string
    {
        return sprintf(
            '%.4f %.4f %.4f rg',
            $this->red,
            $this->green,
            $this->blue
        );
    }

    public function toRgb(): RgbColor
    {
        return $this;
    }

    public function toCmyk(): CmykColor
    {
        $k = 1 - max($this->red, $this->green, $this->blue);

        if ($k === 1.0) {
            return new CmykColor(0, 0, 0, 1);
        }

        $c = (1 - $this->red - $k) / (1 - $k);
        $m = (1 - $this->green - $k) / (1 - $k);
        $y = (1 - $this->blue - $k) / (1 - $k);

        return new CmykColor($c, $m, $y, $k);
    }

    public function toGray(): GrayColor
    {
        // ITU-R BT.709 luminance formula
        $gray = 0.2126 * $this->red + 0.7152 * $this->green + 0.0722 * $this->blue;
        return new GrayColor($gray);
    }

    public function toHex(): string
    {
        return sprintf(
            '#%02X%02X%02X',
            (int) round($this->red * 255),
            (int) round($this->green * 255),
            (int) round($this->blue * 255)
        );
    }

    public function equals(Color $other): bool
    {
        $rgb = $other->toRgb();
        $epsilon = 0.001;

        return abs($this->red - $rgb->red) < $epsilon
            && abs($this->green - $rgb->green) < $epsilon
            && abs($this->blue - $rgb->blue) < $epsilon;
    }

    /**
     * Lighten the color by a percentage.
     */
    public function lighten(float $percent): self
    {
        $factor = 1 + ($percent / 100);
        return new self(
            min(1, $this->red * $factor),
            min(1, $this->green * $factor),
            min(1, $this->blue * $factor)
        );
    }

    /**
     * Darken the color by a percentage.
     */
    public function darken(float $percent): self
    {
        $factor = 1 - ($percent / 100);
        return new self(
            max(0, $this->red * $factor),
            max(0, $this->green * $factor),
            max(0, $this->blue * $factor)
        );
    }

    /**
     * Get the complementary color.
     */
    public function complement(): self
    {
        return new self(1 - $this->red, 1 - $this->green, 1 - $this->blue);
    }

    /**
     * Mix with another color.
     */
    public function mix(Color $other, float $weight = 0.5): self
    {
        $rgb = $other->toRgb();
        $w = max(0, min(1, $weight));

        return new self(
            $this->red * (1 - $w) + $rgb->red * $w,
            $this->green * (1 - $w) + $rgb->green * $w,
            $this->blue * (1 - $w) + $rgb->blue * $w
        );
    }
}
