<?php

declare(strict_types=1);

namespace PdfLib\Export\Xlsx;

use PdfLib\Parser\PdfParser;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfStream;
use PdfLib\Parser\Object\PdfArray;

/**
 * PDF to XLSX Converter.
 *
 * Extracts tabular data from PDF documents and creates XLSX files.
 *
 * @example
 * ```php
 * // Simple conversion - extract all tables
 * $converter = new PdfToXlsxConverter();
 * $converter->convert('document.pdf', 'output.xlsx');
 *
 * // With options
 * $converter = PdfToXlsxConverter::create()
 *     ->setSheetPerPage(true)
 *     ->detectTables(true);
 *
 * $converter->convert('document.pdf', 'output.xlsx');
 *
 * // Get XLSX content as binary
 * $content = $converter->convertToString('document.pdf');
 * ```
 */
class PdfToXlsxConverter
{
    private bool $sheetPerPage = false;
    private bool $detectTables = true;
    private ?array $pageRange = null;
    private float $columnTolerance = 10.0;  // Points - text within this tolerance is same column
    private float $rowTolerance = 5.0;      // Points - text within this tolerance is same row

    /**
     * Create a new converter instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Create one sheet per PDF page.
     */
    public function setSheetPerPage(bool $sheetPerPage = true): self
    {
        $this->sheetPerPage = $sheetPerPage;

        return $this;
    }

