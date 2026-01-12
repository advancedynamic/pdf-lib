<?php

declare(strict_types=1);

namespace PdfLib\Content\Table;

/**
 * Represents a cell in a table.
 */
final class TableCell
{
    // Horizontal alignment
    public const ALIGN_LEFT = 'L';
    public const ALIGN_CENTER = 'C';
    public const ALIGN_RIGHT = 'R';
    public const ALIGN_JUSTIFY = 'J';

    // Vertical alignment
    public const VALIGN_TOP = 'T';
    public const VALIGN_MIDDLE = 'M';
    public const VALIGN_BOTTOM = 'B';

    private string $content = '';
    private int $colspan = 1;
    private int $rowspan = 1;
    private string $halign = self::ALIGN_LEFT;
    private string $valign = self::VALIGN_TOP;
    private bool $isHeader = false;

    // Position in table
    private int $rowIndex = -1;
    private int $colIndex = -1;

    // Calculated dimensions
    private float $width = 0;
    private float $height = 0;
    private float $x = 0;
    private float $y = 0;

    /** @var array<string, mixed> */
    private array $style = [];

    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    /**
     * Create a cell with content.
     */
    public static function create(string $content = ''): self
    {
        return new self($content);
    }

    /**
     * Create a header cell.
     */
    public static function header(string $content = ''): self
    {
        $cell = new self($content);
        $cell->isHeader = true;
        return $cell;
    }

    // Content methods

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    // Span methods

    public function getColspan(): int
    {
        return $this->colspan;
    }

    public function setColspan(int $colspan): self
    {
        $this->colspan = max(1, $colspan);
        return $this;
    }

    public function getRowspan(): int
    {
        return $this->rowspan;
    }

    public function setRowspan(int $rowspan): self
    {
        $this->rowspan = max(1, $rowspan);
        return $this;
    }

    // Alignment methods

    public function getHAlign(): string
    {
        return $this->halign;
    }

    public function setHAlign(string $align): self
    {
        $this->halign = $align;
        return $this;
    }

    public function alignLeft(): self
    {
        return $this->setHAlign(self::ALIGN_LEFT);
    }

    public function alignCenter(): self
    {
        return $this->setHAlign(self::ALIGN_CENTER);
    }

    public function alignRight(): self
    {
        return $this->setHAlign(self::ALIGN_RIGHT);
    }

    public function alignJustify(): self
    {
        return $this->setHAlign(self::ALIGN_JUSTIFY);
    }

    public function getVAlign(): string
    {
        return $this->valign;
    }

    public function setVAlign(string $valign): self
    {
        $this->valign = $valign;
        return $this;
    }

    public function valignTop(): self
    {
        return $this->setVAlign(self::VALIGN_TOP);
    }

    public function valignMiddle(): self
    {
        return $this->setVAlign(self::VALIGN_MIDDLE);
    }

    public function valignBottom(): self
    {
        return $this->setVAlign(self::VALIGN_BOTTOM);
    }

    // Header methods

    public function isHeader(): bool
    {
        return $this->isHeader;
    }

    public function setIsHeader(bool $isHeader): self
    {
        $this->isHeader = $isHeader;
        return $this;
    }

    // Position methods

    public function getRowIndex(): int
    {
        return $this->rowIndex;
    }

    public function setRowIndex(int $rowIndex): self
    {
        $this->rowIndex = $rowIndex;
        return $this;
    }

    public function getColIndex(): int
    {
        return $this->colIndex;
    }

    public function setColIndex(int $colIndex): self
    {
        $this->colIndex = $colIndex;
        return $this;
    }

    // Dimension methods

    public function getWidth(): float
    {
        return $this->width;
    }

