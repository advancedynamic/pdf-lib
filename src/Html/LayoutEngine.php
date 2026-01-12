<?php

declare(strict_types=1);

namespace PdfLib\Html;

use PdfLib\Page\PageSize;

/**
 * Calculates element positions and handles page breaks.
 */
class LayoutEngine
{
    private PageSize $pageSize;
    private float $marginTop;
    private float $marginRight;
    private float $marginBottom;
    private float $marginLeft;

    private string $defaultFontFamily = 'Helvetica';
    private float $defaultFontSize = 12;

    /** @var array<string, array<string, mixed>> */
    private array $defaultStyles = [];

    // Current position tracking
    private float $currentX;
    private float $currentY;
    private float $contentWidth;
    private float $contentHeight;
    private float $lineHeight;

    // Page tracking
    private int $currentPageNumber = 1;

    /** @var array<LayoutPage> */
    private array $pages = [];

    private ?LayoutPage $currentPage = null;

    // Text style stack
    private bool $isBold = false;
    private bool $isItalic = false;
    private bool $isUnderline = false;
    private ?string $linkHref = null;

    /** @var array{float, float, float}|null */
    private ?array $currentColor = null;

    // List tracking
    private int $listDepth = 0;
    private int $listItemNumber = 0;
    private string $listType = 'ul';

    public function __construct(
        PageSize $pageSize,
        float $marginTop = 50,
        float $marginRight = 50,
        float $marginBottom = 50,
        float $marginLeft = 50
    ) {
        $this->pageSize = $pageSize;
        $this->marginTop = $marginTop;
        $this->marginRight = $marginRight;
        $this->marginBottom = $marginBottom;
        $this->marginLeft = $marginLeft;

        $this->contentWidth = $pageSize->getWidth() - $marginLeft - $marginRight;
        $this->contentHeight = $pageSize->getHeight() - $marginTop - $marginBottom;
    }

    /**
     * Set default font.
     */
    public function setDefaultFont(string $family, float $size): self
    {
        $this->defaultFontFamily = $family;
        $this->defaultFontSize = $size;

        return $this;
    }

    /**
     * Set default styles.
     *
     * @param array<string, array<string, mixed>> $styles
     */
    public function setDefaultStyles(array $styles): self
    {
        $this->defaultStyles = $styles;

        return $this;
    }

    /**
     * Layout HTML elements and return pages.
     *
     * @param array<HtmlElement> $elements
     * @return array<LayoutPage>
     */
    public function layout(array $elements): array
    {
        $this->pages = [];
        $this->currentPageNumber = 1;
        $this->newPage();

        foreach ($elements as $element) {
            $this->layoutElement($element);
        }

        return $this->pages;
    }

    /**
     * Create a new page.
     */
    private function newPage(): void
    {
        $this->currentPage = new LayoutPage(
            $this->currentPageNumber,
            $this->pageSize->getWidth(),
            $this->pageSize->getHeight()
        );
        $this->pages[] = $this->currentPage;

        $this->currentX = $this->marginLeft;
        $this->currentY = $this->pageSize->getHeight() - $this->marginTop;
        $this->lineHeight = $this->defaultFontSize * 1.4;
        $this->currentPageNumber++;
    }

    /**
     * Check if we need a new page.
     */
    private function checkPageBreak(float $requiredHeight): void
    {
        if ($this->currentY - $requiredHeight < $this->marginBottom) {
            $this->newPage();
        }
    }

