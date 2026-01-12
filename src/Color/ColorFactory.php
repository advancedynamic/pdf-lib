<?php

declare(strict_types=1);

namespace PdfLib\Color;

/**
 * Factory for creating colors from various formats.
 */
final class ColorFactory
{
    /**
     * CSS named colors (lowercase).
     *
     * @var array<string, string>
     */
    private const NAMED_COLORS = [
        // Basic colors
        'black' => '#000000',
        'white' => '#FFFFFF',
        'red' => '#FF0000',
        'green' => '#008000',
        'blue' => '#0000FF',
        'yellow' => '#FFFF00',
        'cyan' => '#00FFFF',
        'magenta' => '#FF00FF',

        // Extended colors
        'silver' => '#C0C0C0',
        'gray' => '#808080',
        'grey' => '#808080',
        'maroon' => '#800000',
        'olive' => '#808000',
        'lime' => '#00FF00',
        'aqua' => '#00FFFF',
        'teal' => '#008080',
        'navy' => '#000080',
        'fuchsia' => '#FF00FF',
        'purple' => '#800080',

        // Additional web colors
        'orange' => '#FFA500',
        'pink' => '#FFC0CB',
        'brown' => '#A52A2A',
        'coral' => '#FF7F50',
        'crimson' => '#DC143C',
        'darkblue' => '#00008B',
        'darkcyan' => '#008B8B',
        'darkgray' => '#A9A9A9',
        'darkgrey' => '#A9A9A9',
        'darkgreen' => '#006400',
        'darkmagenta' => '#8B008B',
        'darkorange' => '#FF8C00',
        'darkred' => '#8B0000',
        'darkviolet' => '#9400D3',
        'deeppink' => '#FF1493',
        'deepskyblue' => '#00BFFF',
        'dimgray' => '#696969',
        'dimgrey' => '#696969',
        'dodgerblue' => '#1E90FF',
        'firebrick' => '#B22222',
        'forestgreen' => '#228B22',
        'gold' => '#FFD700',
        'goldenrod' => '#DAA520',
        'greenyellow' => '#ADFF2F',
        'honeydew' => '#F0FFF0',
        'hotpink' => '#FF69B4',
        'indianred' => '#CD5C5C',
        'indigo' => '#4B0082',
        'ivory' => '#FFFFF0',
        'khaki' => '#F0E68C',
        'lavender' => '#E6E6FA',
        'lawngreen' => '#7CFC00',
        'lemonchiffon' => '#FFFACD',
        'lightblue' => '#ADD8E6',
        'lightcoral' => '#F08080',
        'lightcyan' => '#E0FFFF',
        'lightgray' => '#D3D3D3',
        'lightgrey' => '#D3D3D3',
        'lightgreen' => '#90EE90',
        'lightpink' => '#FFB6C1',
        'lightsalmon' => '#FFA07A',
        'lightseagreen' => '#20B2AA',
        'lightskyblue' => '#87CEFA',
        'lightslategray' => '#778899',
        'lightslategrey' => '#778899',
        'lightsteelblue' => '#B0C4DE',
        'lightyellow' => '#FFFFE0',
        'limegreen' => '#32CD32',
        'linen' => '#FAF0E6',
        'mediumaquamarine' => '#66CDAA',
        'mediumblue' => '#0000CD',
        'mediumorchid' => '#BA55D3',
        'mediumpurple' => '#9370DB',
        'mediumseagreen' => '#3CB371',
        'mediumslateblue' => '#7B68EE',
        'mediumspringgreen' => '#00FA9A',
        'mediumturquoise' => '#48D1CC',
        'mediumvioletred' => '#C71585',
        'midnightblue' => '#191970',
        'mintcream' => '#F5FFFA',
        'mistyrose' => '#FFE4E1',
        'moccasin' => '#FFE4B5',
        'navajowhite' => '#FFDEAD',
        'oldlace' => '#FDF5E6',
        'olivedrab' => '#6B8E23',
        'orangered' => '#FF4500',
        'orchid' => '#DA70D6',
        'palegoldenrod' => '#EEE8AA',
        'palegreen' => '#98FB98',
        'paleturquoise' => '#AFEEEE',
        'palevioletred' => '#DB7093',
        'papayawhip' => '#FFEFD5',
        'peachpuff' => '#FFDAB9',
        'peru' => '#CD853F',
        'plum' => '#DDA0DD',
        'powderblue' => '#B0E0E6',
        'rosybrown' => '#BC8F8F',
        'royalblue' => '#4169E1',
        'saddlebrown' => '#8B4513',
        'salmon' => '#FA8072',
        'sandybrown' => '#F4A460',
        'seagreen' => '#2E8B57',
        'seashell' => '#FFF5EE',
        'sienna' => '#A0522D',
        'skyblue' => '#87CEEB',
        'slateblue' => '#6A5ACD',
        'slategray' => '#708090',
        'slategrey' => '#708090',
        'snow' => '#FFFAFA',
        'springgreen' => '#00FF7F',
        'steelblue' => '#4682B4',
        'tan' => '#D2B48C',
        'thistle' => '#D8BFD8',
        'tomato' => '#FF6347',
        'turquoise' => '#40E0D0',
        'violet' => '#EE82EE',
        'wheat' => '#F5DEB3',
        'whitesmoke' => '#F5F5F5',
        'yellowgreen' => '#9ACD32',

        // Transparent (special case)
        'transparent' => '#00000000',
    ];

