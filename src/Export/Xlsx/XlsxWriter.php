<?php

declare(strict_types=1);

namespace PdfLib\Export\Xlsx;

use ZipArchive;

/**
 * XLSX file writer.
 *
 * Creates valid XLSX files from structured data.
 * XLSX files are ZIP archives containing XML files following the
 * SpreadsheetML specification.
 */
class XlsxWriter
{
    private string $tempDir;
    private ZipArchive $zip;

    /** @var array<XlsxSheet> */
    private array $sheets = [];

    /** @var array<string> Shared strings */
    private array $sharedStrings = [];

    /** @var array<string, int> String to index mapping */
    private array $stringIndex = [];

    public function __construct()
    {
        $this->tempDir = sys_get_temp_dir() . '/xlsx_' . uniqid();
        if (!mkdir($this->tempDir, 0755, true)) {
            throw new \RuntimeException('Could not create temp directory');
        }
    }

    /**
     * Add a sheet.
     */
    public function addSheet(string $name = 'Sheet1'): XlsxSheet
    {
        $sheet = new XlsxSheet($name, $this);
        $this->sheets[] = $sheet;

        return $sheet;
    }

    /**
     * Get shared string index.
     */
    public function getSharedStringIndex(string $value): int
    {
        if (isset($this->stringIndex[$value])) {
            return $this->stringIndex[$value];
        }

        $index = count($this->sharedStrings);
        $this->sharedStrings[] = $value;
        $this->stringIndex[$value] = $index;

        return $index;
    }

    /**
     * Save the workbook.
     */
    public function save(string $path): void
    {
        if (empty($this->sheets)) {
            $this->addSheet('Sheet1');
        }

        $this->createStructure();
        $this->createContentTypes();
        $this->createRels();
        $this->createWorkbook();
        $this->createSheets();
        $this->createSharedStrings();
        $this->createStyles();
        $this->createTheme();

        // Create ZIP file
        $this->zip = new ZipArchive();
        if ($this->zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create XLSX file: $path");
        }

        $this->addDirectoryToZip($this->tempDir);
        $this->zip->close();

        // Clean up
        $this->removeDirectory($this->tempDir);
    }

    /**
     * Get workbook as binary string.
     */
    public function getContent(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $this->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content;
    }

    /**
     * Create directory structure.
     */
    private function createStructure(): void
    {
        mkdir($this->tempDir . '/_rels', 0755, true);
        mkdir($this->tempDir . '/xl/_rels', 0755, true);
        mkdir($this->tempDir . '/xl/worksheets', 0755, true);
        mkdir($this->tempDir . '/xl/theme', 0755, true);
        mkdir($this->tempDir . '/docProps', 0755, true);
    }