    /**
     * Layout a single element.
     */
    private function layoutElement(HtmlElement $element): void
    {
        $tagName = $element->getTagName();

        // Get element styles
        $styles = $this->getComputedStyles($element);

        // Apply margins
        $marginTop = $this->parseLength($styles['margin-top'] ?? '0');
        $marginBottom = $this->parseLength($styles['margin-bottom'] ?? '0');

        if ($marginTop > 0 && $element->isBlock()) {
            $this->currentY -= $marginTop;
        }

        switch ($tagName) {
            case 'text':
                $this->layoutText($element, $styles);
                break;

            case 'p':
                $this->layoutParagraph($element, $styles);
                break;

            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                $this->layoutHeading($element, $styles);
                break;

            case 'br':
                $this->currentY -= $this->lineHeight;
                $this->currentX = $this->marginLeft;
                break;

            case 'hr':
                $this->layoutHorizontalRule($element, $styles);
                break;

            case 'ul':
            case 'ol':
                $this->layoutList($element, $styles);
                break;

            case 'li':
                $this->layoutListItem($element, $styles);
                break;

            case 'table':
                $this->layoutTable($element, $styles);
                break;

            case 'img':
                $this->layoutImage($element, $styles);
                break;

            case 'div':
            case 'section':
            case 'article':
            case 'header':
            case 'footer':
            case 'nav':
            case 'aside':
                $this->layoutBlock($element, $styles);
                break;

            case 'blockquote':
                $this->layoutBlockquote($element, $styles);
                break;

            case 'pre':
                $this->layoutPreformatted($element, $styles);
                break;

            case 'a':
            case 'span':
            case 'strong':
            case 'b':
            case 'em':
            case 'i':
            case 'u':
            case 'code':
                $this->layoutInline($element, $styles);
                break;

            default:
                // Process children for unknown elements
                foreach ($element->getChildren() as $child) {
                    $this->layoutElement($child);
                }
                break;
        }

        if ($marginBottom > 0 && $element->isBlock()) {
            $this->currentY -= $marginBottom;
        }
    }

    /**
     * Layout text content.
     *
     * @param array<string, string> $styles
     */
    private function layoutText(HtmlElement $element, array $styles): void
    {
        $text = $element->getContent();
        if (trim($text) === '') {
            return;
        }

        $fontSize = $this->parseLength($styles['font-size'] ?? $this->defaultFontSize . 'pt');
        $fontFamily = $styles['font-family'] ?? $this->defaultFontFamily;
        $color = $this->currentColor ?? $this->parseColor($styles['color'] ?? '#000000');
        $lineHeight = $fontSize * 1.4;

        $this->checkPageBreak($lineHeight);

        $layoutElement = LayoutElement::text($text, $this->currentX, $this->currentY);
        $layoutElement->setFontFamily($fontFamily);
        $layoutElement->setFontSize($fontSize);
        $layoutElement->setBold($this->isBold);
        $layoutElement->setItalic($this->isItalic);
        $layoutElement->setUnderline($this->isUnderline);
        $layoutElement->setColor($color);

        if ($this->linkHref !== null) {
            $layoutElement->setExtra('href', $this->linkHref);
        }

        $this->currentPage->addElement($layoutElement);

        // Estimate text width and update position
        $textWidth = $this->estimateTextWidth($text, $fontSize);
        $availableWidth = $this->contentWidth - ($this->currentX - $this->marginLeft);

        if ($textWidth > $availableWidth) {
            // Word wrap needed
            $this->wrapText($text, $fontSize, $fontFamily, $color, $lineHeight);
        } else {
            $this->currentX += $textWidth;
        }
    }

    /**
     * Word wrap text that doesn't fit on one line.
     *
     * @param array{float, float, float} $color
     */
    private function wrapText(
        string $text,
        float $fontSize,
        string $fontFamily,
        array $color,
        float $lineHeight
    ): void {
        $words = preg_split('/\s+/', $text);
        $line = '';
        $firstLine = true;

        foreach ($words as $word) {
            $testLine = $line === '' ? $word : $line . ' ' . $word;
            $testWidth = $this->estimateTextWidth($testLine, $fontSize);

            $availableWidth = $firstLine
                ? $this->contentWidth - ($this->currentX - $this->marginLeft)
                : $this->contentWidth;

            if ($testWidth > $availableWidth && $line !== '') {
                // Output current line
                if (!$firstLine) {
                    $this->currentX = $this->marginLeft;
                    $this->currentY -= $lineHeight;
                    $this->checkPageBreak($lineHeight);
                }

                $layoutElement = LayoutElement::text($line, $this->currentX, $this->currentY);
                $layoutElement->setFontFamily($fontFamily);
                $layoutElement->setFontSize($fontSize);
                $layoutElement->setBold($this->isBold);
                $layoutElement->setItalic($this->isItalic);
                $layoutElement->setColor($color);
                $this->currentPage->addElement($layoutElement);

                $line = $word;
                $firstLine = false;
            } else {
                $line = $testLine;
            }
        }

        // Output remaining text
        if ($line !== '') {
            if (!$firstLine) {
                $this->currentX = $this->marginLeft;
                $this->currentY -= $lineHeight;
                $this->checkPageBreak($lineHeight);
            }

            $layoutElement = LayoutElement::text($line, $this->currentX, $this->currentY);
            $layoutElement->setFontFamily($fontFamily);
            $layoutElement->setFontSize($fontSize);
            $layoutElement->setBold($this->isBold);
            $layoutElement->setItalic($this->isItalic);
            $layoutElement->setColor($color);
            $this->currentPage->addElement($layoutElement);

            $this->currentX = $this->marginLeft + $this->estimateTextWidth($line, $fontSize);
        }
    }