    /**
     * Create a color from various input formats.
     *
     * Supported formats:
     * - Hex: "#FF0000", "FF0000", "#F00"
     * - RGB: "rgb(255, 0, 0)", "rgb(100%, 0%, 0%)"
     * - HSL: "hsl(0, 100%, 50%)"
     * - CMYK: "cmyk(0, 100, 100, 0)"
     * - Named: "red", "blue", "darkgreen"
     * - Gray: "gray(50%)", "gray(128)"
     */
    public static function parse(string $color): Color
    {
        $color = trim($color);
        $lower = strtolower($color);

        // Named color
        if (isset(self::NAMED_COLORS[$lower])) {
            return RgbColor::fromHex(self::NAMED_COLORS[$lower]);
        }

        // Hex color
        if (str_starts_with($color, '#') || preg_match('/^[0-9A-Fa-f]{3,6}$/', $color)) {
            return RgbColor::fromHex($color);
        }

        // RGB
        if (preg_match('/^rgb\s*\(\s*(.+)\s*\)$/i', $color, $matches)) {
            return self::parseRgb($matches[1]);
        }

        // HSL
        if (preg_match('/^hsl\s*\(\s*(.+)\s*\)$/i', $color, $matches)) {
            return self::parseHsl($matches[1]);
        }

        // CMYK
        if (preg_match('/^cmyk\s*\(\s*(.+)\s*\)$/i', $color, $matches)) {
            return self::parseCmyk($matches[1]);
        }

        // Gray
        if (preg_match('/^gray\s*\(\s*(.+)\s*\)$/i', $color, $matches)) {
            return self::parseGray($matches[1]);
        }

        throw new \InvalidArgumentException("Unable to parse color: $color");
    }

    /**
     * Create RGB color from hex string.
     */
    public static function hex(string $hex): RgbColor
    {
        return RgbColor::fromHex($hex);
    }

    /**
     * Create RGB color from 0-255 integer values.
     */
    public static function rgb(int $red, int $green, int $blue): RgbColor
    {
        return RgbColor::fromInt($red, $green, $blue);
    }

    /**
     * Create RGB color from 0-1 float values.
     */
    public static function rgbFloat(float $red, float $green, float $blue): RgbColor
    {
        return new RgbColor($red, $green, $blue);
    }

    /**
     * Create HSL color.
     */
    public static function hsl(float $hue, float $saturation, float $lightness): RgbColor
    {
        return RgbColor::fromHsl($hue, $saturation, $lightness);
    }

    /**
     * Create CMYK color from 0-100 percentage values.
     */
    public static function cmyk(float $cyan, float $magenta, float $yellow, float $black): CmykColor
    {
        return CmykColor::fromPercent($cyan, $magenta, $yellow, $black);
    }

    /**
     * Create CMYK color from 0-1 float values.
     */
    public static function cmykFloat(float $cyan, float $magenta, float $yellow, float $black): CmykColor
    {
        return new CmykColor($cyan, $magenta, $yellow, $black);
    }

    /**
     * Create grayscale color from 0-100 percentage.
     */
    public static function gray(float $percent): GrayColor
    {
        return GrayColor::fromPercent($percent);
    }

    /**
     * Create grayscale color from 0-1 float value.
     */
    public static function grayFloat(float $value): GrayColor
    {
        return new GrayColor($value);
    }

    /**
     * Get a named color.
     */
    public static function named(string $name): RgbColor
    {
        $lower = strtolower($name);

        if (!isset(self::NAMED_COLORS[$lower])) {
            throw new \InvalidArgumentException("Unknown color name: $name");
        }

        return RgbColor::fromHex(self::NAMED_COLORS[$lower]);
    }

    /**
     * Check if a color name exists.
     */
    public static function isNamedColor(string $name): bool
    {
        return isset(self::NAMED_COLORS[strtolower($name)]);
    }

    /**
     * Get all named color names.
     *
     * @return array<int, string>
     */
    public static function getNamedColors(): array
    {
        return array_keys(self::NAMED_COLORS);
    }

    private static function parseRgb(string $values): RgbColor
    {
        $parts = array_map('trim', explode(',', $values));

        if (count($parts) !== 3) {
            throw new \InvalidArgumentException("Invalid RGB format: rgb($values)");
        }

        $r = self::parseColorValue($parts[0], 255);
        $g = self::parseColorValue($parts[1], 255);
        $b = self::parseColorValue($parts[2], 255);

        return new RgbColor($r, $g, $b);
    }

    private static function parseHsl(string $values): RgbColor
    {
        $parts = array_map('trim', explode(',', $values));

        if (count($parts) !== 3) {
            throw new \InvalidArgumentException("Invalid HSL format: hsl($values)");
        }

        $h = (float) $parts[0];
        $s = self::parsePercentage($parts[1]);
        $l = self::parsePercentage($parts[2]);

        return RgbColor::fromHsl($h, $s, $l);
    }

    private static function parseCmyk(string $values): CmykColor
    {
        $parts = array_map('trim', explode(',', $values));

        if (count($parts) !== 4) {
            throw new \InvalidArgumentException("Invalid CMYK format: cmyk($values)");
        }

        $c = self::parsePercentage($parts[0]);
        $m = self::parsePercentage($parts[1]);
        $y = self::parsePercentage($parts[2]);
        $k = self::parsePercentage($parts[3]);

        return CmykColor::fromPercent($c, $m, $y, $k);
    }

    private static function parseGray(string $value): GrayColor
    {
        $value = trim($value);

        if (str_ends_with($value, '%')) {
            return GrayColor::fromPercent((float) rtrim($value, '%'));
        }

        return GrayColor::fromInt((int) $value);
    }

    private static function parseColorValue(string $value, int $max): float
    {
        if (str_ends_with($value, '%')) {
            return ((float) rtrim($value, '%')) / 100;
        }

        return ((float) $value) / $max;
    }

    private static function parsePercentage(string $value): float
    {
        return (float) rtrim(trim($value), '%');
    }
}
