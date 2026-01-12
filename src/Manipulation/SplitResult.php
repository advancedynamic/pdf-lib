<?php

declare(strict_types=1);

namespace PdfLib\Manipulation;

use PdfLib\Parser\PdfParser;

/**
 * Result of a manipulation operation (split, crop, rotate, etc.) containing PDF content.
 *
 * Provides methods to save, retrieve, and inspect the PDF data.
 */
final class SplitResult
{
    private string $content;
    private ?int $pageCount = null;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    /**
     * Save the result to a file.
     */
    public function save(string $filePath): bool
    {
        return file_put_contents($filePath, $this->content) !== false;
    }

    /**
     * Get the raw PDF content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the size of the PDF content in bytes.
     */
    public function getSize(): int
    {
        return strlen($this->content);
    }

    /**
     * Get the page count of the PDF.
     */
    public function getPageCount(): int
    {
        if ($this->pageCount === null) {
            $parser = PdfParser::parseString($this->content);
            $this->pageCount = $parser->getPageCount();
        }

        return $this->pageCount;
    }

    /**
     * Output the PDF content directly (for streaming).
     */
    public function output(): void
    {
        echo $this->content;
    }
}