    /**
     * Layout a paragraph.
     *
     * @param array<string, string> $styles
     */
    private function layoutParagraph(HtmlElement $element, array $styles): void
    {
        $this->currentX = $this->marginLeft;
        $this->lineHeight = $this->parseLength($styles['font-size'] ?? $this->defaultFontSize . 'pt') * 1.4;

        // Layout content
        $content = $element->getContent();
        if ($content !== '' && !$element->hasChildren()) {
            $textElement = new HtmlElement('text', $content);
            $this->layoutText($textElement, $styles);
        } else {
            foreach ($element->getChildren() as $child) {
                $this->layoutElement($child);
            }
        }

        // Move to next line after paragraph
        $this->currentY -= $this->lineHeight;
        $this->currentX = $this->marginLeft;
    }

    /**
     * Layout a heading.
     *
     * @param array<string, string> $styles
     */
    private function layoutHeading(HtmlElement $element, array $styles): void
    {
        $oldBold = $this->isBold;
        $this->isBold = true;

        $this->currentX = $this->marginLeft;
        $fontSize = $this->parseLength($styles['font-size'] ?? '16pt');
        $this->lineHeight = $fontSize * 1.4;

        $this->checkPageBreak($this->lineHeight);

        // Layout content
        $content = $element->getContent();
        if ($content !== '' && !$element->hasChildren()) {
            $textElement = new HtmlElement('text', $content);
            $this->layoutText($textElement, $styles);
        } else {
            foreach ($element->getChildren() as $child) {
                $this->layoutElement($child);
            }
        }

        // Move to next line
        $this->currentY -= $this->lineHeight;
        $this->currentX = $this->marginLeft;
        $this->isBold = $oldBold;
    }

    /**
     * Layout a horizontal rule.
     *
     * @param array<string, string> $styles
     */
    private function layoutHorizontalRule(HtmlElement $element, array $styles): void
    {
        $this->checkPageBreak(10);

        $lineElement = LayoutElement::line(
            $this->marginLeft,
            $this->currentY - 5,
            $this->contentWidth
        );
        $lineElement->setLineWidth(1);
        $lineElement->setColor([0, 0, 0]);
        $this->currentPage->addElement($lineElement);

        $this->currentY -= 10;
        $this->currentX = $this->marginLeft;
    }

    /**
     * Layout a list.
     *
     * @param array<string, string> $styles
     */
    private function layoutList(HtmlElement $element, array $styles): void
    {
        $this->listDepth++;
        $oldListType = $this->listType;
        $this->listType = $element->getTagName();
        $this->listItemNumber = 0;

        $paddingLeft = $this->parseLength($styles['padding-left'] ?? '20pt');
        $this->currentX += $paddingLeft;

        foreach ($element->getChildren() as $child) {
            if ($child->isListItem()) {
                $this->listItemNumber++;
                $this->layoutElement($child);
            }
        }

        $this->currentX -= $paddingLeft;
        $this->listType = $oldListType;
        $this->listDepth--;
    }

