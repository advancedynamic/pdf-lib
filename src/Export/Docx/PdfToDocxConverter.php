<?php

declare(strict_types=1);

namespace PdfLib\Export\Docx;

use PdfLib\Parser\PdfParser;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfStream;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfString;
use PdfLib\Parser\Object\PdfName;

/**
 * PDF to DOCX Converter.
 *
 * Extracts text content from PDF documents and creates DOCX files.
 *
 * @example
 * ```php
 * // Simple conversion
 * $converter = new PdfToDocxConverter();
 * $converter->convert('document.pdf', 'output.docx');
 *
 * // With options
 * $converter = PdfToDocxConverter::create()
 *     ->setPageSize('A4')
 *     ->setFont('Times New Roman', 12)
 *     ->preserveLayout(true);
 *
 * $converter->convert('document.pdf', 'output.docx');
 *
 * // Get DOCX content as binary
 * $content = $converter->convertToString('document.pdf');
 * ```
 */
class PdfToDocxConverter
{
    private string $pageSize = 'A4';
    private string $fontName = 'Arial';
    private int $fontSize = 12;
    private bool $preserveLayout = false;
    private bool $extractImages = false;
    private ?array $pageRange = null;

    /**
     * Create a new converter instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set page size.
     */
    public function setPageSize(string $size): self
    {
        $this->pageSize = $size;

        return $this;
    }

    /**
     * Set default font.
     */
    public function setFont(string $name, int $size = 12): self
    {
        $this->fontName = $name;
        $this->fontSize = $size;

        return $this;
    }

    /**
     * Try to preserve PDF layout (experimental).
     */
    public function preserveLayout(bool $preserve = true): self
    {
        $this->preserveLayout = $preserve;

        return $this;
    }

    /**
     * Extract images from PDF (experimental).
     */
    public function extractImages(bool $extract = true): self
    {
        $this->extractImages = $extract;

        return $this;
    }

    /**
     * Set pages to convert.
     *
     * @param array<int>|null $pages Array of page numbers (1-based) or null for all
     */
    public function setPages(?array $pages): self
    {
        $this->pageRange = $pages;

        return $this;
    }

    /**
     * Convert PDF file to DOCX file.
     */
    public function convert(string $pdfPath, string $docxPath): void
    {
        if (!file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: $pdfPath");
        }

        $writer = $this->createDocxWriter($pdfPath);
        $writer->save($docxPath);
    }

    /**
     * Convert PDF file to DOCX binary string.
     */
    public function convertToString(string $pdfPath): string
    {
        if (!file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: $pdfPath");
        }

        $writer = $this->createDocxWriter($pdfPath);

        return $writer->getContent();
    }

