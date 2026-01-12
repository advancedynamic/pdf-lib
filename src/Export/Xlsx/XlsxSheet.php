<?php

declare(strict_types=1);

namespace PdfLib\Export\Xlsx;

/**
 * Represents a worksheet in an XLSX file.
 */
class XlsxSheet
{
    private string $name;
    private XlsxWriter $writer;

    /** @var array<int, array<int, array{value: mixed, type: string, style: int}>> */
    private array $cells = [];

    /** @var array<int, float> Column widths */
    private array $columnWidths = [];

    /** @var array<int, float> Row heights */
    private array $rowHeights = [];

    private int $maxRow = 0;
    private int $maxCol = 0;

    public function __construct(string $name, XlsxWriter $writer)
    {
        $this->name = $name;
        $this->writer = $writer;
    }

    /**
     * Get sheet name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set cell value.
     *
     * @param int $row Row number (1-based)
     * @param int $col Column number (1-based)
     * @param mixed $value Cell value
     */
    public function setCell(int $row, int $col, mixed $value, int $style = 0): self
    {
        if (!isset($this->cells[$row])) {
            $this->cells[$row] = [];
        }

        $type = $this->getCellType($value);

        $this->cells[$row][$col] = [
            'value' => $value,
            'type' => $type,
            'style' => $style,
        ];

        $this->maxRow = max($this->maxRow, $row);
        $this->maxCol = max($this->maxCol, $col);

        return $this;
    }

    /**
     * Set cell by address (e.g., "A1", "B2").
     */
    public function setCellByAddress(string $address, mixed $value, int $style = 0): self
    {
        [$col, $row] = $this->parseAddress($address);

        return $this->setCell($row, $col, $value, $style);
    }

    /**
     * Set a row of data.
     *
     * @param int $row Row number (1-based)
     * @param array<mixed> $data Array of values
     */
    public function setRow(int $row, array $data, int $style = 0): self
    {
        $col = 1;
        foreach ($data as $value) {
            $this->setCell($row, $col, $value, $style);
            $col++;
        }

        return $this;
    }

    /**
     * Add a row of data at the next available row.
     *
     * @param array<mixed> $data Array of values
     */
    public function addRow(array $data, int $style = 0): self
    {
        return $this->setRow($this->maxRow + 1, $data, $style);
    }

    /**
     * Set column width.
     *
     * @param int $col Column number (1-based)
     * @param float $width Width in characters
     */
    public function setColumnWidth(int $col, float $width): self
    {
        $this->columnWidths[$col] = $width;

        return $this;
    }

    /**
     * Set row height.
     *
     * @param int $row Row number (1-based)
     * @param float $height Height in points
     */
    public function setRowHeight(int $row, float $height): self
    {
        $this->rowHeights[$row] = $height;

        return $this;
    }

    /**
     * Convert sheet to XML.
     */
    public function toXml(): string
    {
        $dimension = $this->getDimensionRef();
        $cols = $this->getColumnsXml();
        $rows = $this->getRowsXml();

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <dimension ref="{$dimension}"/>
    <sheetViews>
        <sheetView tabSelected="1" workbookViewId="0"/>
    </sheetViews>
    <sheetFormatPr defaultRowHeight="15"/>
{$cols}    <sheetData>
{$rows}    </sheetData>
</worksheet>
XML;

        return $xml;
    }

    /**
     * Get dimension reference (e.g., "A1:D10").
     */
    private function getDimensionRef(): string
    {
        if ($this->maxRow === 0 || $this->maxCol === 0) {
            return 'A1';
        }

        return 'A1:' . $this->columnToLetter($this->maxCol) . $this->maxRow;
    }

    /**
     * Get columns XML.
     */
    private function getColumnsXml(): string
    {
        if (empty($this->columnWidths)) {
            return '';
        }

        $cols = "    <cols>\n";

        foreach ($this->columnWidths as $col => $width) {
            $cols .= "        <col min=\"{$col}\" max=\"{$col}\" width=\"{$width}\" customWidth=\"1\"/>\n";
        }

        $cols .= "    </cols>\n";

        return $cols;
    }

    /**
     * Get rows XML.
     */
    private function getRowsXml(): string
    {
        $xml = '';

        for ($row = 1; $row <= $this->maxRow; $row++) {
            if (!isset($this->cells[$row])) {
                continue;
            }

            $rowHeight = isset($this->rowHeights[$row])
                ? ' ht="' . $this->rowHeights[$row] . '" customHeight="1"'
                : '';

            $xml .= "        <row r=\"{$row}\"{$rowHeight}>\n";

            ksort($this->cells[$row]);
            foreach ($this->cells[$row] as $col => $cell) {
                $xml .= $this->getCellXml($row, $col, $cell);
            }

            $xml .= "        </row>\n";
        }

        return $xml;
    }

    /**
     * Get cell XML.
     *
     * @param array{value: mixed, type: string, style: int} $cell
     */
    private function getCellXml(int $row, int $col, array $cell): string
    {
        $ref = $this->columnToLetter($col) . $row;
        $style = $cell['style'] > 0 ? ' s="' . $cell['style'] . '"' : '';
        $value = $cell['value'];
        $type = $cell['type'];

        switch ($type) {
            case 'string':
                $stringIndex = $this->writer->getSharedStringIndex((string) $value);
                return "            <c r=\"{$ref}\" t=\"s\"{$style}><v>{$stringIndex}</v></c>\n";

            case 'number':
                return "            <c r=\"{$ref}\"{$style}><v>{$value}</v></c>\n";

            case 'boolean':
                $val = $value ? '1' : '0';
                return "            <c r=\"{$ref}\" t=\"b\"{$style}><v>{$val}</v></c>\n";

            case 'formula':
                $formula = htmlspecialchars(ltrim((string) $value, '='), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                return "            <c r=\"{$ref}\"{$style}><f>{$formula}</f></c>\n";

            default:
                return "            <c r=\"{$ref}\"{$style}/>\n";
        }
    }

    /**
     * Determine cell type from value.
     */
    private function getCellType(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'empty';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_numeric($value)) {
            return 'number';
        }

        if (is_string($value) && str_starts_with($value, '=')) {
            return 'formula';
        }

        return 'string';
    }

    /**
     * Convert column number to letter (1 = A, 26 = Z, 27 = AA).
     */
    private function columnToLetter(int $col): string
    {
        $letter = '';

        while ($col > 0) {
            $col--;
            $letter = chr(($col % 26) + 65) . $letter;
            $col = (int) ($col / 26);
        }

        return $letter;
    }

    /**
     * Parse cell address (e.g., "A1") to [col, row].
     *
     * @return array{int, int}
     */
    private function parseAddress(string $address): array
    {
        if (!preg_match('/^([A-Z]+)(\d+)$/', strtoupper($address), $matches)) {
            throw new \InvalidArgumentException("Invalid cell address: $address");
        }

        $col = 0;
        $letters = $matches[1];
        $len = strlen($letters);

        for ($i = 0; $i < $len; $i++) {
            $col = $col * 26 + (ord($letters[$i]) - 64);
        }

        return [$col, (int) $matches[2]];
    }
}