    /**
     * Layout a list item.
     *
     * @param array<string, string> $styles
     */
    private function layoutListItem(HtmlElement $element, array $styles): void
    {
        $fontSize = $this->parseLength($styles['font-size'] ?? $this->defaultFontSize . 'pt');
        $this->lineHeight = $fontSize * 1.4;
        $this->checkPageBreak($this->lineHeight);

        // Add bullet or number
        $bullet = $this->listType === 'ol'
            ? $this->listItemNumber . '.'
            : "\xE2\x80\xA2"; // Unicode bullet

        $bulletX = $this->currentX - 15;
        $bulletElement = LayoutElement::text($bullet, $bulletX, $this->currentY);
        $bulletElement->setFontSize($fontSize);
        $this->currentPage->addElement($bulletElement);

        // Layout content
        $content = $element->getContent();
        if ($content !== '' && !$element->hasChildren()) {
            $textElement = new HtmlElement('text', $content);
            $this->layoutText($textElement, $styles);
        } else {
            foreach ($element->getChildren() as $child) {
                $this->layoutElement($child);
            }
        }

        $this->currentY -= $this->lineHeight;
        $this->currentX = $this->marginLeft + ($this->listDepth * 20);
    }

    /**
     * Layout a table.
     *
     * @param array<string, string> $styles
     */
    private function layoutTable(HtmlElement $element, array $styles): void
    {
        $rows = [];
        $this->collectTableRows($element, $rows);

        if (empty($rows)) {
            return;
        }

        // Calculate column widths
        $columnCount = $this->getMaxColumnCount($rows);
        $columnWidth = $this->contentWidth / max(1, $columnCount);

        $cellPadding = $this->parseLength($styles['padding'] ?? '5pt');
        $fontSize = $this->parseLength($styles['font-size'] ?? $this->defaultFontSize . 'pt');
        $rowHeight = $fontSize * 1.4 + ($cellPadding * 2);

        foreach ($rows as $row) {
            $this->checkPageBreak($rowHeight);

            $cellX = $this->marginLeft;
            $cells = $row->getChildren();

            foreach ($cells as $cellIndex => $cell) {
                if (!$cell->isTableCell()) {
                    continue;
                }

                $colspan = $cell->getColspan();
                $cellWidth = $columnWidth * $colspan;
                $cellStyles = $this->getComputedStyles($cell);

                // Draw cell background
                $bgColor = $cellStyles['background-color'] ?? null;
                if ($bgColor !== null) {
                    $rectElement = LayoutElement::rectangle(
                        $cellX,
                        $this->currentY - $rowHeight,
                        $cellWidth,
                        $rowHeight
                    );
                    $rectElement->setBackgroundColor($this->parseColor($bgColor));
                    $this->currentPage->addElement($rectElement);
                }

                // Draw cell border
                $borderStyle = $cellStyles['border'] ?? null;
                if ($borderStyle !== null) {
                    $rectElement = LayoutElement::rectangle(
                        $cellX,
                        $this->currentY - $rowHeight,
                        $cellWidth,
                        $rowHeight
                    );
                    $rectElement->setBorderColor([0, 0, 0]);
                    $rectElement->setLineWidth(1);
                    $this->currentPage->addElement($rectElement);
                }

                // Draw cell text
                $content = $cell->getContent() ?: $cell->getTextContent();
                if ($content !== '') {
                    $textElement = LayoutElement::text(
                        $content,
                        $cellX + $cellPadding,
                        $this->currentY - $cellPadding - ($fontSize * 0.8)
                    );
                    $textElement->setFontSize($fontSize);
                    $textElement->setBold($cell->getTagName() === 'th');
                    $this->currentPage->addElement($textElement);
                }

                $cellX += $cellWidth;
            }

            $this->currentY -= $rowHeight;
        }

        $this->currentX = $this->marginLeft;
    }

    /**
     * Collect table rows from various table structures.
     *
     * @param array<HtmlElement> $rows
     */
    private function collectTableRows(HtmlElement $table, array &$rows): void
    {
        foreach ($table->getChildren() as $child) {
            $tagName = $child->getTagName();

            if ($tagName === 'tr') {
                $rows[] = $child;
            } elseif (in_array($tagName, ['thead', 'tbody', 'tfoot'], true)) {
                $this->collectTableRows($child, $rows);
            }
        }
    }

    /**
     * Get maximum column count from rows.
     *
     * @param array<HtmlElement> $rows
     */
    private function getMaxColumnCount(array $rows): int
    {
        $max = 0;

        foreach ($rows as $row) {
            $count = 0;
            foreach ($row->getChildren() as $cell) {
                if ($cell->isTableCell()) {
                    $count += $cell->getColspan();
                }
            }
            $max = max($max, $count);
        }

        return $max;
    }

