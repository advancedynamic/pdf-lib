<?php

declare(strict_types=1);

namespace PdfLib\Content\Table;

use PdfLib\Page\Page;

/**
 * Table layout and rendering for PDF.
 *
 * @example
 * ```php
 * $table = new Table();
 * $table->setWidth(500)
 *       ->setPosition(50, 700)
 *       ->addHeaderRow(['Name', 'Age', 'City'])
 *       ->addRow(['John', '30', 'New York'])
 *       ->addRow(['Jane', '25', 'Los Angeles']);
 *
 * $table->render($page);
 * ```
 */
final class Table
{
    /** @var array<int, array<int, TableCell>> */
    private array $rows = [];

    /** @var array<int, float> Column widths */
    private array $columnWidths = [];

    /** @var array<int, float> Row heights */
    private array $rowHeights = [];

    /** @var array<int, array<int, bool>> Cell occupancy tracking for colspan/rowspan */
    private array $occupancy = [];

    private float $x = 0;
    private float $y = 0;
    private float $tableWidth = 0;
    private int $numColumns = 0;

    // Default styling
    private float $cellPadding = 2.0;
    private float $borderWidth = 0.2;
    private string $borderColor = '#000000';
    private float $fontSize = 10.0;
    private string $fontFamily = 'Helvetica';

    // Header styling
    private string $headerBackgroundColor = '#E0E0E0';
    private string $headerTextColor = '#000000';
    private bool $headerBold = true;

    // Auto-sizing
    private bool $autoSize = true;

    /** @var array<int> Header row indices */
    private array $headerRows = [];

    public function __construct()
    {
    }

    /**
     * Create a new table.
     */
    public static function create(): self
    {
        return new self();
    }

    // Position methods

