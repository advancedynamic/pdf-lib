<?php

declare(strict_types=1);

namespace PdfLib\Export\Docx;

use ZipArchive;

/**
 * DOCX file writer.
 *
 * Creates valid DOCX files from structured content.
 * DOCX files are ZIP archives containing XML files following the
 * Office Open XML (OOXML) specification.
 */
class DocxWriter
{
    private string $tempDir;
    private ZipArchive $zip;

    /** @var array<DocxParagraph> */
    private array $paragraphs = [];

    private string $defaultFont = 'Arial';
    private int $defaultFontSize = 24; // Half-points (12pt = 24)

    // Page settings (in twips: 1 inch = 1440 twips)
    private int $pageWidth = 12240;   // 8.5 inches
    private int $pageHeight = 15840;  // 11 inches
    private int $marginTop = 1440;    // 1 inch
    private int $marginRight = 1440;
    private int $marginBottom = 1440;
    private int $marginLeft = 1440;

    public function __construct()
    {
        $this->tempDir = sys_get_temp_dir() . '/docx_' . uniqid();
        if (!mkdir($this->tempDir, 0755, true)) {
            throw new \RuntimeException('Could not create temp directory');
        }
    }

    /**
     * Set page size to A4.
     */
    public function setPageSizeA4(): self
    {
        // A4: 210mm x 297mm = 11906 x 16838 twips
        $this->pageWidth = 11906;
        $this->pageHeight = 16838;

        return $this;
    }

    /**
     * Set page size to Letter.
     */
    public function setPageSizeLetter(): self
    {
        $this->pageWidth = 12240;
        $this->pageHeight = 15840;

        return $this;
    }

    /**
     * Set margins in inches.
     */
    public function setMargins(float $top, float $right, float $bottom, float $left): self
    {
        $this->marginTop = (int) ($top * 1440);
        $this->marginRight = (int) ($right * 1440);
        $this->marginBottom = (int) ($bottom * 1440);
        $this->marginLeft = (int) ($left * 1440);

        return $this;
    }

    /**
     * Set default font.
     */
    public function setDefaultFont(string $font, int $sizePt = 12): self
    {
        $this->defaultFont = $font;
        $this->defaultFontSize = $sizePt * 2; // Convert to half-points

        return $this;
    }

    /**
     * Add a paragraph.
     */
    public function addParagraph(DocxParagraph $paragraph): self
    {
        $this->paragraphs[] = $paragraph;

        return $this;
    }

    /**
     * Add simple text paragraph.
     */
    public function addText(
        string $text,
        ?string $style = null,
        bool $bold = false,
        bool $italic = false
    ): self {
        $paragraph = new DocxParagraph();
        $paragraph->addRun($text, $bold, $italic);

        if ($style !== null) {
            $paragraph->setStyle($style);
        }

        $this->paragraphs[] = $paragraph;

        return $this;
    }

    /**
     * Add a heading.
     */
    public function addHeading(string $text, int $level = 1): self
    {
        $paragraph = new DocxParagraph();
        $paragraph->addRun($text, true);
        $paragraph->setStyle('Heading' . min(9, max(1, $level)));

        $this->paragraphs[] = $paragraph;

        return $this;
    }

    /**
     * Add a page break.
     */
    public function addPageBreak(): self
    {
        $paragraph = new DocxParagraph();
        $paragraph->setPageBreak(true);

        $this->paragraphs[] = $paragraph;

        return $this;
    }

    /**
     * Save the document.
     */
    public function save(string $path): void
    {
        $this->createStructure();
        $this->createContentTypes();
        $this->createRels();
        $this->createDocument();
        $this->createStyles();
        $this->createSettings();
        $this->createWebSettings();
        $this->createFontTable();
        $this->createTheme();

        // Create ZIP file
        $this->zip = new ZipArchive();
        if ($this->zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create DOCX file: $path");
        }

        $this->addDirectoryToZip($this->tempDir);
        $this->zip->close();

        // Clean up
        $this->removeDirectory($this->tempDir);
    }

    /**
     * Get document as binary string.
     */
    public function getContent(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_');
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
        mkdir($this->tempDir . '/word/_rels', 0755, true);
        mkdir($this->tempDir . '/word/theme', 0755, true);
        mkdir($this->tempDir . '/docProps', 0755, true);
    }