    /**
     * Layout an image.
     *
     * @param array<string, string> $styles
     */
    private function layoutImage(HtmlElement $element, array $styles): void
    {
        $src = $element->getSrc() ?? $element->getContent();
        if ($src === '' || $src === null) {
            return;
        }

        $width = $this->parseLength($element->getAttribute('width') ?? $styles['width'] ?? '100');
        $height = $this->parseLength($element->getAttribute('height') ?? $styles['height'] ?? '100');

        // Limit to content width
        if ($width > $this->contentWidth) {
            $ratio = $this->contentWidth / $width;
            $width = $this->contentWidth;
            $height *= $ratio;
        }

        $this->checkPageBreak($height);

        $imageElement = LayoutElement::image(
            $src,
            $this->currentX,
            $this->currentY - $height,
            $width,
            $height
        );
        $this->currentPage->addElement($imageElement);

        $this->currentY -= $height + 10;
        $this->currentX = $this->marginLeft;
    }

    /**
     * Layout a block element.
     *
     * @param array<string, string> $styles
     */
    private function layoutBlock(HtmlElement $element, array $styles): void
    {
        $this->currentX = $this->marginLeft;

        // Draw background if set
        $bgColor = $styles['background-color'] ?? null;
        if ($bgColor !== null) {
            // Would need to calculate block height first
        }

        foreach ($element->getChildren() as $child) {
            $this->layoutElement($child);
        }

        $this->currentX = $this->marginLeft;
    }

    /**
     * Layout a blockquote.
     *
     * @param array<string, string> $styles
     */
    private function layoutBlockquote(HtmlElement $element, array $styles): void
    {
        $marginLeft = $this->parseLength($styles['margin-left'] ?? '20pt');
        $paddingLeft = $this->parseLength($styles['padding-left'] ?? '10pt');
        $oldColor = $this->currentColor;
        $this->currentColor = $this->parseColor($styles['color'] ?? '#666666');

        $this->currentX += $marginLeft;

        // Draw left border
        $startY = $this->currentY;

        foreach ($element->getChildren() as $child) {
            $this->layoutElement($child);
        }

        $endY = $this->currentY;

        // Add left border line
        $borderElement = LayoutElement::line(
            $this->marginLeft + $marginLeft - 5,
            $startY,
            0
        );
        $borderElement->setHeight($startY - $endY);
        $borderElement->setLineWidth(3);
        $borderElement->setColor($this->parseColor($styles['border-left'] ?? '#cccccc'));
        $this->currentPage->addElement($borderElement);

        $this->currentX = $this->marginLeft;
        $this->currentColor = $oldColor;
    }

    /**
     * Layout preformatted text.
     *
     * @param array<string, string> $styles
     */
    private function layoutPreformatted(HtmlElement $element, array $styles): void
    {
        $fontFamily = $styles['font-family'] ?? 'Courier';
        $fontSize = $this->parseLength($styles['font-size'] ?? '10pt');
        $padding = $this->parseLength($styles['padding'] ?? '10pt');

        $content = $element->getContent() ?: $element->getTextContent();
        $lines = explode("\n", $content);

        $lineHeight = $fontSize * 1.2;
        $blockHeight = (count($lines) * $lineHeight) + ($padding * 2);

        $this->checkPageBreak($blockHeight);

        // Draw background
        $bgColor = $styles['background-color'] ?? '#f5f5f5';
        $rectElement = LayoutElement::rectangle(
            $this->marginLeft,
            $this->currentY - $blockHeight,
            $this->contentWidth,
            $blockHeight
        );
        $rectElement->setBackgroundColor($this->parseColor($bgColor));
        $this->currentPage->addElement($rectElement);

        // Draw lines
        $y = $this->currentY - $padding;
        foreach ($lines as $line) {
            $textElement = LayoutElement::text(
                $line,
                $this->marginLeft + $padding,
                $y - ($fontSize * 0.8)
            );
            $textElement->setFontFamily($fontFamily);
            $textElement->setFontSize($fontSize);
            $this->currentPage->addElement($textElement);
            $y -= $lineHeight;
        }

        $this->currentY -= $blockHeight + 10;
        $this->currentX = $this->marginLeft;
    }