    /**
     * Create [Content_Types].xml
     */
    private function createContentTypes(): void
    {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $num = $i + 1;
            $sheets .= "    <Override PartName=\"/xl/worksheets/sheet{$num}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>\n";
        }

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
{$sheets}    <Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
        file_put_contents($this->tempDir . '/[Content_Types].xml', $xml);
    }

    /**
     * Create _rels/.rels
     */
    private function createRels(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
        file_put_contents($this->tempDir . '/_rels/.rels', $xml);

        // Workbook rels
        $sheetRels = '';
        foreach ($this->sheets as $i => $sheet) {
            $num = $i + 1;
            $rId = $i + 1;
            $sheetRels .= "    <Relationship Id=\"rId{$rId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$num}.xml\"/>\n";
        }

        $nextRId = count($this->sheets) + 1;

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
{$sheetRels}    <Relationship Id="rId{$nextRId}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
    <Relationship Id="rId" . ($nextRId + 1) . "" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId" . ($nextRId + 2) . "" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML;

        // Fix the XML properly
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
{$sheetRels}    <Relationship Id="rId100" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
    <Relationship Id="rId101" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId102" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML;
        file_put_contents($this->tempDir . '/xl/_rels/workbook.xml.rels', $xml);

        // Core properties
        $date = date('Y-m-d\TH:i:s\Z');
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:creator>PdfLib</dc:creator>
    <cp:lastModifiedBy>PdfLib</cp:lastModifiedBy>
    <dcterms:created xsi:type="dcterms:W3CDTF">{$date}</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">{$date}</dcterms:modified>
</cp:coreProperties>
XML;
        file_put_contents($this->tempDir . '/docProps/core.xml', $xml);

        // App properties
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
    <Application>PdfLib</Application>
    <DocSecurity>0</DocSecurity>
    <ScaleCrop>false</ScaleCrop>
    <LinksUpToDate>false</LinksUpToDate>
    <SharedDoc>false</SharedDoc>
    <HyperlinksChanged>false</HyperlinksChanged>
</Properties>
XML;
        file_put_contents($this->tempDir . '/docProps/app.xml', $xml);
    }

    /**
     * Create xl/workbook.xml
     */
    private function createWorkbook(): void
    {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $name = htmlspecialchars($sheet->getName(), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $rId = $i + 1;
            $sheetId = $i + 1;
            $sheets .= "        <sheet name=\"{$name}\" sheetId=\"{$sheetId}\" r:id=\"rId{$rId}\"/>\n";
        }

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <fileVersion appName="xl" lastEdited="5" lowestEdited="5" rupBuild="9303"/>
    <workbookPr defaultThemeVersion="124226"/>
    <bookViews>
        <workbookView xWindow="0" yWindow="0" windowWidth="20490" windowHeight="7755"/>
    </bookViews>
    <sheets>
{$sheets}    </sheets>
    <calcPr calcId="144525"/>
</workbook>
XML;
        file_put_contents($this->tempDir . '/xl/workbook.xml', $xml);
    }

    /**
     * Create worksheet files.
     */
    private function createSheets(): void
    {
        foreach ($this->sheets as $i => $sheet) {
            $num = $i + 1;
            $xml = $sheet->toXml();
            file_put_contents($this->tempDir . "/xl/worksheets/sheet{$num}.xml", $xml);
        }
    }

    /**
     * Create xl/sharedStrings.xml
     */
    private function createSharedStrings(): void
    {
        $count = count($this->sharedStrings);
        $strings = '';

        foreach ($this->sharedStrings as $str) {
            $escaped = htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $strings .= "    <si><t>{$escaped}</t></si>\n";
        }

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="{$count}" uniqueCount="{$count}">
{$strings}</sst>
XML;
        file_put_contents($this->tempDir . '/xl/sharedStrings.xml', $xml);
    }

    /**
     * Create xl/styles.xml
     */
    private function createStyles(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="2">
        <font>
            <sz val="11"/>
            <color theme="1"/>
            <name val="Calibri"/>
            <family val="2"/>
            <scheme val="minor"/>
        </font>
        <font>
            <b/>
            <sz val="11"/>
            <color theme="1"/>
            <name val="Calibri"/>
            <family val="2"/>
            <scheme val="minor"/>
        </font>
    </fonts>
    <fills count="2">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
    </fills>
    <borders count="1">
        <border><left/><right/><top/><bottom/><diagonal/></border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="2">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    </cellXfs>
    <cellStyles count="1">
        <cellStyle name="Normal" xfId="0" builtinId="0"/>
    </cellStyles>
    <dxfs count="0"/>
    <tableStyles count="0" defaultTableStyle="TableStyleMedium9" defaultPivotStyle="PivotStyleLight16"/>
</styleSheet>
XML;
        file_put_contents($this->tempDir . '/xl/styles.xml', $xml);
    }

    /**
     * Create xl/theme/theme1.xml
     */
    private function createTheme(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">
    <a:themeElements>
        <a:clrScheme name="Office">
            <a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1>
            <a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>
            <a:dk2><a:srgbClr val="44546A"/></a:dk2>
            <a:lt2><a:srgbClr val="E7E6E6"/></a:lt2>
            <a:accent1><a:srgbClr val="4472C4"/></a:accent1>
            <a:accent2><a:srgbClr val="ED7D31"/></a:accent2>
            <a:accent3><a:srgbClr val="A5A5A5"/></a:accent3>
            <a:accent4><a:srgbClr val="FFC000"/></a:accent4>
            <a:accent5><a:srgbClr val="5B9BD5"/></a:accent5>
            <a:accent6><a:srgbClr val="70AD47"/></a:accent6>
            <a:hlink><a:srgbClr val="0563C1"/></a:hlink>
            <a:folHlink><a:srgbClr val="954F72"/></a:folHlink>
        </a:clrScheme>
        <a:fontScheme name="Office">
            <a:majorFont>
                <a:latin typeface="Calibri Light"/>
                <a:ea typeface=""/>
                <a:cs typeface=""/>
            </a:majorFont>
            <a:minorFont>
                <a:latin typeface="Calibri"/>
                <a:ea typeface=""/>
                <a:cs typeface=""/>
            </a:minorFont>
        </a:fontScheme>
        <a:fmtScheme name="Office">
            <a:fillStyleLst>
                <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
                <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
                <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
            </a:fillStyleLst>
            <a:lnStyleLst>
                <a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>
                <a:ln w="12700"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>
                <a:ln w="19050"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>
            </a:lnStyleLst>
            <a:effectStyleLst>
                <a:effectStyle><a:effectLst/></a:effectStyle>
                <a:effectStyle><a:effectLst/></a:effectStyle>
                <a:effectStyle><a:effectLst/></a:effectStyle>
            </a:effectStyleLst>
            <a:bgFillStyleLst>
                <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
                <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
                <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
            </a:bgFillStyleLst>
        </a:fmtScheme>
    </a:themeElements>
</a:theme>
XML;
        file_put_contents($this->tempDir . '/xl/theme/theme1.xml', $xml);
    }

    /**
     * Add directory contents to ZIP.
     */
    private function addDirectoryToZip(string $dir, string $zipPath = ''): void
    {
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $file;
            $localPath = $zipPath . ($zipPath ? '/' : '') . $file;

            if (is_dir($fullPath)) {
                $this->zip->addEmptyDir($localPath);
                $this->addDirectoryToZip($fullPath, $localPath);
            } else {
                $this->zip->addFile($fullPath, $localPath);
            }
        }
    }

    /**
     * Remove directory recursively.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
