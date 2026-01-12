<?php

declare(strict_types=1);

namespace PdfLib\Import\Xlsx;

use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;
use ZipArchive;
use DOMDocument;
use DOMXPath;
use DOMElement;

/**
 * XLSX to PDF Converter.
 *
 * Converts Microsoft Excel XLSX files to PDF format using pure PHP.
 * XLSX files are ZIP archives containing XML files following the
 * SpreadsheetML specification.
 */
class XlsxToPdfConverter
{
    private PageSize $pageSize;
    private float $marginTop = 50;
    private float $marginRight = 50;
    private float $marginBottom = 50;
    private float $marginLeft = 50;
    private string $defaultFontFamily = 'Helvetica';
    private float $defaultFontSize = 10;
    private bool $landscape = false;
    private bool $sheetPerPage = false;
    private bool $showGridlines = true;
    private bool $showHeaders = true;

    /** @var array<int> Sheets to convert (empty = all, 1-based) */
    private array $sheets = [];

    public function __construct()
    {
        $this->pageSize = PageSize::a4();
    }

    /**
     * Create a new converter instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Convert XLSX file to PDF file.
     */
    public function convert(string $xlsxPath, string $pdfPath): void
    {
        $pdf = $this->toPdfDocument($xlsxPath);
        $pdf->save($pdfPath);
    }

    /**
     * Convert XLSX file to PDF and return as binary string.
     */
    public function convertToString(string $xlsxPath): string
    {
        $pdf = $this->toPdfDocument($xlsxPath);
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
        if ($tempFile === false) {
            throw new \RuntimeException('Could not create temp file');
        }
        $pdf->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        if ($content === false) {
            throw new \RuntimeException('Could not read temp file');
        }

        return $content;
    }

    /**
     * Convert XLSX file to PdfDocument object.
     */
    public function toPdfDocument(string $xlsxPath): PdfDocument
    {
        if (!file_exists($xlsxPath)) {
            throw new \InvalidArgumentException("XLSX file not found: $xlsxPath");
        }

        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            throw new \RuntimeException("Could not open XLSX file: $xlsxPath");
        }

        // Read shared strings
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $sharedStrings = $sharedStringsXml ? $this->parseSharedStrings($sharedStringsXml) : [];

        // Read workbook to get sheet names
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        if ($workbookXml === false) {
            $zip->close();
            throw new \RuntimeException("Invalid XLSX file: missing workbook.xml");
        }

        $sheetInfo = $this->parseWorkbook($workbookXml);

        // Parse each sheet
        $allSheets = [];
        foreach ($sheetInfo as $index => $info) {
            $sheetNum = $index + 1;

            // Skip if specific sheets are requested and this isn't one
            if (!empty($this->sheets) && !in_array($sheetNum, $this->sheets, true)) {
                continue;
            }

            $sheetXml = $zip->getFromName("xl/worksheets/sheet{$sheetNum}.xml");
            if ($sheetXml !== false) {
                $allSheets[] = [
                    'name' => $info['name'],
                    'data' => $this->parseSheet($sheetXml, $sharedStrings),
                ];
            }
        }

        $zip->close();