    /**
     * Try to detect and format tables (experimental).
     */
    public function detectTables(bool $detect = true): self
    {
        $this->detectTables = $detect;

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
     * Set column detection tolerance in points.
     */
    public function setColumnTolerance(float $tolerance): self
    {
        $this->columnTolerance = $tolerance;

        return $this;
    }

    /**
     * Set row detection tolerance in points.
     */
    public function setRowTolerance(float $tolerance): self
    {
        $this->rowTolerance = $tolerance;

        return $this;
    }

    /**
     * Convert PDF file to XLSX file.
     */
    public function convert(string $pdfPath, string $xlsxPath): void
    {
        if (!file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: $pdfPath");
        }

        $writer = $this->createXlsxWriter($pdfPath);
        $writer->save($xlsxPath);
    }

    /**
     * Convert PDF file to XLSX binary string.
     */
    public function convertToString(string $pdfPath): string
    {
        if (!file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: $pdfPath");
        }

        $writer = $this->createXlsxWriter($pdfPath);

        return $writer->getContent();
    }

    /**
     * Convert PDF content to XLSX binary string.
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
     * Create XLSX writer with content from PDF.
     */
    private function createXlsxWriter(string $pdfPath): XlsxWriter
    {
        $parser = PdfParser::parseFile($pdfPath);
        $pageCount = $parser->getPageCount();

        $writer = new XlsxWriter();

        // Determine pages to process
        $pagesToProcess = $this->pageRange ?? range(1, $pageCount);

        if ($this->sheetPerPage) {
            // One sheet per page
            foreach ($pagesToProcess as $pageNum) {
                if ($pageNum < 1 || $pageNum > $pageCount) {
                    continue;
                }

                $sheet = $writer->addSheet("Page {$pageNum}");
                $this->extractPageToSheet($parser, $pageNum, $sheet);
            }
        } else {
            // All pages in one sheet
            $sheet = $writer->addSheet('Sheet1');
            $currentRow = 1;

            foreach ($pagesToProcess as $pageNum) {
                if ($pageNum < 1 || $pageNum > $pageCount) {
                    continue;
                }

                // Add page header if not first page
                if ($currentRow > 1) {
                    $currentRow++; // Empty row
                    $sheet->setCell($currentRow, 1, "--- Page {$pageNum} ---", 1);
                    $currentRow++;
                }

                $currentRow = $this->extractPageToSheet($parser, $pageNum, $sheet, $currentRow);
            }
        }

        return $writer;
    }

    /**
     * Extract page content to sheet.
     *
     * @return int Next available row number
     */
    private function extractPageToSheet(
        PdfParser $parser,
        int $pageNum,
        XlsxSheet $sheet,
        int $startRow = 1
    ): int {
        $page = $parser->getPage($pageNum);
        if ($page === null) {
            return $startRow;
        }

        $contents = $page->get('Contents');
        if ($contents === null) {
            return $startRow;
        }

        // Resolve content stream(s)
        $contentData = $this->getContentStream($parser, $contents);
        if ($contentData === '') {
            return $startRow;
        }

        // Extract positioned text
        $textItems = $this->parseContentStream($contentData);

        if (empty($textItems)) {
            return $startRow;
        }

        if ($this->detectTables) {
            // Try to organize text into a grid
            return $this->organizeAsTable($textItems, $sheet, $startRow);
        } else {
            // Just add text line by line
            return $this->addTextLines($textItems, $sheet, $startRow);
        }
    }

    /**
     * Organize text items as a table based on positions.
     *
     * @param array<array{text: string, x: float, y: float}> $textItems
     * @return int Next row number
     */
    private function organizeAsTable(array $textItems, XlsxSheet $sheet, int $startRow): int
    {
        if (empty($textItems)) {
            return $startRow;
        }

        // Sort by y (descending - PDF y is bottom-up) then x (ascending)
        usort($textItems, function ($a, $b) {
            $yDiff = $b['y'] - $a['y'];
            if (abs($yDiff) > $this->rowTolerance) {
                return $yDiff > 0 ? 1 : -1;
            }

            return $a['x'] <=> $b['x'];
        });

        // Group into rows by y position
        $rows = [];
        $currentRow = [];
        $currentY = $textItems[0]['y'];

        foreach ($textItems as $item) {
            if (abs($item['y'] - $currentY) > $this->rowTolerance) {
                if (!empty($currentRow)) {
                    $rows[] = $currentRow;
                }
                $currentRow = [];
                $currentY = $item['y'];
            }
            $currentRow[] = $item;
        }

        if (!empty($currentRow)) {
            $rows[] = $currentRow;
        }

        // Find column positions
        $columnPositions = $this->detectColumnPositions($rows);

        // Write to sheet
        $rowNum = $startRow;

        foreach ($rows as $row) {
            // Assign each item to a column
            $cellsByColumn = [];

            foreach ($row as $item) {
                $colIndex = $this->findColumnIndex($item['x'], $columnPositions);
                if (!isset($cellsByColumn[$colIndex])) {
                    $cellsByColumn[$colIndex] = [];
                }
                $cellsByColumn[$colIndex][] = $item['text'];
            }

            // Write cells
            foreach ($cellsByColumn as $col => $texts) {
                $sheet->setCell($rowNum, $col, implode(' ', $texts));
            }

            $rowNum++;
        }

        return $rowNum;
    }

    /**
     * Detect column positions from rows of text.
     *
     * @param array<array<array{text: string, x: float, y: float}>> $rows
     * @return array<float> Column x positions
     */
    private function detectColumnPositions(array $rows): array
    {
        // Collect all x positions
        $xPositions = [];

        foreach ($rows as $row) {
            foreach ($row as $item) {
                $found = false;
                foreach ($xPositions as $existingX) {
                    if (abs($item['x'] - $existingX) <= $this->columnTolerance) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $xPositions[] = $item['x'];
                }
            }
        }

        sort($xPositions);

        return $xPositions;
    }

    /**
     * Find column index for an x position.
     *
     * @param array<float> $columnPositions
     */
    private function findColumnIndex(float $x, array $columnPositions): int
    {
        foreach ($columnPositions as $index => $colX) {
            if (abs($x - $colX) <= $this->columnTolerance) {
                return $index + 1; // 1-based columns
            }
        }

        // Find closest column
        $minDist = PHP_FLOAT_MAX;
        $closestIndex = 1;

        foreach ($columnPositions as $index => $colX) {
            $dist = abs($x - $colX);
            if ($dist < $minDist) {
                $minDist = $dist;
                $closestIndex = $index + 1;
            }
        }

        return $closestIndex;
    }

    /**
     * Add text items as simple lines.
     *
     * @param array<array{text: string, x: float, y: float}> $textItems
     * @return int Next row number
     */
    private function addTextLines(array $textItems, XlsxSheet $sheet, int $startRow): int
    {
        // Sort by y (descending) then x
        usort($textItems, function ($a, $b) {
            $yDiff = $b['y'] - $a['y'];
            if (abs($yDiff) > $this->rowTolerance) {
                return $yDiff > 0 ? 1 : -1;
            }

            return $a['x'] <=> $b['x'];
        });

        // Group by y into lines
        $lines = [];
        $currentLine = [];
        $currentY = $textItems[0]['y'] ?? 0;

        foreach ($textItems as $item) {
            if (abs($item['y'] - $currentY) > $this->rowTolerance) {
                if (!empty($currentLine)) {
                    $lines[] = implode(' ', array_column($currentLine, 'text'));
                }
                $currentLine = [];
                $currentY = $item['y'];
            }
            $currentLine[] = $item;
        }

        if (!empty($currentLine)) {
            $lines[] = implode(' ', array_column($currentLine, 'text'));
        }

        // Write to sheet
        $rowNum = $startRow;
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $sheet->setCell($rowNum, 1, $line);
                $rowNum++;
            }
        }

