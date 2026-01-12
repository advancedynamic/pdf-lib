<?php

declare(strict_types=1);

namespace PdfLib\Import\Docx;

use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;
use ZipArchive;
use DOMDocument;
use DOMXPath;
use DOMNode;
use DOMElement;

/**
 * DOCX to PDF Converter.
 *
 * Converts Microsoft Word DOCX files to PDF format using pure PHP.
 * DOCX files are ZIP archives containing XML files following the
 * Office Open XML (OOXML) specification.
 */
class DocxToPdfConverter
{
    private PageSize $pageSize;
    private float $marginTop = 50;
    private float $marginRight = 50;
    private float $marginBottom = 50;
    private float $marginLeft = 50;
    private string $defaultFontFamily = 'Helvetica';
    private float $defaultFontSize = 12;

    /** @var array<int> Pages to convert (empty = all) */
    private array $pages = [];

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
     * Convert DOCX file to PDF file.
     */
    public function convert(string $docxPath, string $pdfPath): void
    {
        $pdf = $this->toPdfDocument($docxPath);
        $pdf->save($pdfPath);
    }

    /**
     * Convert DOCX file to PDF and return as binary string.
     */
    public function convertToString(string $docxPath): string
    {
        $pdf = $this->toPdfDocument($docxPath);
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
     * Convert DOCX file to PdfDocument object.
     */
    public function toPdfDocument(string $docxPath): PdfDocument
    {
        if (!file_exists($docxPath)) {
            throw new \InvalidArgumentException("DOCX file not found: $docxPath");
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException("Could not open DOCX file: $docxPath");
        }

        // Read document.xml
        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new \RuntimeException("Invalid DOCX file: missing document.xml");
        }

        // Parse styles.xml for font information
        $stylesXml = $zip->getFromName('word/styles.xml');

        $zip->close();

        // Parse document content
        $content = $this->parseDocument($documentXml, $stylesXml ?: null);

        return $this->renderToPdf($content);
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
    public function setDefaultFont(string $family, float $size = 12): self
    {
        $this->defaultFontFamily = $family;
        $this->defaultFontSize = $size;

        return $this;
    }

    /**
     * Set specific pages to convert.
     *
     * @param array<int> $pages Page numbers (1-based)
     */
    public function setPages(array $pages): self
    {
        $this->pages = $pages;

        return $this;
    }

    /**
     * Parse DOCX document XML.
     *
     * @return array<array{type: string, text: string, style: array<string, mixed>}>
     */
    private function parseDocument(string $documentXml, ?string $stylesXml): array
    {
        $content = [];

        // Parse styles first
        $styles = $stylesXml !== null ? $this->parseStyles($stylesXml) : [];

        // Parse document
        $dom = new DOMDocument();
        $dom->loadXML($documentXml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Find all paragraphs
        $paragraphs = $xpath->query('//w:p');

        if ($paragraphs !== false) {
            foreach ($paragraphs as $paragraph) {
                $paragraphContent = $this->parseParagraph($paragraph, $xpath, $styles);
                if ($paragraphContent !== null) {
                    $content[] = $paragraphContent;
                }
            }
        }

        return $content;
    }

    /**
     * Parse styles.xml.
     *
     * @return array<string, array<string, mixed>>
     */
    private function parseStyles(string $stylesXml): array
    {
        $styles = [];

        $dom = new DOMDocument();
        $dom->loadXML($stylesXml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Get default font from docDefaults
        $defaultFont = $xpath->query('//w:docDefaults//w:rFonts/@w:ascii');
        if ($defaultFont !== false && $defaultFont->length > 0) {
            $item = $defaultFont->item(0);
            if ($item !== null) {
                $styles['default']['fontFamily'] = $item->nodeValue;
            }
        }

        $defaultSize = $xpath->query('//w:docDefaults//w:sz/@w:val');
        if ($defaultSize !== false && $defaultSize->length > 0) {
            $item = $defaultSize->item(0);
            if ($item !== null && $item->nodeValue !== null) {
                // Size is in half-points
                $styles['default']['fontSize'] = (int) $item->nodeValue / 2;
            }
        }

        // Parse named styles
        $styleNodes = $xpath->query('//w:style');
        if ($styleNodes !== false) {
            foreach ($styleNodes as $style) {
                if (!$style instanceof DOMElement) {
                    continue;
                }
                $styleId = $style->getAttribute('w:styleId');
                if ($styleId) {
                    $styleData = [];

                    // Font size
                    $size = $xpath->query('.//w:sz/@w:val', $style);
                    if ($size !== false && $size->length > 0) {
                        $item = $size->item(0);
                        if ($item !== null && $item->nodeValue !== null) {
                            $styleData['fontSize'] = (int) $item->nodeValue / 2;
                        }
                    }

                    // Bold
                    $bold = $xpath->query('.//w:b', $style);
                    if ($bold !== false && $bold->length > 0) {
                        $styleData['bold'] = true;
                    }

                    // Italic
                    $italic = $xpath->query('.//w:i', $style);
                    if ($italic !== false && $italic->length > 0) {
                        $styleData['italic'] = true;
                    }

                    $styles[$styleId] = $styleData;
                }
            }
        }

        return $styles;
    }

    /**
     * Parse a paragraph element.
     *
     * @param array<string, array<string, mixed>> $styles
     * @return array{type: string, text: string, style: array<string, mixed>}|null
     */
    private function parseParagraph(DOMNode $paragraph, DOMXPath $xpath, array $styles): ?array
    {
        $text = '';
        $style = [
            'fontSize' => $this->defaultFontSize,
            'fontFamily' => $this->defaultFontFamily,
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'pageBreak' => false,
        ];

        // Check for page break
        $pageBreak = $xpath->query('.//w:br[@w:type="page"]', $paragraph);
        if ($pageBreak !== false && $pageBreak->length > 0) {
            $style['pageBreak'] = true;
        }

        // Get paragraph style
        $pStyle = $xpath->query('./w:pPr/w:pStyle/@w:val', $paragraph);
        if ($pStyle !== false && $pStyle->length > 0) {
            $item = $pStyle->item(0);
            $styleName = $item !== null ? ($item->nodeValue ?? '') : '';
            if ($styleName !== '' && isset($styles[$styleName])) {
                $style = array_merge($style, $styles[$styleName]);
            }

            // Handle heading styles
            if ($styleName !== '' && str_starts_with($styleName, 'Heading')) {
                $level = (int) substr($styleName, 7);
                $style['heading'] = $level;
                $style['fontSize'] = max(24 - ($level - 1) * 4, 12);
                $style['bold'] = true;
            }
        }

        // Get all text runs
        $runs = $xpath->query('./w:r', $paragraph);
        if ($runs !== false) {
            foreach ($runs as $run) {
                // Check run properties
                $bold = $xpath->query('./w:rPr/w:b', $run);
                $italic = $xpath->query('./w:rPr/w:i', $run);
                $underline = $xpath->query('./w:rPr/w:u', $run);

                if ($bold !== false && $bold->length > 0) {
                    $style['bold'] = true;
                }
                if ($italic !== false && $italic->length > 0) {
                    $style['italic'] = true;
                }
                if ($underline !== false && $underline->length > 0) {
                    $style['underline'] = true;
                }

                // Get text content
                $textNodes = $xpath->query('./w:t', $run);
                if ($textNodes !== false) {
                    foreach ($textNodes as $textNode) {
                        $text .= $textNode->nodeValue ?? '';
                    }
                }
            }
        }

        if (empty($text) && !$style['pageBreak']) {
            return null;
        }

        return [
            'type' => 'paragraph',
            'text' => $text,
            'style' => $style,
        ];
    }

    /**
     * Render parsed content to PDF.
     *
     * @param array<array{type: string, text: string, style: array<string, mixed>}> $content
     */
    private function renderToPdf(array $content): PdfDocument
    {
        $pdf = PdfDocument::create();

        $pageWidth = $this->pageSize->getWidth();
        $pageHeight = $this->pageSize->getHeight();
        $contentWidth = $pageWidth - $this->marginLeft - $this->marginRight;

        $currentY = $pageHeight - $this->marginTop;
        $page = new Page($this->pageSize);
        $pageNumber = 1;

        foreach ($content as $item) {
            $style = $item['style'];
            $text = $item['text'];

            // Handle page break
            if ($style['pageBreak']) {
                $pdf->addPageObject($page);
                $page = new Page($this->pageSize);
                $currentY = $pageHeight - $this->marginTop;
                $pageNumber++;
                continue;
            }

            // Check if we should include this page
            if (!empty($this->pages) && !in_array($pageNumber, $this->pages, true)) {
                continue;
            }

            $fontSize = is_numeric($style['fontSize']) ? (float) $style['fontSize'] : $this->defaultFontSize;
            $lineHeight = $fontSize * 1.4;

            // Word wrap text
            $lines = $this->wordWrap($text, $contentWidth, $fontSize);

            foreach ($lines as $line) {
                // Check if we need a new page
                if ($currentY - $lineHeight < $this->marginBottom) {
                    $pdf->addPageObject($page);
                    $page = new Page($this->pageSize);
                    $currentY = $pageHeight - $this->marginTop;
                    $pageNumber++;

                    if (!empty($this->pages) && !in_array($pageNumber, $this->pages, true)) {
                        continue;
                    }
                }

                $options = [
                    'fontSize' => $fontSize,
                    'fontFamily' => is_string($style['fontFamily']) ? $style['fontFamily'] : $this->defaultFontFamily,
                ];

                if ($style['bold'] ?? false) {
                    $options['fontWeight'] = 'bold';
                }

                if ($style['italic'] ?? false) {
                    $options['fontStyle'] = 'italic';
                }

                $page->addText($line, $this->marginLeft, $currentY, $options);
                $currentY -= $lineHeight;
            }

            // Add spacing after paragraph
            $currentY -= $fontSize * 0.5;
        }

        // Add last page
        $pdf->addPageObject($page);

        return $pdf;
    }

    /**
     * Word wrap text to fit within content width.
     *
     * @return array<string>
     */
    private function wordWrap(string $text, float $maxWidth, float $fontSize): array
    {
        if (empty($text)) {
            return [''];
        }

        // Approximate character width (varies by font)
        $avgCharWidth = $fontSize * 0.5;
        $charsPerLine = (int) ($maxWidth / $avgCharWidth);

        if ($charsPerLine <= 0) {
            return [$text];
        }

        $lines = [];
        $words = explode(' ', $text);
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;

            if (strlen($testLine) <= $charsPerLine) {
                $currentLine = $testLine;
            } else {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines;
    }
}