    public function setWidth(float $width): self
    {
        $this->width = $width;
        return $this;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function setHeight(float $height): self
    {
        $this->height = $height;
        return $this;
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function setX(float $x): self
    {
        $this->x = $x;
        return $this;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function setY(float $y): self
    {
        $this->y = $y;
        return $this;
    }

    // Style methods

    /**
     * @return array<string, mixed>
     */
    public function getStyle(): array
    {
        return $this->style;
    }

    /**
     * @param array<string, mixed> $style
     */
    public function setStyle(array $style): self
    {
        $this->style = $style;
        return $this;
    }

    /**
     * @param array<string, mixed> $style
     */
    public function mergeStyle(array $style): self
    {
        $this->style = array_merge($this->style, $style);
        return $this;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->style['backgroundColor'] ?? null;
    }

    public function setBackgroundColor(string $color): self
    {
        $this->style['backgroundColor'] = $color;
        return $this;
    }

    public function getTextColor(): ?string
    {
        return $this->style['textColor'] ?? null;
    }

    public function setTextColor(string $color): self
    {
        $this->style['textColor'] = $color;
        return $this;
    }

    public function getFontSize(): ?float
    {
        return $this->style['fontSize'] ?? null;
    }

    public function setFontSize(float $size): self
    {
        $this->style['fontSize'] = $size;
        return $this;
    }

    public function getFontFamily(): ?string
    {
        return $this->style['fontFamily'] ?? null;
    }

    public function setFontFamily(string $family): self
    {
        $this->style['fontFamily'] = $family;
        return $this;
    }

    // Border methods

    public function getBorderWidth(): ?float
    {
        return $this->style['borderWidth'] ?? null;
    }

    public function setBorderWidth(float $width): self
    {
        $this->style['borderWidth'] = $width;
        return $this;
    }

    public function getBorderColor(): ?string
    {
        return $this->style['borderColor'] ?? null;
    }

    public function setBorderColor(string $color): self
    {
        $this->style['borderColor'] = $color;
        return $this;
    }

    public function hasBorderTop(): bool
    {
        return $this->style['borderTop'] ?? true;
    }

    public function setBorderTop(bool $enabled): self
    {
        $this->style['borderTop'] = $enabled;
        return $this;
    }

    public function hasBorderRight(): bool
    {
        return $this->style['borderRight'] ?? true;
    }

    public function setBorderRight(bool $enabled): self
    {
        $this->style['borderRight'] = $enabled;
        return $this;
    }

    public function hasBorderBottom(): bool
    {
        return $this->style['borderBottom'] ?? true;
    }

    public function setBorderBottom(bool $enabled): self
    {
        $this->style['borderBottom'] = $enabled;
        return $this;
    }

    public function hasBorderLeft(): bool
    {
        return $this->style['borderLeft'] ?? true;
    }

    public function setBorderLeft(bool $enabled): self
    {
        $this->style['borderLeft'] = $enabled;
        return $this;
    }

    public function removeBorders(): self
    {
        $this->style['borderTop'] = false;
        $this->style['borderRight'] = false;
        $this->style['borderBottom'] = false;
        $this->style['borderLeft'] = false;
        return $this;
    }

    // Padding methods

    public function getPaddingTop(): float
    {
        return $this->style['paddingTop'] ?? 2.0;
    }

    public function setPaddingTop(float $padding): self
    {
        $this->style['paddingTop'] = $padding;
        return $this;
    }

    public function getPaddingRight(): float
    {
        return $this->style['paddingRight'] ?? 2.0;
    }

    public function setPaddingRight(float $padding): self
    {
        $this->style['paddingRight'] = $padding;
        return $this;
    }

    public function getPaddingBottom(): float
    {
        return $this->style['paddingBottom'] ?? 2.0;
    }

    public function setPaddingBottom(float $padding): self
    {
        $this->style['paddingBottom'] = $padding;
        return $this;
    }

    public function getPaddingLeft(): float
    {
        return $this->style['paddingLeft'] ?? 2.0;
    }

    public function setPaddingLeft(float $padding): self
    {
        $this->style['paddingLeft'] = $padding;
        return $this;
    }

    public function setPadding(float $padding): self
    {
        $this->style['paddingTop'] = $padding;
        $this->style['paddingRight'] = $padding;
        $this->style['paddingBottom'] = $padding;
        $this->style['paddingLeft'] = $padding;
        return $this;
    }

    /**
     * Get total horizontal padding.
     */
    public function getHorizontalPadding(): float
    {
        return $this->getPaddingLeft() + $this->getPaddingRight();
    }

    /**
     * Get total vertical padding.
     */
    public function getVerticalPadding(): float
    {
        return $this->getPaddingTop() + $this->getPaddingBottom();
    }

    /**
     * Calculate minimum height based on content.
     */
    public function getMinimumHeight(float $fontSize = 12.0): float
    {
        $lines = substr_count($this->content, "\n") + 1;
        return ($lines * $fontSize * 1.2) + $this->getVerticalPadding();
    }
}