        return $rowNum;
    }

    /**
     * Get content stream data.
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
     * Parse content stream and extract positioned text.
     *
     * @return array<array{text: string, x: float, y: float}>
     */
    private function parseContentStream(string $content): array
    {
        $textItems = [];

        // Track text position
        $currentX = 0.0;
        $currentY = 0.0;
        $textMatrix = [1, 0, 0, 1, 0, 0]; // a, b, c, d, e, f

        // State stack for graphics state
        $stateStack = [];

        $lines = preg_split('/[\r\n]+/', $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Save graphics state
            if ($line === 'q') {
                $stateStack[] = [$currentX, $currentY, $textMatrix];
            }

            // Restore graphics state
            if ($line === 'Q') {
                if (!empty($stateStack)) {
                    [$currentX, $currentY, $textMatrix] = array_pop($stateStack);
                }
            }

            // Text matrix: a b c d e f Tm
            if (preg_match('/^([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+Tm$/', $line, $matches)) {
                $textMatrix = [
                    (float) $matches[1],
                    (float) $matches[2],
                    (float) $matches[3],
                    (float) $matches[4],
                    (float) $matches[5],
                    (float) $matches[6],
                ];
                $currentX = $textMatrix[4];
                $currentY = $textMatrix[5];
            }

            // Text position: x y Td
            if (preg_match('/^([\d.\-]+)\s+([\d.\-]+)\s+Td$/', $line, $matches)) {
                $currentX += (float) $matches[1];
                $currentY += (float) $matches[2];
            }

            // Text position with leading: x y TD
            if (preg_match('/^([\d.\-]+)\s+([\d.\-]+)\s+TD$/', $line, $matches)) {
                $currentX += (float) $matches[1];
                $currentY += (float) $matches[2];
            }

            // Show string: (text) Tj
            if (preg_match_all('/\(([^)]*)\)\s*Tj/', $line, $matches)) {
                foreach ($matches[1] as $text) {
                    $decoded = $this->decodeString($text);
                    if (trim($decoded) !== '') {
                        $textItems[] = [
                            'text' => $decoded,
                            'x' => $currentX,
                            'y' => $currentY,
                        ];
                    }
                }
            }

            // Show strings with positioning: [...] TJ
            if (preg_match('/\[(.*?)\]\s*TJ/s', $line, $matches)) {
                $tjContent = $matches[1];
                preg_match_all('/\(([^)]*)\)/', $tjContent, $stringMatches);

                $combinedText = '';
                foreach ($stringMatches[1] as $text) {
                    $combinedText .= $this->decodeString($text);
                }

                if (trim($combinedText) !== '') {
                    $textItems[] = [
                        'text' => $combinedText,
                        'x' => $currentX,
                        'y' => $currentY,
                    ];
                }
            }
        }

        return $textItems;
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