    /**
     * Create [Content_Types].xml
     */
    private function createContentTypes(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
    <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
    <Override PartName="/word/webSettings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml"/>
    <Override PartName="/word/fontTable.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml"/>
    <Override PartName="/word/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
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
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
        file_put_contents($this->tempDir . '/_rels/.rels', $xml);

        // Word rels
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings" Target="webSettings.xml"/>
    <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable" Target="fontTable.xml"/>
    <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
</Relationships>
XML;
        file_put_contents($this->tempDir . '/word/_rels/document.xml.rels', $xml);

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
     * Create word/document.xml
     */
    private function createDocument(): void
    {
        $body = '';

        foreach ($this->paragraphs as $paragraph) {
            $body .= $paragraph->toXml();
        }

        // Add section properties
        $body .= <<<XML
        <w:sectPr>
            <w:pgSz w:w="{$this->pageWidth}" w:h="{$this->pageHeight}"/>
            <w:pgMar w:top="{$this->marginTop}" w:right="{$this->marginRight}" w:bottom="{$this->marginBottom}" w:left="{$this->marginLeft}" w:header="720" w:footer="720" w:gutter="0"/>
            <w:cols w:space="720"/>
            <w:docGrid w:linePitch="360"/>
        </w:sectPr>
XML;

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:body>
{$body}
    </w:body>
</w:document>
XML;
        file_put_contents($this->tempDir . '/word/document.xml', $xml);
    }

    /**
     * Create word/styles.xml
     */
    private function createStyles(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:docDefaults>
        <w:rPrDefault>
            <w:rPr>
                <w:rFonts w:ascii="{$this->defaultFont}" w:hAnsi="{$this->defaultFont}" w:cs="{$this->defaultFont}"/>
                <w:sz w:val="{$this->defaultFontSize}"/>
                <w:szCs w:val="{$this->defaultFontSize}"/>
            </w:rPr>
        </w:rPrDefault>
        <w:pPrDefault>
            <w:pPr>
                <w:spacing w:after="200" w:line="276" w:lineRule="auto"/>
            </w:pPr>
        </w:pPrDefault>
    </w:docDefaults>
    <w:style w:type="paragraph" w:styleId="Normal" w:default="1">
        <w:name w:val="Normal"/>
        <w:qFormat/>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Heading1">
        <w:name w:val="Heading 1"/>
        <w:basedOn w:val="Normal"/>
        <w:qFormat/>
        <w:pPr>
            <w:spacing w:before="480" w:after="120"/>
            <w:outlineLvl w:val="0"/>
        </w:pPr>
        <w:rPr>
            <w:b/>
            <w:sz w:val="48"/>
            <w:szCs w:val="48"/>
        </w:rPr>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Heading2">
        <w:name w:val="Heading 2"/>
        <w:basedOn w:val="Normal"/>
        <w:qFormat/>
        <w:pPr>
            <w:spacing w:before="360" w:after="80"/>
            <w:outlineLvl w:val="1"/>
        </w:pPr>
        <w:rPr>
            <w:b/>
            <w:sz w:val="36"/>
            <w:szCs w:val="36"/>
        </w:rPr>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Heading3">
        <w:name w:val="Heading 3"/>
        <w:basedOn w:val="Normal"/>
        <w:qFormat/>
        <w:pPr>
            <w:spacing w:before="280" w:after="80"/>
            <w:outlineLvl w:val="2"/>
        </w:pPr>
        <w:rPr>
            <w:b/>
            <w:sz w:val="28"/>
            <w:szCs w:val="28"/>
        </w:rPr>
    </w:style>
</w:styles>
XML;
        file_put_contents($this->tempDir . '/word/styles.xml', $xml);
    }

    /**
     * Create word/settings.xml
     */
    private function createSettings(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:zoom w:percent="100"/>
    <w:defaultTabStop w:val="720"/>
    <w:characterSpacingControl w:val="doNotCompress"/>
    <w:compat>
        <w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/>
    </w:compat>
</w:settings>
XML;
        file_put_contents($this->tempDir . '/word/settings.xml', $xml);
    }

    /**
     * Create word/webSettings.xml
     */
    private function createWebSettings(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:webSettings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:optimizeForBrowser/>
    <w:allowPNG/>
</w:webSettings>
XML;
        file_put_contents($this->tempDir . '/word/webSettings.xml', $xml);
    }

    /**
     * Create word/fontTable.xml
     */
    private function createFontTable(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:fonts xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:font w:name="{$this->defaultFont}">
        <w:panose1 w:val="020B0604020202020204"/>
        <w:charset w:val="00"/>
        <w:family w:val="swiss"/>
        <w:pitch w:val="variable"/>
    </w:font>
    <w:font w:name="Times New Roman">
        <w:panose1 w:val="02020603050405020304"/>
        <w:charset w:val="00"/>
        <w:family w:val="roman"/>
        <w:pitch w:val="variable"/>
    </w:font>
    <w:font w:name="Courier New">
        <w:panose1 w:val="02070309020205020404"/>
        <w:charset w:val="00"/>
        <w:family w:val="modern"/>
        <w:pitch w:val="fixed"/>
    </w:font>
</w:fonts>
XML;
        file_put_contents($this->tempDir . '/word/fontTable.xml', $xml);
    }

    /**
     * Create word/theme/theme1.xml
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
        file_put_contents($this->tempDir . '/word/theme/theme1.xml', $xml);
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
