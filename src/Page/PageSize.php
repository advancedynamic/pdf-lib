<?php

declare(strict_types=1);

namespace PdfLib\Page;

/**
 * Standard page sizes.
 *
 * Dimensions are in points (1 point = 1/72 inch).
 */
final class PageSize
{
    // ISO A Series (portrait)
    public const A0 = [2384, 3370];
    public const A1 = [1684, 2384];
    public const A2 = [1191, 1684];
    public const A3 = [842, 1191];
    public const A4 = [595, 842];
    public const A5 = [420, 595];
    public const A6 = [298, 420];
    public const A7 = [210, 298];
    public const A8 = [148, 210];
    public const A9 = [105, 148];
    public const A10 = [74, 105];

    // ISO B Series (portrait)
    public const B0 = [2835, 4008];
    public const B1 = [2004, 2835];
    public const B2 = [1417, 2004];
    public const B3 = [1001, 1417];
    public const B4 = [709, 1001];
    public const B5 = [499, 709];
    public const B6 = [354, 499];
    public const B7 = [249, 354];
    public const B8 = [176, 249];
    public const B9 = [125, 176];
    public const B10 = [88, 125];

    // ISO C Series (envelopes)
    public const C0 = [2599, 3677];
    public const C1 = [1837, 2599];
    public const C2 = [1298, 1837];
    public const C3 = [918, 1298];
    public const C4 = [649, 918];
    public const C5 = [459, 649];
    public const C6 = [323, 459];
    public const C7 = [230, 323];
    public const C8 = [162, 230];
    public const C9 = [113, 162];
    public const C10 = [79, 113];

    // North American sizes
    public const LETTER = [612, 792];
    public const LEGAL = [612, 1008];
    public const TABLOID = [792, 1224];
    public const LEDGER = [1224, 792];
    public const EXECUTIVE = [522, 756];
    public const FOLIO = [612, 936];
    public const QUARTO = [610, 780];
    public const STATEMENT = [396, 612];

    // Other common sizes
    public const POSTCARD = [283, 416];
    public const PHOTO_4X6 = [288, 432];
    public const PHOTO_5X7 = [360, 504];

    private function __construct(
        private readonly float $width,
        private readonly float $height,
        private readonly string $name = 'Custom'
    ) {
    }

    /**
     * Create a page size from width and height in points.
     */
    public static function create(float $width, float $height, string $name = 'Custom'): self
    {
        return new self($width, $height, $name);
    }

    /**
     * Create from millimeters.
     */
    public static function fromMm(float $width, float $height, string $name = 'Custom'): self
    {
        return new self(
            $width * 72 / 25.4,
            $height * 72 / 25.4,
            $name
        );
    }

    /**
     * Create from inches.
     */
    public static function fromInches(float $width, float $height, string $name = 'Custom'): self
    {
        return new self(
            $width * 72,
            $height * 72,
            $name
        );
    }

    /**
     * Create from a predefined size constant.
     *
     * @param array{0: int|float, 1: int|float} $size
     */
    public static function fromArray(array $size, string $name = 'Custom'): self
    {
        return new self((float) $size[0], (float) $size[1], $name);
    }

    // Factory methods for common sizes

    public static function a4(): self
    {
        return self::fromArray(self::A4, 'A4');
    }

    public static function a3(): self
    {
        return self::fromArray(self::A3, 'A3');
    }

    public static function a5(): self
    {
        return self::fromArray(self::A5, 'A5');
    }

    public static function letter(): self
    {
        return self::fromArray(self::LETTER, 'Letter');
    }

    public static function legal(): self
    {
        return self::fromArray(self::LEGAL, 'Legal');
    }

    public static function tabloid(): self
    {
        return self::fromArray(self::TABLOID, 'Tabloid');
    }

    public static function ledger(): self
    {
        return self::fromArray(self::LEDGER, 'Ledger');
    }

    public static function executive(): self
    {
        return self::fromArray(self::EXECUTIVE, 'Executive');
    }

    /**
     * Get width in points.
     */
    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * Get height in points.
     */
    public function getHeight(): float
    {
        return $this->height;
    }

    /**
     * Get width in millimeters.
     */
    public function getWidthMm(): float
    {
        return $this->width * 25.4 / 72;
    }

    /**
     * Get height in millimeters.
     */
    public function getHeightMm(): float
    {
        return $this->height * 25.4 / 72;
    }

    /**
     * Get width in inches.
     */
    public function getWidthInches(): float
    {
        return $this->width / 72;
    }

    /**
     * Get height in inches.
     */
    public function getHeightInches(): float
    {
        return $this->height / 72;
    }

    /**
     * Get the size name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if this is a portrait orientation.
     */
    public function isPortrait(): bool
    {
        return $this->height >= $this->width;
    }

    /**
     * Check if this is a landscape orientation.
     */
    public function isLandscape(): bool
    {
        return $this->width > $this->height;
    }

    /**
     * Get the landscape version of this size.
     */
    public function landscape(): self
    {
        if ($this->isLandscape()) {
            return $this;
        }
        return new self($this->height, $this->width, $this->name . ' Landscape');
    }

    /**
     * Get the portrait version of this size.
     */
    public function portrait(): self
    {
        if ($this->isPortrait()) {
            return $this;
        }
        return new self($this->height, $this->width, $this->name . ' Portrait');
    }

    /**
     * Get as array [width, height].
     *
     * @return array{0: float, 1: float}
     */
    public function toArray(): array
    {
        return [$this->width, $this->height];
    }

    /**
     * Get as MediaBox array [0, 0, width, height].
     *
     * @return array{0: int, 1: int, 2: float, 3: float}
     */
    public function toMediaBox(): array
    {
        return [0, 0, $this->width, $this->height];
    }
}
