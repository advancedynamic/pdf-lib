<?php

declare(strict_types=1);

namespace PdfLib\Manipulation;

use PdfLib\Document\PdfDocument;
use PdfLib\Parser\PdfParser;

/**
 * PDF Splitter - Extract and split pages from PDFs.
 *
 * Properly preserves page content, resources, fonts, and images.
 *
 * @example
 * ```php
 * $splitter = new Splitter('document.pdf');
 * $splitter->extractPage(3)->save('page3.pdf');
 * $splitter->splitByPageCount(5, outputDir: './chunks/');
 * ```
 */
final class Splitter
{
    private ?PdfParser $parser = null;
    private string $content = '';
    private string $version = '1.7';

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
     * Get total page count.
     */
    public function getPageCount(): int
    {
        $this->ensureLoaded();
        return $this->parser->getPageCount();
    }

    /**
     * Extract a single page.
     *
     * @param int $pageNumber 1-indexed page number
     */
    public function extractPage(int $pageNumber): SplitResult
    {
        return $this->extractPages([$pageNumber]);
    }

    /**
     * Extract specific pages.
     *
     * @param array<int>|string $pages Page numbers (1-indexed) or range string
     */
    public function extractPages(array|string $pages): SplitResult
    {
        $this->ensureLoaded();

        $pageCount = $this->parser->getPageCount();
        $pageNumbers = $this->resolvePages($pages, $pageCount);

        // Use Merger to properly copy page content
        $merger = new Merger();
        $merger->setVersion($this->version);
        $merger->addContent($this->content, $pageNumbers);

        return new SplitResult($merger->merge());
    }

    /**
     * Split into individual page files.
     *
     * @param string $outputDir Output directory
     * @param string $prefix File prefix
     * @return array<int, string> Created file paths
     */
    public function splitToFiles(string $outputDir, string $prefix = 'page_'): array
    {
        $this->ensureLoaded();

        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
            throw new \RuntimeException("Could not create output directory: $outputDir");
        }

        $files = [];
        $pageCount = $this->getPageCount();

        for ($i = 1; $i <= $pageCount; $i++) {
            $filename = sprintf('%s%s%d.pdf', rtrim($outputDir, '/'), DIRECTORY_SEPARATOR, $i);
            $this->extractPage($i)->save($filename);
            $files[$i] = $filename;
        }

        return $files;
    }

    /**
     * Split by page count (chunks).
     *
     * @param int $pagesPerChunk Pages per output file
     * @return array<int, SplitResult> Array of results
     */
    public function splitByPageCount(int $pagesPerChunk): array
    {
        $this->ensureLoaded();

        if ($pagesPerChunk < 1) {
            throw new \InvalidArgumentException('Pages per chunk must be at least 1');
        }

        $chunks = [];
        $pageCount = $this->getPageCount();

        for ($i = 1; $i <= $pageCount; $i += $pagesPerChunk) {
            $end = min($i + $pagesPerChunk - 1, $pageCount);
            $chunks[] = $this->extractPages(range($i, $end));
        }

        return $chunks;
    }

    /**
     * Split at specific page numbers.
     *
     * @param array<int> $splitPoints Page numbers where splits occur
     * @return array<int, SplitResult>
     */
    public function splitAtPages(array $splitPoints): array
    {
        $this->ensureLoaded();

        sort($splitPoints);
        $pageCount = $this->getPageCount();
        $chunks = [];
        $start = 1;

        foreach ($splitPoints as $splitPoint) {
            if ($splitPoint > $start && $splitPoint <= $pageCount) {
                $chunks[] = $this->extractPages(range($start, $splitPoint - 1));
                $start = $splitPoint;
            }
        }

        // Remaining pages
        if ($start <= $pageCount) {
            $chunks[] = $this->extractPages(range($start, $pageCount));
        }

        return $chunks;
    }

    /**
     * Extract odd pages only.
     */
    public function extractOddPages(): SplitResult
    {
        $this->ensureLoaded();
        $pageCount = $this->getPageCount();
        $oddPages = [];

        for ($i = 1; $i <= $pageCount; $i += 2) {
            $oddPages[] = $i;
        }

        return $this->extractPages($oddPages);
    }

    /**
     * Extract even pages only.
     */
    public function extractEvenPages(): SplitResult
    {
        $this->ensureLoaded();
        $pageCount = $this->getPageCount();
        $evenPages = [];

        for ($i = 2; $i <= $pageCount; $i += 2) {
            $evenPages[] = $i;
        }

        return $this->extractPages($evenPages);
    }

    /**
     * Extract pages in reverse order.
     */
    public function extractReversed(): SplitResult
    {
        $this->ensureLoaded();
        $pageCount = $this->getPageCount();
        return $this->extractPages(range($pageCount, 1, -1));
    }

    /**
     * Extract first N pages.
     */
    public function extractFirst(int $count): SplitResult
    {
        $this->ensureLoaded();
        $count = min($count, $this->getPageCount());
        return $this->extractPages(range(1, $count));
    }

    /**
     * Extract last N pages.
     */
    public function extractLast(int $count): SplitResult
    {
        $this->ensureLoaded();
        $pageCount = $this->getPageCount();
        $count = min($count, $pageCount);
        return $this->extractPages(range($pageCount - $count + 1, $pageCount));
    }

    /**
     * Extract a range of pages.
     *
     * @param int $from Start page (1-indexed, inclusive)
     * @param int $to End page (1-indexed, inclusive)
     */
    public function extractRange(int $from, int $to): SplitResult
    {
        $this->ensureLoaded();
        $pageCount = $this->getPageCount();
        $from = max(1, $from);
        $to = min($to, $pageCount);

        if ($from > $to) {
            throw new \InvalidArgumentException("Invalid range: $from to $to");
        }

        return $this->extractPages(range($from, $to));
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
     * Resolve page selection to array of page numbers.
     *
     * @param array<int>|string $pages
     * @return array<int>
     */
    private function resolvePages(array|string $pages, int $totalPages): array
    {
        if ($pages === 'all') {
            return range(1, $totalPages);
        }

        if (is_array($pages)) {
            return array_filter($pages, fn($p) => $p >= 1 && $p <= $totalPages);
        }

        // Parse range string like "1-5", "1,3,5", "1-3,5,7-9"
        $result = [];
        $parts = explode(',', $pages);

        foreach ($parts as $part) {
            $part = trim($part);
            if (str_contains($part, '-')) {
                [$start, $end] = explode('-', $part, 2);
                $start = (int) $start;
                $end = (int) $end;
                for ($i = $start; $i <= $end && $i <= $totalPages; $i++) {
                    if ($i >= 1) {
                        $result[] = $i;
                    }
                }
            } else {
                $num = (int) $part;
                if ($num >= 1 && $num <= $totalPages) {
                    $result[] = $num;
                }
            }
        }

        return array_unique($result);
    }
}