    public function setPosition(float $x, float $y): self
    {
        $this->x = $x;
        $this->y = $y;
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

    // Dimension methods

    public function getWidth(): float
    {
        return $this->tableWidth;
    }

    public function setWidth(float $width): self
    {
        $this->tableWidth = $width;
        return $this;
    }

    public function getRowCount(): int
    {
        return count($this->rows);
    }

    public function getColumnCount(): int
    {
        return $this->numColumns;
    }

    // Style methods

    public function setCellPadding(float $padding): self
    {
        $this->cellPadding = $padding;
        return $this;
    }

    public function setBorderWidth(float $width): self
    {
        $this->borderWidth = $width;
        return $this;
    }

    public function setBorderColor(string $color): self
    {
        $this->borderColor = $color;
        return $this;
    }

    public function setFontSize(float $size): self
    {
        $this->fontSize = $size;
        return $this;
    }

    public function setFontFamily(string $family): self
    {
        $this->fontFamily = $family;
        return $this;
    }

    public function setHeaderBackgroundColor(string $color): self
    {
        $this->headerBackgroundColor = $color;
        return $this;
    }

    public function setHeaderTextColor(string $color): self
    {
        $this->headerTextColor = $color;
        return $this;
    }

    public function setHeaderBold(bool $bold): self
    {
        $this->headerBold = $bold;
        return $this;
    }

    public function setAutoSize(bool $autoSize): self
    {
        $this->autoSize = $autoSize;
        return $this;
    }

    /**
     * Set column widths manually.
     *
     * @param array<int, float> $widths
     */
    public function setColumnWidths(array $widths): self
    {
        $this->columnWidths = $widths;
        $this->autoSize = false;
        return $this;
    }

    // Row methods

    /**
     * Add a header row.
     *
     * @param array<int, string|TableCell> $cells
     * @param array<string, mixed> $style
     */
    public function addHeaderRow(array $cells, array $style = []): self
    {
        $rowIndex = count($this->rows);
        $this->headerRows[] = $rowIndex;
        return $this->addRow($cells, $style, true);
    }

    /**
     * Add a data row.
     *
     * @param array<int, string|TableCell> $cells
     * @param array<string, mixed> $style
     */
    public function addRow(array $cells, array $style = [], bool $isHeader = false): self
    {
        $rowIndex = count($this->rows);
        $row = [];
        $colIndex = 0;

        foreach ($cells as $cell) {
            // Skip occupied cells
            while ($this->isCellOccupied($rowIndex, $colIndex)) {
                $colIndex++;
            }

            // Convert string to TableCell
            if (is_string($cell)) {
                $cell = new TableCell($cell);
            }

            // Apply row style
            if (!empty($style)) {
                $cell->mergeStyle($style);
            }

            // Set header properties
            if ($isHeader) {
                $cell->setIsHeader(true);
                $cell->mergeStyle([
                    'backgroundColor' => $this->headerBackgroundColor,
                    'textColor' => $this->headerTextColor,
                ]);
            }

            // Set position
            $cell->setRowIndex($rowIndex);
            $cell->setColIndex($colIndex);

            // Mark cells as occupied for colspan/rowspan
            $this->markCellOccupied($rowIndex, $colIndex, $cell->getColspan(), $cell->getRowspan());

            $row[$colIndex] = $cell;
            $colIndex += $cell->getColspan();
        }

        $this->rows[$rowIndex] = $row;
        $this->numColumns = max($this->numColumns, $colIndex);

        return $this;
    }

    /**
     * Get a specific row.
     *
     * @return array<int, TableCell>|null
     */
    public function getRow(int $index): ?array
    {
        return $this->rows[$index] ?? null;
    }

    /**
     * Get a specific cell.
     */
    public function getCell(int $row, int $col): ?TableCell
    {
        return $this->rows[$row][$col] ?? null;
    }

    // Calculation methods

    /**
     * Calculate table layout.
     */
    public function calculate(): void
    {
        if ($this->autoSize) {
            $this->calculateColumnWidths();
        }
        $this->calculateRowHeights();
        $this->calculateCellPositions();
    }

    /**
     * Calculate column widths based on content.
     */
    private function calculateColumnWidths(): void
    {
        if ($this->tableWidth <= 0) {
            $this->tableWidth = 500; // Default width
        }

        // Initialize column widths
        $contentWidths = array_fill(0, $this->numColumns, 0);

        // Measure content width for single-span cells
        foreach ($this->rows as $row) {
            foreach ($row as $cell) {
                if ($cell->getColspan() === 1) {
                    $colIndex = $cell->getColIndex();
                    $textWidth = $this->measureTextWidth($cell->getContent());
                    $cellWidth = $textWidth + $cell->getHorizontalPadding();
                    $contentWidths[$colIndex] = max($contentWidths[$colIndex], $cellWidth);
                }
            }
        }

        // Calculate total content width
        $totalContent = array_sum($contentWidths);

        if ($totalContent > 0) {
            // Scale columns to fit table width
            $scale = $this->tableWidth / $totalContent;
            foreach ($contentWidths as $i => $width) {
                $this->columnWidths[$i] = $width * $scale;
            }
        } else {
            // Equal width columns
            $colWidth = $this->tableWidth / $this->numColumns;
            $this->columnWidths = array_fill(0, $this->numColumns, $colWidth);
        }
    }

    /**
     * Calculate row heights based on content.
     */
    private function calculateRowHeights(): void
    {
        // First pass: calculate heights for non-spanned cells
        foreach ($this->rows as $rowIndex => $row) {
            $maxHeight = 0;

            foreach ($row as $cell) {
                if ($cell->getRowspan() === 1) {
                    $cellHeight = $this->measureCellHeight($cell);
                    $maxHeight = max($maxHeight, $cellHeight);
                }
            }

            $this->rowHeights[$rowIndex] = max($maxHeight, $this->fontSize + $this->cellPadding * 2);
        }

        // Second pass: adjust for rowspan cells
        foreach ($this->rows as $rowIndex => $row) {
            foreach ($row as $cell) {
                if ($cell->getRowspan() > 1) {
                    $cellHeight = $this->measureCellHeight($cell);
                    $spanHeight = 0;

                    for ($i = 0; $i < $cell->getRowspan(); $i++) {
                        $spanHeight += $this->rowHeights[$rowIndex + $i] ?? 0;
                    }

                    if ($cellHeight > $spanHeight) {
                        // Distribute extra height
                        $extra = ($cellHeight - $spanHeight) / $cell->getRowspan();
                        for ($i = 0; $i < $cell->getRowspan(); $i++) {
                            $this->rowHeights[$rowIndex + $i] += $extra;
                        }
                    }
                }
            }
        }
    }

    /**
     * Calculate cell positions.
     */
    private function calculateCellPositions(): void
    {
        $currentY = $this->y;

        foreach ($this->rows as $rowIndex => $row) {
            $currentX = $this->x;

            foreach ($row as $colIndex => $cell) {
                // Calculate cell width (including colspan)
                $cellWidth = 0;
                for ($i = 0; $i < $cell->getColspan(); $i++) {
                    $cellWidth += $this->columnWidths[$colIndex + $i] ?? 0;
                }

                // Calculate cell height (including rowspan)
                $cellHeight = 0;
                for ($i = 0; $i < $cell->getRowspan(); $i++) {
                    $cellHeight += $this->rowHeights[$rowIndex + $i] ?? 0;
                }

                $cell->setX($currentX);
                $cell->setY($currentY);
                $cell->setWidth($cellWidth);
                $cell->setHeight($cellHeight);

                $currentX += $cellWidth;
            }

            $currentY -= $this->rowHeights[$rowIndex] ?? 0;
        }
    }

    /**
     * Render the table to a page.
     */
    public function render(Page $page): void
    {
        $this->calculate();

        foreach ($this->rows as $row) {
            foreach ($row as $cell) {
                $this->renderCell($page, $cell);
            }
        }
    }

    /**
     * Render a single cell.
     */
    private function renderCell(Page $page, TableCell $cell): void
    {
        $x = $cell->getX();
        $y = $cell->getY();
        $width = $cell->getWidth();
        $height = $cell->getHeight();

        // Draw background
        $bgColor = $cell->getBackgroundColor();
        if ($bgColor !== null) {
            $page->addRectangle($x, $y - $height, $width, $height, [
                'fill' => true,
                'fillColor' => $this->parseColor($bgColor),
            ]);
        }

        // Draw borders
        $borderWidth = $cell->getBorderWidth() ?? $this->borderWidth;
        $borderColor = $cell->getBorderColor() ?? $this->borderColor;

        if ($borderWidth > 0) {
            $colorRgb = $this->parseColor($borderColor);

            if ($cell->hasBorderTop()) {
                $page->addLine($x, $y, $x + $width, $y, [
                    'lineWidth' => $borderWidth,
                    'color' => $colorRgb,
                ]);
            }

            if ($cell->hasBorderBottom()) {
                $page->addLine($x, $y - $height, $x + $width, $y - $height, [
                    'lineWidth' => $borderWidth,
                    'color' => $colorRgb,
                ]);
            }

            if ($cell->hasBorderLeft()) {
                $page->addLine($x, $y, $x, $y - $height, [
                    'lineWidth' => $borderWidth,
                    'color' => $colorRgb,
                ]);
            }

            if ($cell->hasBorderRight()) {
                $page->addLine($x + $width, $y, $x + $width, $y - $height, [
                    'lineWidth' => $borderWidth,
                    'color' => $colorRgb,
                ]);
            }
        }

        // Draw text
        $content = $cell->getContent();
        if ($content !== '') {
            $fontSize = $cell->getFontSize() ?? $this->fontSize;
            $textColor = $cell->getTextColor();
            $textColorRgb = $textColor !== null ? $this->parseColor($textColor) : [0.0, 0.0, 0.0];

            // Calculate text position based on alignment
            $paddingLeft = $cell->getPaddingLeft();
            $paddingTop = $cell->getPaddingTop();

            $textX = $x + $paddingLeft;
            $textY = $y - $paddingTop - $fontSize;

            // Horizontal alignment
            $textWidth = $this->measureTextWidth($content);
            $availableWidth = $width - $cell->getHorizontalPadding();

            switch ($cell->getHAlign()) {
                case TableCell::ALIGN_CENTER:
                    $textX = $x + ($width - $textWidth) / 2;
                    break;
                case TableCell::ALIGN_RIGHT:
                    $textX = $x + $width - $cell->getPaddingRight() - $textWidth;
                    break;
            }

            // Vertical alignment
            $textHeight = $fontSize;
            $availableHeight = $height - $cell->getVerticalPadding();

            switch ($cell->getVAlign()) {
                case TableCell::VALIGN_MIDDLE:
                    $textY = $y - ($height + $textHeight) / 2;
                    break;
                case TableCell::VALIGN_BOTTOM:
                    $textY = $y - $height + $cell->getPaddingBottom();
                    break;
            }

            $page->addText($content, $textX, $textY, [
                'fontSize' => $fontSize,
                'color' => $textColorRgb,
            ]);
        }
    }

    /**
     * Get the total table height.
     */
    public function getTotalHeight(): float
    {
        $this->calculate();
        return array_sum($this->rowHeights);
    }

    /**
     * Mark cells as occupied for colspan/rowspan tracking.
     */
    private function markCellOccupied(int $rowIndex, int $colIndex, int $colspan, int $rowspan): void
    {
        for ($r = 0; $r < $rowspan; $r++) {
            for ($c = 0; $c < $colspan; $c++) {
                if ($r === 0 && $c === 0) {
                    continue; // Skip the original cell
                }
                $this->occupancy[$rowIndex + $r][$colIndex + $c] = true;
            }
        }
    }

    /**
     * Check if a cell position is occupied.
     */
    private function isCellOccupied(int $rowIndex, int $colIndex): bool
    {
        return $this->occupancy[$rowIndex][$colIndex] ?? false;
    }

    /**
     * Measure text width (approximation).
     */
    private function measureTextWidth(string $text): float
    {
        // Rough approximation: average character width is ~0.5 * font size
        return strlen($text) * $this->fontSize * 0.5;
    }

    /**
     * Measure cell height based on content.
     */
    private function measureCellHeight(TableCell $cell): float
    {
        $fontSize = $cell->getFontSize() ?? $this->fontSize;
        $lines = substr_count($cell->getContent(), "\n") + 1;
        return ($lines * $fontSize * 1.2) + $cell->getVerticalPadding();
    }

    /**
     * Parse color string to RGB array.
     *
     * @return array{float, float, float}
     */
    private function parseColor(string $color): array
    {
        // Remove # prefix
        $color = ltrim($color, '#');

        // Parse hex color
        if (strlen($color) === 6) {
            $r = hexdec(substr($color, 0, 2)) / 255;
            $g = hexdec(substr($color, 2, 2)) / 255;
            $b = hexdec(substr($color, 4, 2)) / 255;
            return [$r, $g, $b];
        }

        // Short form (#RGB)
        if (strlen($color) === 3) {
            $r = hexdec($color[0] . $color[0]) / 255;
            $g = hexdec($color[1] . $color[1]) / 255;
            $b = hexdec($color[2] . $color[2]) / 255;
            return [$r, $g, $b];
        }

        return [0.0, 0.0, 0.0]; // Default black
    }
}