    /**
     * Layout inline element.
     *
     * @param array<string, string> $styles
     */
    private function layoutInline(HtmlElement $element, array $styles): void
    {
        $tagName = $element->getTagName();

        // Save state
        $oldBold = $this->isBold;
        $oldItalic = $this->isItalic;
        $oldUnderline = $this->isUnderline;
        $oldColor = $this->currentColor;
        $oldHref = $this->linkHref;

        // Apply inline styles
        if (in_array($tagName, ['strong', 'b'], true) || ($styles['font-weight'] ?? '') === 'bold') {
            $this->isBold = true;
        }
        if (in_array($tagName, ['em', 'i'], true) || ($styles['font-style'] ?? '') === 'italic') {
            $this->isItalic = true;
        }
        if ($tagName === 'u' || ($styles['text-decoration'] ?? '') === 'underline') {
            $this->isUnderline = true;
        }
        if ($tagName === 'a') {
            $this->linkHref = $element->getHref();
            $this->currentColor = $this->parseColor($styles['color'] ?? '#0000ff');
            $this->isUnderline = true;
        }
        if (isset($styles['color'])) {
            $this->currentColor = $this->parseColor($styles['color']);
        }

        // Layout content
        $content = $element->getContent();
        if ($content !== '' && !$element->hasChildren()) {
            $textElement = new HtmlElement('text', $content);
            $this->layoutText($textElement, $styles);
        }

        foreach ($element->getChildren() as $child) {
            $this->layoutElement($child);
        }

        // Restore state
        $this->isBold = $oldBold;
        $this->isItalic = $oldItalic;
        $this->isUnderline = $oldUnderline;
        $this->currentColor = $oldColor;
        $this->linkHref = $oldHref;
    }

    /**
     * Get computed styles for an element.
     *
     * @return array<string, string>
     */
    private function getComputedStyles(HtmlElement $element): array
    {
        $styles = [];
        $tagName = $element->getTagName();

        // Apply default styles for tag
        if (isset($this->defaultStyles[$tagName])) {
            foreach ($this->defaultStyles[$tagName] as $prop => $value) {
                $styles[$prop] = (string) $value;
            }
        }

        // Apply element's inline styles
        foreach ($element->getStyles() as $prop => $value) {
            $styles[$prop] = $value;
        }

        return $styles;
    }

    /**
     * Parse a CSS length value to points.
     */
    private function parseLength(string|float $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        if (preg_match('/^(-?[\d.]+)(px|pt|em|rem|%|in|cm|mm)?$/i', $value, $matches)) {
            $number = (float) $matches[1];
            $unit = strtolower($matches[2] ?? 'pt');

            return match ($unit) {
                'px' => $number * 0.75,          // Approximate px to pt
                'pt' => $number,
                'em', 'rem' => $number * $this->defaultFontSize,
                '%' => ($number / 100) * $this->contentWidth,
                'in' => $number * 72,
                'cm' => $number * 28.35,
                'mm' => $number * 2.835,
                default => $number,
            };
        }

        return 0;
    }

    /**
     * Parse a color string.
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
            'gray' => [0.5, 0.5, 0.5],
            'grey' => [0.5, 0.5, 0.5],
        ];

        if (isset($namedColors[strtolower($color)])) {
            return $namedColors[strtolower($color)];
        }

        // Hex color
        if (str_starts_with($color, '#')) {
            $hex = ltrim($color, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if (strlen($hex) === 6) {
                return [
                    hexdec(substr($hex, 0, 2)) / 255,
                    hexdec(substr($hex, 2, 2)) / 255,
                    hexdec(substr($hex, 4, 2)) / 255,
                ];
            }
        }

        return [0, 0, 0];
    }

    /**
     * Estimate text width in points.
     */
    private function estimateTextWidth(string $text, float $fontSize): float
    {
        // Approximate character width as 0.5 * fontSize for proportional fonts
        // This is a simplification - real implementation would use font metrics
        $avgCharWidth = $fontSize * 0.5;

        return strlen($text) * $avgCharWidth;
    }
}
