<?php

declare(strict_types=1);

namespace PdfLib\Html;

/**
 * Represents a laid-out element with position and dimensions.
 */
class LayoutElement
{
    private string $type;
    private string $content;
    private float $x;
    private float $y;
    private float $width;
    private float $height;

    // Text properties
    private string $fontFamily = 'Helvetica';
    private float $fontSize = 12;
    private bool $bold = false;
    private bool $italic = false;
    private bool $underline = false;

    /** @var array{float, float, float}|null RGB color */
    private ?array $color = null;

    /** @var array{float, float, float}|null RGB background color */
    private ?array $backgroundColor = null;

    /** @var array{float, float, float}|null RGB border color */
    private ?array $borderColor = null;

    private float $lineWidth = 1;

    /** @var array<string, mixed> */
    private array $extra = [];

    public function __construct(
        string $type,
        string $content = '',
        float $x = 0,
        float $y = 0,
        float $width = 0,
        float $height = 0
    ) {
        $this->type = $type;
        $this->content = $content;
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Create a text element.
     */
    public static function text(string $content, float $x, float $y): self
    {
        return new self('text', $content, $x, $y);
    }

    /**
     * Create an image element.
     */
    public static function image(string $src, float $x, float $y, float $width, float $height): self
    {
        return new self('image', $src, $x, $y, $width, $height);
    }

    /**
     * Create a line element.
     */
    public static function line(float $x, float $y, float $width): self
    {
        return new self('line', '', $x, $y, $width, 0);
    }

    /**
     * Create a rectangle element.
     */
    public static function rectangle(float $x, float $y, float $width, float $height): self
    {
        return new self('rectangle', '', $x, $y, $width, $height);
    }

    /**
     * Get element type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set content.
     */
    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get X position.
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * Set X position.
     */
    public function setX(float $x): self
    {
        $this->x = $x;

        return $this;
    }

    /**
     * Get Y position.
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * Set Y position.
     */
    public function setY(float $y): self
    {
        $this->y = $y;

        return $this;
    }

    /**
     * Get width.
     */
    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * Set width.
     */
    public function setWidth(float $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Get height.
     */
    public function getHeight(): float
    {
        return $this->height;
    }

    /**
     * Set height.
     */
    public function setHeight(float $height): self
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Get font family.
     */
    public function getFontFamily(): string
    {
        return $this->fontFamily;
    }

    /**
     * Set font family.
     */
    public function setFontFamily(string $fontFamily): self
    {
        $this->fontFamily = $fontFamily;

        return $this;
    }

    /**
     * Get font size.
     */
    public function getFontSize(): float
    {
        return $this->fontSize;
    }

    /**
     * Set font size.
     */
    public function setFontSize(float $fontSize): self
    {
        $this->fontSize = $fontSize;

        return $this;
    }

    /**
     * Check if bold.
     */
    public function isBold(): bool
    {
        return $this->bold;
    }

    /**
     * Set bold.
     */
    public function setBold(bool $bold): self
    {
        $this->bold = $bold;

        return $this;
    }

    /**
     * Check if italic.
     */
    public function isItalic(): bool
    {
        return $this->italic;
    }

    /**
     * Set italic.
     */
    public function setItalic(bool $italic): self
    {
        $this->italic = $italic;

        return $this;
    }

    /**
     * Check if underline.
     */
    public function isUnderline(): bool
    {
        return $this->underline;
    }

    /**
     * Set underline.
     */
    public function setUnderline(bool $underline): self
    {
        $this->underline = $underline;

        return $this;
    }

    /**
     * Get color.
     *
     * @return array{float, float, float}|null
     */
    public function getColor(): ?array
    {
        return $this->color;
    }

    /**
     * Set color.
     *
     * @param array{float, float, float}|string $color RGB array or hex string
     */
    public function setColor(array|string $color): self
    {
        if (is_string($color)) {
            $this->color = $this->parseColor($color);
        } else {
            $this->color = $color;
        }

        return $this;
    }

    /**
     * Get background color.
     *
     * @return array{float, float, float}|null
     */
    public function getBackgroundColor(): ?array
    {
        return $this->backgroundColor;
    }

    /**
     * Set background color.
     *
     * @param array{float, float, float}|string $color
     */
    public function setBackgroundColor(array|string $color): self
    {
        if (is_string($color)) {
            $this->backgroundColor = $this->parseColor($color);
        } else {
            $this->backgroundColor = $color;
        }

        return $this;
    }

    /**
     * Get border color.
     *
     * @return array{float, float, float}|null
     */
    public function getBorderColor(): ?array
    {
        return $this->borderColor;
    }

    /**
     * Set border color.
     *
     * @param array{float, float, float}|string $color
     */
    public function setBorderColor(array|string $color): self
    {
        if (is_string($color)) {
            $this->borderColor = $this->parseColor($color);
        } else {
            $this->borderColor = $color;
        }

        return $this;
    }

    /**
     * Get line width.
     */
    public function getLineWidth(): float
    {
        return $this->lineWidth;
    }

    /**
     * Set line width.
     */
    public function setLineWidth(float $lineWidth): self
    {
        $this->lineWidth = $lineWidth;

        return $this;
    }

    /**
     * Get extra data.
     *
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * Set extra data.
     */
    public function setExtra(string $key, mixed $value): self
    {
        $this->extra[$key] = $value;

        return $this;
    }

    /**
     * Get extra data value.
     */
    public function getExtraValue(string $key, mixed $default = null): mixed
    {
        return $this->extra[$key] ?? $default;
    }

    /**
     * Parse a color string to RGB array.
     *
     * @return array{float, float, float}
     */
    private function parseColor(string $color): array
    {
        $color = trim($color);

        // Named colors
        $namedColors = [
            'black' => [0, 0, 0],
            'white' => [1, 1, 1],
            'red' => [1, 0, 0],
            'green' => [0, 0.5, 0],
            'blue' => [0, 0, 1],
            'yellow' => [1, 1, 0],
            'cyan' => [0, 1, 1],
            'magenta' => [1, 0, 1],
            'gray' => [0.5, 0.5, 0.5],
            'grey' => [0.5, 0.5, 0.5],
            'orange' => [1, 0.647, 0],
            'purple' => [0.5, 0, 0.5],
            'pink' => [1, 0.753, 0.796],
            'brown' => [0.647, 0.165, 0.165],
        ];

        $colorLower = strtolower($color);
        if (isset($namedColors[$colorLower])) {
            return $namedColors[$colorLower];
        }

        // Hex color
        if (str_starts_with($color, '#')) {
            $hex = ltrim($color, '#');

            // Short hex (#RGB)
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }

            if (strlen($hex) === 6) {
                $r = hexdec(substr($hex, 0, 2)) / 255;
                $g = hexdec(substr($hex, 2, 2)) / 255;
                $b = hexdec(substr($hex, 4, 2)) / 255;

                return [$r, $g, $b];
            }
        }

        // RGB function
        if (preg_match('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', $color, $matches)) {
            return [
                (int) $matches[1] / 255,
                (int) $matches[2] / 255,
                (int) $matches[3] / 255,
            ];
        }

        // Default to black
        return [0, 0, 0];
    }
}