        return $this->renderToPdf($allSheets);
    }

    /**
     * Set page size.
     *
     * @param string|PageSize $size Page size name or PageSize object
     */
    public function setPageSize(string|PageSize $size): self
    {
        if (is_string($size)) {
            $this->pageSize = match (strtolower($size)) {
                'a3' => PageSize::a3(),
                'a4' => PageSize::a4(),
                'a5' => PageSize::a5(),
                'letter' => PageSize::letter(),
                'legal' => PageSize::legal(),
                default => PageSize::a4(),
            };
        } else {
            $this->pageSize = $size;
        }

        return $this;
    }

    /**
     * Set landscape orientation.
     */
    public function setLandscape(bool $landscape = true): self
    {
        $this->landscape = $landscape;

        return $this;
    }

    /**
     * Set page margins.
     */
    public function setMargins(float $top, float $right, float $bottom, float $left): self
    {
        $this->marginTop = $top;
        $this->marginRight = $right;
        $this->marginBottom = $bottom;
        $this->marginLeft = $left;

        return $this;
    }

    /**
     * Set default font.
     */
    public function setDefaultFont(string $family, float $size = 10): self
    {
        $this->defaultFontFamily = $family;
        $this->defaultFontSize = $size;

        return $this;
    }

    /**
     * Create a new page for each sheet.
     */
    public function setSheetPerPage(bool $perPage = true): self
    {
        $this->sheetPerPage = $perPage;

        return $this;
    }

    /**
     * Show or hide gridlines.
     */
    public function showGridlines(bool $show = true): self
    {
        $this->showGridlines = $show;

        return $this;
    }

    /**
     * Show or hide row/column headers.
     */
    public function showHeaders(bool $show = true): self
    {
        $this->showHeaders = $show;

        return $this;
    }

    /**
     * Set specific sheets to convert.
     *
     * @param array<int> $sheets Sheet numbers (1-based)
     */
    public function setSheets(array $sheets): self
    {
        $this->sheets = $sheets;

        return $this;
    }

    /**
     * Parse shared strings XML.
     *
     * @return array<string>
     */
    private function parseSharedStrings(string $xml): array
    {
        $strings = [];

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $items = $xpath->query('//x:si');
        if ($items !== false) {
            foreach ($items as $item) {
                $text = '';
                $tNodes = $xpath->query('.//x:t', $item);
                if ($tNodes !== false) {
                    foreach ($tNodes as $t) {
                        $text .= $t->nodeValue ?? '';
                    }
                }
                $strings[] = $text;
            }
        }

        return $strings;
    }

    /**
     * Parse workbook XML to get sheet info.
     *
     * @return array<array{name: string}>
     */
    private function parseWorkbook(string $xml): array
    {
        $sheets = [];

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $sheetNodes = $xpath->query('//x:sheet');
        if ($sheetNodes !== false) {
            foreach ($sheetNodes as $sheet) {
                if ($sheet instanceof DOMElement) {
                    $sheets[] = [
                        'name' => $sheet->getAttribute('name'),
                    ];
                }
            }
        }

        return $sheets;
    }

    /**
     * Parse a worksheet XML.
     *
     * @param array<string> $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function parseSheet(string $xml, array $sharedStrings): array
    {
        $data = [];

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = $xpath->query('//x:sheetData/x:row');
        if ($rows !== false) {
            foreach ($rows as $row) {
                if (!$row instanceof DOMElement) {
                    continue;
                }
                $rowNum = (int) $row->getAttribute('r');
                $data[$rowNum] = [];

                $cells = $xpath->query('./x:c', $row);
                if ($cells !== false) {
                    foreach ($cells as $cell) {
                        if (!$cell instanceof DOMElement) {
                            continue;
                        }
                        $cellRef = $cell->getAttribute('r');
                        $colNum = $this->columnToNumber($cellRef);

                        $value = '';
                        $type = $cell->getAttribute('t');

                        $vNode = $xpath->query('./x:v', $cell);
                        if ($vNode !== false && $vNode->length > 0) {
                            $item = $vNode->item(0);
                            $rawValue = $item !== null ? ($item->nodeValue ?? '') : '';

                            if ($type === 's') {
                                // Shared string
                                $index = (int) $rawValue;
                                $value = $sharedStrings[$index] ?? '';
                            } elseif ($type === 'b') {
                                // Boolean
                                $value = $rawValue === '1' ? 'TRUE' : 'FALSE';
                            } else {
                                // Number or inline string
                                $value = $rawValue;
                            }
                        }

                        // Check for inline string
                        if ($type === 'inlineStr') {
                            $isNode = $xpath->query('./x:is/x:t', $cell);
                            if ($isNode !== false && $isNode->length > 0) {
                                $item = $isNode->item(0);
                                $value = $item !== null ? ($item->nodeValue ?? '') : '';
                            }
                        }

                        // Check for formula result
                        $fNode = $xpath->query('./x:f', $cell);
                        if ($fNode !== false && $fNode->length > 0 && $value === '') {
                            $item = $fNode->item(0);
                            // Formula without cached value
                            $value = '=' . ($item !== null ? ($item->nodeValue ?? '') : '');
                        }

                        $data[$rowNum][$colNum] = $value;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Convert column reference to number.
     */
    private function columnToNumber(string $cellRef): int
    {
        preg_match('/^([A-Z]+)/', $cellRef, $matches);
        $col = $matches[1] ?? 'A';

        $num = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $num = $num * 26 + (ord($col[$i]) - ord('A') + 1);
        }

        return $num;
    }

    /**
     * Convert column number to letter.
     */
    private function numberToColumn(int $num): string
    {
        $col = '';
        while ($num > 0) {
            $num--;
            $col = chr(ord('A') + ($num % 26)) . $col;
            $num = (int) ($num / 26);
        }

        return $col;
    }

    /**
     * Render parsed sheets to PDF.
     *
     * @param array<array{name: string, data: array<int, array<int, string>>}> $sheets
     */
    private function renderToPdf(array $sheets): PdfDocument
    {
        $pdf = PdfDocument::create();

        $pageSize = $this->landscape ? $this->pageSize->landscape() : $this->pageSize;
        $pageWidth = $pageSize->getWidth();
        $pageHeight = $pageSize->getHeight();
        $contentWidth = $pageWidth - $this->marginLeft - $this->marginRight;

        foreach ($sheets as $sheetIndex => $sheet) {
            $sheetName = $sheet['name'];
            $data = $sheet['data'];

            if (empty($data)) {
                continue;
            }

            // Calculate column widths
            $maxCol = 0;
            $maxRow = 0;
            foreach ($data as $rowNum => $row) {
                $maxRow = max($maxRow, $rowNum);
                foreach ($row as $colNum => $value) {
                    $maxCol = max($maxCol, $colNum);
                }
            }

            // Simple column width calculation
            $colWidth = $contentWidth / max($maxCol, 1);
            $colWidth = min($colWidth, 100); // Max column width

            $rowHeight = $this->defaultFontSize * 1.8;
            $headerOffset = $this->showHeaders ? 20 : 0;

            $currentY = $pageHeight - $this->marginTop;
            $page = new Page($pageSize);

            // Add sheet name as title
            $page->addText($sheetName, $this->marginLeft, $currentY, [
                'fontSize' => $this->defaultFontSize + 2,
                'fontWeight' => 'bold',
            ]);
            $currentY -= $rowHeight * 1.5;

            // Add column headers if enabled
            if ($this->showHeaders && $maxCol > 0) {
                for ($col = 1; $col <= $maxCol; $col++) {
                    $x = $this->marginLeft + $headerOffset + ($col - 1) * $colWidth;
                    $page->addText($this->numberToColumn($col), $x + 2, $currentY, [
                        'fontSize' => $this->defaultFontSize - 1,
                        'color' => [128, 128, 128],
                    ]);
                }
                $currentY -= $rowHeight;
            }

            // Render data rows
            for ($rowNum = 1; $rowNum <= $maxRow; $rowNum++) {
                // Check if we need a new page
                if ($currentY - $rowHeight < $this->marginBottom) {
                    $pdf->addPageObject($page);
                    $page = new Page($pageSize);
                    $currentY = $pageHeight - $this->marginTop;
                }

                // Row header
                if ($this->showHeaders) {
                    $page->addText((string) $rowNum, $this->marginLeft, $currentY, [
                        'fontSize' => $this->defaultFontSize - 1,
                        'color' => [128, 128, 128],
                    ]);
                }

                // Cell values
                $row = $data[$rowNum] ?? [];
                for ($col = 1; $col <= $maxCol; $col++) {
                    $value = $row[$col] ?? '';
                    $x = $this->marginLeft + $headerOffset + ($col - 1) * $colWidth;

                    // Truncate long values
                    $maxChars = (int) ($colWidth / ($this->defaultFontSize * 0.5));
                    if (strlen($value) > $maxChars) {
                        $value = substr($value, 0, $maxChars - 2) . '..';
                    }

                    $page->addText($value, $x + 2, $currentY, [
                        'fontSize' => $this->defaultFontSize,
                        'fontFamily' => $this->defaultFontFamily,
                    ]);

                    // Draw gridlines
                    if ($this->showGridlines) {
                        $page->addRectangle($x, $currentY - $rowHeight + 4, $colWidth, $rowHeight, [
                            'stroke' => [200, 200, 200],
                            'lineWidth' => 0.5,
                        ]);
                    }
                }

                $currentY -= $rowHeight;
            }

            $pdf->addPageObject($page);

            // Add page break between sheets if needed
            if ($this->sheetPerPage && $sheetIndex < count($sheets) - 1) {
                // Next sheet will start on new page
            }
        }

        return $pdf;
    }
}
