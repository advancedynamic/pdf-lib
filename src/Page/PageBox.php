<?php

declare(strict_types=1);

namespace PdfLib\Page;

use PdfLib\Parser\Object\PdfArray;

/**
 * Represents a page boundary box.
 *
 * PDF defines several page boxes:
 * - MediaBox: Physical medium boundaries (required)
 * - CropBox: Visible region when displayed/printed (default: MediaBox)
 * - BleedBox: Region for clipping during production (default: CropBox)
 * - TrimBox: Intended dimensions of finished page (default: CropBox)
 * - ArtBox: Meaningful content extent (default: CropBox)
 */
final class PageBox
{
    public const MEDIA_BOX = 'MediaBox';
    public const CROP_BOX = 'CropBox';
    public const BLEED_BOX = 'BleedBox';
    public const TRIM_BOX = 'TrimBox';
    public const ART_BOX = 'ArtBox';

    public function __construct(
        private readonly float $llx, // Lower-left x
        private readonly float $lly, // Lower-left y
        private readonly float $urx, // Upper-right x
        private readonly float $ury  // Upper-right y
    ) {
    }

    /**
     * Create from a PageSize.
     */
    public static function fromPageSize(PageSize $size): self
    {
        return new self(0, 0, $size->getWidth(), $size->getHeight());
    }

    /**
     * Create from an array [llx, lly, urx, ury].
     *
     * @param array{0: float|int, 1: float|int, 2: float|int, 3: float|int} $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            (float) $array[0],
            (float) $array[1],
            (float) $array[2],
            (float) $array[3]
        );
    }

    /**
     * Create from a PdfArray.
     */
    public static function fromPdfArray(PdfArray $array): self
    {
        $values = $array->toArray();
        return self::fromArray($values);
    }

    /**
     * Create with specific dimensions.
     */
    public static function create(float $width, float $height, float $x = 0, float $y = 0): self
    {
        return new self($x, $y, $x + $width, $y + $height);
    }

    /**
     * Get lower-left x coordinate.
     */
    public function getLlx(): float
    {
        return $this->llx;
    }

    /**
     * Get lower-left y coordinate.
     */
    public function getLly(): float
    {
        return $this->lly;
    }

    /**
     * Get upper-right x coordinate.
     */
    public function getUrx(): float
    {
        return $this->urx;
    }

    /**
     * Get upper-right y coordinate.
     */
    public function getUry(): float
    {
        return $this->ury;
    }

    /**
     * Get width.
     */
    public function getWidth(): float
    {
        return $this->urx - $this->llx;
    }

    /**
     * Get height.
     */
    public function getHeight(): float
    {
        return $this->ury - $this->lly;
    }

    /**
     * Convert to array [llx, lly, urx, ury].
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function toArray(): array
    {
        return [$this->llx, $this->lly, $this->urx, $this->ury];
    }

    /**
     * Convert to PdfArray.
     */
    public function toPdfArray(): PdfArray
    {
        return PdfArray::fromValues($this->toArray());
    }

    /**
     * Expand box by adding margin.
     */
    public function expand(float $margin): self
    {
        return new self(
            $this->llx - $margin,
            $this->lly - $margin,
            $this->urx + $margin,
            $this->ury + $margin
        );
    }

    /**
     * Contract box by subtracting margin.
     */
    public function contract(float $margin): self
    {
        return $this->expand(-$margin);
    }

    /**
     * Check if a point is inside this box.
     */
    public function contains(float $x, float $y): bool
    {
        return $x >= $this->llx
            && $x <= $this->urx
            && $y >= $this->lly
            && $y <= $this->ury;
    }

    /**
     * Check if this box intersects with another.
     */
    public function intersects(self $other): bool
    {
        return $this->llx < $other->urx
            && $this->urx > $other->llx
            && $this->lly < $other->ury
            && $this->ury > $other->lly;
    }

    /**
     * Get intersection with another box.
     */
    public function intersection(self $other): ?self
    {
        if (!$this->intersects($other)) {
            return null;
        }

        return new self(
            max($this->llx, $other->llx),
            max($this->lly, $other->lly),
            min($this->urx, $other->urx),
            min($this->ury, $other->ury)
        );
    }

    /**
     * Get union (bounding box) with another box.
     */
    public function union(self $other): self
    {
        return new self(
            min($this->llx, $other->llx),
            min($this->lly, $other->lly),
            max($this->urx, $other->urx),
            max($this->ury, $other->ury)
        );
    }

    /**
     * Translate (move) the box.
     */
    public function translate(float $dx, float $dy): self
    {
        return new self(
            $this->llx + $dx,
            $this->lly + $dy,
            $this->urx + $dx,
            $this->ury + $dy
        );
    }

    /**
     * Scale the box.
     */
    public function scale(float $factor): self
    {
        $centerX = ($this->llx + $this->urx) / 2;
        $centerY = ($this->lly + $this->ury) / 2;
        $halfWidth = $this->getWidth() / 2 * $factor;
        $halfHeight = $this->getHeight() / 2 * $factor;

        return new self(
            $centerX - $halfWidth,
            $centerY - $halfHeight,
            $centerX + $halfWidth,
            $centerY + $halfHeight
        );
    }

    /**
     * Get center x coordinate.
     */
    public function getCenterX(): float
    {
        return ($this->llx + $this->urx) / 2;
    }

    /**
     * Get center y coordinate.
     */
    public function getCenterY(): float
    {
        return ($this->lly + $this->ury) / 2;
    }

    /**
     * Check if box is valid (positive dimensions).
     */
    public function isValid(): bool
    {
        return $this->urx > $this->llx && $this->ury > $this->lly;
    }

    /**
     * Get area of the box.
     */
    public function getArea(): float
    {
        return $this->getWidth() * $this->getHeight();
    }
}
