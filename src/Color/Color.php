<?php

declare(strict_types=1);

namespace PdfLib\Color;

/**
 * Interface for PDF color values.
 *
 * PDF supports multiple color spaces:
 * - DeviceGray: Single value 0-1 (black to white)
 * - DeviceRGB: Three values 0-1 (red, green, blue)
 * - DeviceCMYK: Four values 0-1 (cyan, magenta, yellow, black)
 */
interface Color
{
    /**
     * Get the color space name for PDF output.
     */
    public function getColorSpace(): string;

    /**
     * Get the color components as array of floats (0-1).
     *
     * @return array<int, float>
     */
    public function getComponents(): array;

    /**
     * Get the PDF operator for setting stroke color.
     */
    public function getStrokeOperator(): string;

    /**
     * Get the PDF operator for setting fill color.
     */
    public function getFillOperator(): string;

    /**
     * Convert to RGB color.
     */
    public function toRgb(): RgbColor;

    /**
     * Convert to CMYK color.
     */
    public function toCmyk(): CmykColor;

    /**
     * Convert to Grayscale.
     */
    public function toGray(): GrayColor;

    /**
     * Get CSS-compatible hex color string.
     */
    public function toHex(): string;

    /**
     * Check if this color equals another.
     */
    public function equals(Color $other): bool;
}
