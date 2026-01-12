<?php

declare(strict_types=1);

namespace PdfLib\Html;

/**
 * Represents a page with laid-out elements.
 */
class LayoutPage
{
    private int $pageNumber;
    private float $width;
    private float $height;

    /** @var array<LayoutElement> */
    private array $elements = [];

    public function __construct(int $pageNumber, float $width, float $height)
    {
        $this->pageNumber = $pageNumber;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Get page number.
     */
    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    /**
     * Get page width.
     */
    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * Get page height.
     */
    public function getHeight(): float
    {
        return $this->height;
    }

    /**
     * Add an element to the page.
     */
    public function addElement(LayoutElement $element): self
    {
        $this->elements[] = $element;

        return $this;
    }

    /**
     * Get all elements.
     *
     * @return array<LayoutElement>
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    /**
     * Check if page has elements.
     */
    public function hasElements(): bool
    {
        return count($this->elements) > 0;
    }

    /**
     * Get element count.
     */
    public function getElementCount(): int
    {
        return count($this->elements);
    }

    /**
     * Clear all elements.
     */
    public function clearElements(): self
    {
        $this->elements = [];

        return $this;
    }
}