    /**
     * Convert PDF content to DOCX binary string.
     */
    public function convertContent(string $pdfContent): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tempFile, $pdfContent);

        try {
            $result = $this->convertToString($tempFile);
        } finally {
            unlink($tempFile);
        }

        return $result;
    }

    /**
     * Create DOCX writer with content from PDF.
     */
    private function createDocxWriter(string $pdfPath): DocxWriter
    {
        $parser = PdfParser::parseFile($pdfPath);
        $pageCount = $parser->getPageCount();

        $writer = new DocxWriter();

        // Set page size
        if (strtoupper($this->pageSize) === 'A4') {
            $writer->setPageSizeA4();
        } else {
            $writer->setPageSizeLetter();
        }

        $writer->setDefaultFont($this->fontName, $this->fontSize);

        // Determine pages to process
        $pagesToProcess = $this->pageRange ?? range(1, $pageCount);

        foreach ($pagesToProcess as $pageNum) {
            if ($pageNum < 1 || $pageNum > $pageCount) {
                continue;
            }

            // Add page break between pages (except first)
            if ($pageNum > $pagesToProcess[0]) {
                $writer->addPageBreak();
            }

            // Extract and add page content
            $this->extractPageContent($parser, $pageNum, $writer);
        }

        return $writer;
    }

    /**
     * Extract content from a page and add to writer.
     */
    private function extractPageContent(PdfParser $parser, int $pageNum, DocxWriter $writer): void
    {
        $page = $parser->getPage($pageNum);
        if ($page === null) {
            return;
        }

        $contents = $page->get('Contents');
        if ($contents === null) {
            return;
        }

        // Resolve content stream(s)
        $contentData = $this->getContentStream($parser, $contents);
        if ($contentData === '') {
            return;
        }

        // Parse and extract text from content stream
        $textBlocks = $this->parseContentStream($contentData, $parser, $page);

        // Add text blocks to writer
        foreach ($textBlocks as $block) {
            if (trim($block['text']) === '') {
                continue;
            }

            $paragraph = new DocxParagraph();

            // Create run with formatting
            $run = new DocxRun($block['text']);

            if (!empty($block['bold'])) {
                $run->setBold(true);
            }
            if (!empty($block['italic'])) {
                $run->setItalic(true);
            }
            if (!empty($block['fontSize'])) {
                $run->setFontSize((int) $block['fontSize']);
            }

            $paragraph->addRunObject($run);
            $writer->addParagraph($paragraph);
        }
    }

    /**
     * Get content stream data.
     *
     * @return string Decoded content stream
     */
    private function getContentStream(PdfParser $parser, mixed $contents): string
    {
        $resolved = $parser->resolveReference($contents);

        if ($resolved instanceof PdfStream) {
            return $resolved->getDecodedContent();
        }

        if ($resolved instanceof PdfArray) {
            $data = '';
            foreach ($resolved->getValues() as $item) {
                $itemResolved = $parser->resolveReference($item);
                if ($itemResolved instanceof PdfStream) {
                    $data .= $itemResolved->getDecodedContent() . "\n";
                }
            }

            return $data;
        }

        return '';
    }

    /**
     * Parse content stream and extract text blocks.
     *
     * @return array<array{text: string, x?: float, y?: float, fontSize?: float, bold?: bool, italic?: bool}>
     */
    private function parseContentStream(string $content, PdfParser $parser, PdfDictionary $page): array
    {
        $textBlocks = [];
        $currentText = '';
        $currentFontSize = $this->fontSize;
        $currentFont = '';
        $isBold = false;
        $isItalic = false;

        // Get font resources
        $fonts = $this->getPageFonts($parser, $page);

        // Split into tokens and parse
        $lines = preg_split('/[\r\n]+/', $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Font selection: /F1 12 Tf
            if (preg_match('/\/([A-Za-z0-9]+)\s+([\d.]+)\s+Tf/', $line, $matches)) {
                $fontName = $matches[1];
                $currentFontSize = (float) $matches[2];

                // Check font properties
                if (isset($fonts[$fontName])) {
                    $fontInfo = $fonts[$fontName];
                    $baseFontName = strtolower($fontInfo['baseFont'] ?? '');
                    $isBold = str_contains($baseFontName, 'bold');
                    $isItalic = str_contains($baseFontName, 'italic') || str_contains($baseFontName, 'oblique');
                }
            }

            // Text showing operators
            // (text) Tj - show string
            if (preg_match_all('/\(([^)]*)\)\s*Tj/', $line, $matches)) {
                foreach ($matches[1] as $text) {
                    $decoded = $this->decodeString($text);
                    $currentText .= $decoded;
                }
            }

            // [(text) num (text)] TJ - show strings with positioning
            if (preg_match('/\[(.*?)\]\s*TJ/s', $line, $matches)) {
                $tjContent = $matches[1];

                // Extract strings from TJ array
                preg_match_all('/\(([^)]*)\)/', $tjContent, $stringMatches);
                foreach ($stringMatches[1] as $text) {
                    $decoded = $this->decodeString($text);
                    $currentText .= $decoded;
                }
            }

            // Text newline operators
            if (preg_match('/\bT\*\b/', $line) || preg_match('/\'/', $line)) {
                if ($currentText !== '') {
                    $textBlocks[] = [
                        'text' => $currentText,
                        'fontSize' => $currentFontSize,
                        'bold' => $isBold,
                        'italic' => $isItalic,
                    ];
                    $currentText = '';
                }
            }

            // End text object
            if (preg_match('/\bET\b/', $line)) {
                if ($currentText !== '') {
                    $textBlocks[] = [
                        'text' => $currentText,
                        'fontSize' => $currentFontSize,
                        'bold' => $isBold,
                        'italic' => $isItalic,
                    ];
                    $currentText = '';
                }
            }
        }

        // Add remaining text
        if ($currentText !== '') {
            $textBlocks[] = [
                'text' => $currentText,
                'fontSize' => $currentFontSize,
                'bold' => $isBold,
                'italic' => $isItalic,
            ];
        }

        return $textBlocks;
    }

    /**
     * Get font resources from page.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getPageFonts(PdfParser $parser, PdfDictionary $page): array
    {
        $fonts = [];

        $resources = $page->get('Resources');
        if ($resources === null) {
            return $fonts;
        }

        $resources = $parser->resolveReference($resources);
        if (!$resources instanceof PdfDictionary) {
            return $fonts;
        }

        $fontDict = $resources->get('Font');
        if ($fontDict === null) {
            return $fonts;
        }

        $fontDict = $parser->resolveReference($fontDict);
        if (!$fontDict instanceof PdfDictionary) {
            return $fonts;
        }

        foreach ($fontDict->getKeys() as $fontKey) {
            $font = $parser->resolveReference($fontDict->get($fontKey));
            if ($font instanceof PdfDictionary) {
                $baseFont = $font->get('BaseFont');
                if ($baseFont instanceof PdfName) {
                    $fonts[$fontKey] = [
                        'baseFont' => $baseFont->getValue(),
                    ];
                }
            }
        }

        return $fonts;
    }

    /**
     * Decode a PDF string.
     */
    private function decodeString(string $text): string
    {
        // Handle escape sequences
        $text = str_replace(
            ['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'],
            ["\n", "\r", "\t", '(', ')', '\\'],
            $text
        );

        // Handle octal escapes
        $text = preg_replace_callback(
            '/\\\\([0-7]{1,3})/',
            fn ($m) => chr((int) octdec($m[1])),
            $text
        );

        return $text;
    }
}
