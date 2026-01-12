<?php

declare(strict_types=1);

namespace PdfLib\Manipulation;

use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;
use PdfLib\Parser\PdfParser;

/**
 * PDF Stamper - Add watermarks, page numbers, headers/footers.
 *
 * @example
 * ```php
 * $stamper = new Stamper('document.pdf');
 * $stamper->addWatermark('CONFIDENTIAL', rotation: 45, opacity: 0.3)
 *         ->addPageNumbers('Page {page} of {total}', position: 'bottom-center')
 *         ->save('stamped.pdf');
 * ```
 */
final class Stamper
{
    // Position constants
    public const POSITION_TOP_LEFT = 'top-left';
    public const POSITION_TOP_CENTER = 'top-center';
    public const POSITION_TOP_RIGHT = 'top-right';
    public const POSITION_CENTER_LEFT = 'center-left';
    public const POSITION_CENTER = 'center';
    public const POSITION_CENTER_RIGHT = 'center-right';
    public const POSITION_BOTTOM_LEFT = 'bottom-left';
    public const POSITION_BOTTOM_CENTER = 'bottom-center';
    public const POSITION_BOTTOM_RIGHT = 'bottom-right';

    // Layer constants
    public const LAYER_FOREGROUND = 'foreground';
    public const LAYER_BACKGROUND = 'background';

    private ?PdfParser $parser = null;
    private string $content = '';
    private string $version = '1.7';

    /** @var array<int, array{type: string, config: array<string, mixed>, layer: string}> */
    private array $stamps = [];

    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            $this->loadFile($filePath);
        }
    }

    /**
     * Load PDF from file.
     */
    public function loadFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("PDF file not found: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Could not read PDF file: $filePath");
        }

        return $this->loadContent($content);
    }

    /**
     * Load PDF from string content.
     */
    public function loadContent(string $content): self
    {
        if (!str_starts_with($content, '%PDF-')) {
            throw new \InvalidArgumentException('Invalid PDF content');
        }

        $this->content = $content;
        $this->parser = PdfParser::parseString($content);
        $this->version = $this->parser->getVersion();

        return $this;
    }

    /**
     * Add a text stamp.
     *
     * @param array{
     *     fontSize?: float,
     *     color?: array{float, float, float},
     *     opacity?: float,
     *     rotation?: float,
     *     position?: string,
     *     x?: float,
     *     y?: float,
     *     pages?: array<int>|string
     * } $config
     */
    public function addTextStamp(
        string $text,
        array $config = [],
        string $layer = self::LAYER_FOREGROUND
    ): self {
        $this->stamps[] = [
            'type' => 'text',
            'config' => array_merge(['text' => $text], $config),
            'layer' => $layer,
        ];

        return $this;
    }

    /**
     * Add a watermark (centered, rotated text).
     *
     * @param array{
     *     fontSize?: float,
     *     color?: array{float, float, float},
     *     opacity?: float,
     *     pages?: array<int>|string
     * } $config
     */
    public function addWatermark(
        string $text,
        float $rotation = 45.0,
        float $opacity = 0.3,
        array $config = []
    ): self {
        return $this->addTextStamp($text, array_merge([
            'position' => self::POSITION_CENTER,
            'rotation' => $rotation,
            'opacity' => $opacity,
            'fontSize' => 72.0,
        ], $config), self::LAYER_BACKGROUND);
    }

    /**
     * Add a "DRAFT" watermark.
     *
     * @param array<int>|string $pages
     */
    public function addDraftWatermark(array|string $pages = 'all'): self
    {
        return $this->addWatermark('DRAFT', 45.0, 0.3, [
            'color' => [0.8, 0.0, 0.0],
            'pages' => $pages,
        ]);
    }

    /**
     * Add a "CONFIDENTIAL" watermark.
     *
     * @param array<int>|string $pages
     */
    public function addConfidentialWatermark(array|string $pages = 'all'): self
    {
        return $this->addWatermark('CONFIDENTIAL', 45.0, 0.3, [
            'color' => [0.8, 0.0, 0.0],
            'pages' => $pages,
        ]);
    }

    /**
     * Add a "COPY" watermark.
     *
     * @param array<int>|string $pages
     */
    public function addCopyWatermark(array|string $pages = 'all'): self
    {
        return $this->addWatermark('COPY', 45.0, 0.3, [
            'color' => [0.5, 0.5, 0.5],
            'pages' => $pages,
        ]);
    }

    /**
     * Add page numbers.
     *
     * @param string $format Format string with {page} and {total} placeholders
     * @param array{
     *     fontSize?: float,
     *     color?: array{float, float, float},
     *     margin?: float,
     *     pages?: array<int>|string
     * } $config
     */
    public function addPageNumbers(
        string $format = 'Page {page} of {total}',
        string $position = self::POSITION_BOTTOM_CENTER,
        array $config = []
    ): self {
        $this->stamps[] = [
            'type' => 'page_number',
            'config' => array_merge([
                'format' => $format,
                'position' => $position,
                'fontSize' => 10.0,
            ], $config),
            'layer' => self::LAYER_FOREGROUND,
        ];

        return $this;
    }

    /**
     * Add a header.
     *
     * @param array{
     *     fontSize?: float,
     *     color?: array{float, float, float},
     *     margin?: float,
     *     pages?: array<int>|string
     * } $config
     */
    public function addHeader(string $text, array $config = []): self
    {
        return $this->addTextStamp($text, array_merge([
            'position' => self::POSITION_TOP_CENTER,
            'fontSize' => 10.0,
            'margin' => 36.0, // 0.5 inch
        ], $config), self::LAYER_FOREGROUND);
    }

    /**
     * Add a footer.
     *
     * @param array{
     *     fontSize?: float,
     *     color?: array{float, float, float},
     *     margin?: float,
     *     pages?: array<int>|string
     * } $config
     */
    public function addFooter(string $text, array $config = []): self
    {
        return $this->addTextStamp($text, array_merge([
            'position' => self::POSITION_BOTTOM_CENTER,
            'fontSize' => 10.0,
            'margin' => 36.0,
        ], $config), self::LAYER_FOREGROUND);
    }

    /**
     * Add a date stamp.
     *
     * @param string $format PHP date format
     * @param array{
     *     fontSize?: float,
     *     color?: array{float, float, float},
     *     margin?: float,
     *     pages?: array<int>|string
     * } $config
     */
    public function addDateStamp(
        string $format = 'Y-m-d',
        string $position = self::POSITION_TOP_RIGHT,
        array $config = []
    ): self {
        return $this->addTextStamp(date($format), array_merge([
            'position' => $position,
            'fontSize' => 10.0,
            'margin' => 36.0,
        ], $config), self::LAYER_FOREGROUND);
    }

    /**
     * Add a "VOID" stamp.
     *
     * @param array<int>|string $pages
     */
    public function addVoidStamp(array|string $pages = 'all'): self
    {
        return $this->addTextStamp('VOID', [
            'position' => self::POSITION_CENTER,
            'fontSize' => 144.0,
            'color' => [1.0, 0.0, 0.0],
            'opacity' => 0.5,
            'rotation' => 0.0,
            'pages' => $pages,
        ], self::LAYER_FOREGROUND);
    }

    /**
     * Clear all stamps.
     */
    public function clearStamps(): self
    {
        $this->stamps = [];
        return $this;
    }

    /**
     * Apply all stamps and return PDF document.
     */
    public function apply(): PdfDocument
    {
        $this->ensureLoaded();

        $pageDicts = $this->parser->getPages();
        $totalPages = count($pageDicts);

        $document = PdfDocument::create();
        $document->setVersion($this->version);

        foreach ($pageDicts as $pageIndex => $pageDict) {
            $pageNum = $pageIndex + 1;

            // Get page dimensions
            $mediaBox = $pageDict->get('MediaBox');
            $width = 612.0;
            $height = 792.0;

            if ($mediaBox !== null) {
                $box = $mediaBox->toArray();
                $width = (float) ($box[2] ?? 612);
                $height = (float) ($box[3] ?? 792);
            }

            $page = new Page(PageSize::create($width, $height));

            // Apply stamps to this page
            $this->applyStampsToPage($page, $pageNum, $totalPages, $width, $height);

            $document->addPageObject($page);
        }

        return $document;
    }

    /**
     * Apply stamps and save to file.
     */
    public function save(string $outputPath): bool
    {
        $document = $this->apply();
        return file_put_contents($outputPath, $document->render()) !== false;
    }

    /**
     * Apply stamps and save to file (alias for save).
     */
    public function applyToFile(string $outputPath): bool
    {
        return $this->save($outputPath);
    }

    /**
     * Ensure PDF is loaded.
     */
    private function ensureLoaded(): void
    {
        if ($this->parser === null) {
            throw new \RuntimeException('No PDF loaded. Call loadFile() or loadContent() first.');
        }
    }

    /**
     * Apply stamps to a single page.
     */
    private function applyStampsToPage(
        Page $page,
        int $pageNum,
        int $totalPages,
        float $width,
        float $height
    ): void {
        foreach ($this->stamps as $stamp) {
            $config = $stamp['config'];

            // Check if this stamp applies to this page
            $pages = $config['pages'] ?? 'all';
            if (!$this->stampAppliesToPage($pages, $pageNum, $totalPages)) {
                continue;
            }

            // Get text content
            $text = $config['text'] ?? '';
            if ($stamp['type'] === 'page_number') {
                $format = $config['format'] ?? 'Page {page} of {total}';
                $text = str_replace(['{page}', '{total}'], [(string) $pageNum, (string) $totalPages], $format);
            }

            // Calculate position
            $position = $config['position'] ?? self::POSITION_CENTER;
            $margin = $config['margin'] ?? 36.0;
            $fontSize = $config['fontSize'] ?? 12.0;
            [$x, $y] = $this->calculatePosition($position, $width, $height, $margin, $fontSize, $text);

            // Override with explicit x/y if provided
            $x = $config['x'] ?? $x;
            $y = $config['y'] ?? $y;

            // Get color
            $color = $config['color'] ?? [0.0, 0.0, 0.0];

            // Get rotation
            $rotation = $config['rotation'] ?? 0.0;

            // Get opacity
            $opacity = $config['opacity'] ?? 1.0;

            // Add text to page
            $page->addText($text, $x, $y, [
                'fontSize' => $fontSize,
                'color' => $color,
                'rotation' => $rotation,
                'opacity' => $opacity,
            ]);
        }
    }

    /**
     * Check if stamp applies to a specific page.
     *
     * @param array<int>|string $pages
     */
    private function stampAppliesToPage(array|string $pages, int $pageNum, int $totalPages): bool
    {
        if ($pages === 'all') {
            return true;
        }

        if ($pages === 'first') {
            return $pageNum === 1;
        }

        if ($pages === 'last') {
            return $pageNum === $totalPages;
        }

        if ($pages === 'odd') {
            return $pageNum % 2 === 1;
        }

        if ($pages === 'even') {
            return $pageNum % 2 === 0;
        }

        if (is_array($pages)) {
            return in_array($pageNum, $pages, true);
        }

        return false;
    }

    /**
     * Calculate position based on position constant.
     *
     * @return array{float, float}
     */
    private function calculatePosition(
        string $position,
        float $width,
        float $height,
        float $margin,
        float $fontSize,
        string $text
    ): array {
        // Estimate text width (rough approximation)
        $textWidth = strlen($text) * $fontSize * 0.5;

        return match ($position) {
            self::POSITION_TOP_LEFT => [$margin, $height - $margin],
            self::POSITION_TOP_CENTER => [($width - $textWidth) / 2, $height - $margin],
            self::POSITION_TOP_RIGHT => [$width - $margin - $textWidth, $height - $margin],
            self::POSITION_CENTER_LEFT => [$margin, $height / 2],
            self::POSITION_CENTER => [($width - $textWidth) / 2, $height / 2],
            self::POSITION_CENTER_RIGHT => [$width - $margin - $textWidth, $height / 2],
            self::POSITION_BOTTOM_LEFT => [$margin, $margin + $fontSize],
            self::POSITION_BOTTOM_CENTER => [($width - $textWidth) / 2, $margin + $fontSize],
            self::POSITION_BOTTOM_RIGHT => [$width - $margin - $textWidth, $margin + $fontSize],
            default => [($width - $textWidth) / 2, $height / 2],
        };
    }
}
