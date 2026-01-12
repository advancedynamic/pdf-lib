<?php

declare(strict_types=1);

namespace PdfLib\Import\Pptx;

use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;
use ZipArchive;
use DOMDocument;
use DOMXPath;
use DOMNode;
use DOMElement;

/**
 * PPTX to PDF Converter.
 *
 * Converts Microsoft PowerPoint PPTX files to PDF format using pure PHP.
 * PPTX files are ZIP archives containing XML files following the
 * PresentationML specification.
 */
class PptxToPdfConverter
{
    private PageSize $pageSize;
    private float $marginTop = 36;
    private float $marginRight = 36;
    private float $marginBottom = 36;
    private float $marginLeft = 36;
    private string $defaultFontFamily = 'Helvetica';
    private float $defaultFontSize = 12;

    /** @var array<int> Slides to convert (empty = all, 1-based) */
    private array $slides = [];

    /** @var int Slides per page for handout mode (0 = single slide per page) */
    private int $handoutMode = 0;

    /** @var bool Include slide numbers */
    private bool $includeSlideNumbers = true;

    public function __construct()
    {
        // Default to widescreen landscape (16:9 aspect ratio similar to slides)
        $this->pageSize = PageSize::letter()->landscape();
    }

    /**
     * Create a new converter instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Convert PPTX file to PDF file.
     */
    public function convert(string $pptxPath, string $pdfPath): void
    {
        $pdf = $this->toPdfDocument($pptxPath);
        $pdf->save($pdfPath);
    }

    /**
     * Convert PPTX file to PDF and return as binary string.
     */
    public function convertToString(string $pptxPath): string
    {
        $pdf = $this->toPdfDocument($pptxPath);
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
     * Convert PPTX file to PdfDocument object.
     */
    public function toPdfDocument(string $pptxPath): PdfDocument
    {
        if (!file_exists($pptxPath)) {
            throw new \InvalidArgumentException("PPTX file not found: $pptxPath");
        }

        $zip = new ZipArchive();
        if ($zip->open($pptxPath) !== true) {
            throw new \RuntimeException("Could not open PPTX file: $pptxPath");
        }

        // Read presentation.xml to get slide info
        $presentationXml = $zip->getFromName('ppt/presentation.xml');
        if ($presentationXml === false) {
            $zip->close();
            throw new \RuntimeException("Invalid PPTX file: missing presentation.xml");
        }

        $slideInfo = $this->parsePresentation($presentationXml);

        // Parse each slide
        $allSlides = [];
        $slideNum = 1;
        foreach ($slideInfo as $info) {
            // Skip if specific slides are requested and this isn't one
            if (!empty($this->slides) && !in_array($slideNum, $this->slides, true)) {
                $slideNum++;
                continue;
            }

            $slideXml = $zip->getFromName("ppt/slides/slide{$slideNum}.xml");
            if ($slideXml !== false) {
                $allSlides[] = [
                    'number' => $slideNum,
                    'content' => $this->parseSlide($slideXml),
                ];
            }

            $slideNum++;
        }

        $zip->close();

        return $this->renderToPdf($allSlides);
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
                'a3' => PageSize::a3()->landscape(),
                'a4' => PageSize::a4()->landscape(),
                'a5' => PageSize::a5()->landscape(),
                'letter' => PageSize::letter()->landscape(),
                'legal' => PageSize::legal()->landscape(),
                default => PageSize::letter()->landscape(),
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
     * Set specific slides to convert.
     *
     * @param array<int> $slides Slide numbers (1-based)
     */
    public function setSlides(array $slides): self
    {
        $this->slides = $slides;

        return $this;
    }

    /**
     * Set handout mode (multiple slides per page).
     *
     * @param int $slidesPerPage 0 = disabled, 2/4/6/9 = handout layouts
     */
    public function setHandoutMode(int $slidesPerPage): self
    {
        $this->handoutMode = in_array($slidesPerPage, [0, 2, 4, 6, 9], true)
            ? $slidesPerPage
            : 0;

        return $this;
    }

    /**
     * Get handout mode value.
     */
    public function getHandoutMode(): int
    {
        return $this->handoutMode;
    }

    /**
     * Include or exclude slide numbers.
     */
    public function includeSlideNumbers(bool $include = true): self
    {
        $this->includeSlideNumbers = $include;

        return $this;
    }

    /**
     * Parse presentation.xml to get slide info.
     *
     * @return array<array{id: string}>
     */
    private function parsePresentation(string $xml): array
    {
        $slides = [];

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $slideNodes = $xpath->query('//p:sldIdLst/p:sldId');
        if ($slideNodes !== false) {
            foreach ($slideNodes as $slide) {
                if ($slide instanceof DOMElement) {
                    $slides[] = [
                        'id' => $slide->getAttribute('r:id'),
                    ];
                }
            }
        }

        return $slides;
    }

    /**
     * Parse a slide XML.
     *
     * @return array<array{type: string, text: string, x: float, y: float, width: float, height: float, style: array<string, mixed>}>
     */
    private function parseSlide(string $xml): array
    {
        $content = [];

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        // Find all shapes with text
        $shapes = $xpath->query('//p:sp');
        if ($shapes !== false) {
            foreach ($shapes as $shape) {
                $shapeContent = $this->parseShape($shape, $xpath);
                if ($shapeContent !== null) {
                    $content[] = $shapeContent;
                }
            }
        }

        return $content;
    }

    /**
     * Parse a shape element.
     *
     * @return array{type: string, text: string, x: float, y: float, width: float, height: float, style: array<string, mixed>}|null
     */
    private function parseShape(DOMNode $shape, DOMXPath $xpath): ?array
    {
        // Get position and size from shape properties
        $xfrm = $xpath->query('.//p:spPr/a:xfrm', $shape);
        $x = 0.0;
        $y = 0.0;
        $width = 0.0;
        $height = 0.0;

        if ($xfrm !== false && $xfrm->length > 0) {
            $xfrmNode = $xfrm->item(0);
            if ($xfrmNode !== null) {
                $off = $xpath->query('./a:off', $xfrmNode);
                $ext = $xpath->query('./a:ext', $xfrmNode);

                if ($off !== false && $off->length > 0) {
                    $offNode = $off->item(0);
                    if ($offNode instanceof DOMElement) {
                        // EMUs to points (914400 EMUs per inch, 72 points per inch)
                        $x = ((int) $offNode->getAttribute('x')) / 914400 * 72;
                        $y = ((int) $offNode->getAttribute('y')) / 914400 * 72;
                    }
                }

                if ($ext !== false && $ext->length > 0) {
                    $extNode = $ext->item(0);
                    if ($extNode instanceof DOMElement) {
                        $width = ((int) $extNode->getAttribute('cx')) / 914400 * 72;
                        $height = ((int) $extNode->getAttribute('cy')) / 914400 * 72;
                    }
                }
            }
        }

        // Get text content
        $textBody = $xpath->query('.//p:txBody', $shape);
        if ($textBody === false || $textBody->length === 0) {
            return null;
        }

        $textBodyNode = $textBody->item(0);
        if ($textBodyNode === null) {
            return null;
        }

        $text = '';
        $style = [
            'fontSize' => $this->defaultFontSize,
            'fontFamily' => $this->defaultFontFamily,
            'bold' => false,
            'italic' => false,
            'color' => null,
        ];

        // Parse paragraphs
        $paragraphs = $xpath->query('.//a:p', $textBodyNode);
        $paraTexts = [];

        if ($paragraphs !== false) {
            foreach ($paragraphs as $para) {
                $paraText = '';

                // Check paragraph properties
                $pPr = $xpath->query('./a:pPr', $para);
                if ($pPr !== false && $pPr->length > 0) {
                    $pPrNode = $pPr->item(0);
                    // Check alignment
                    if ($pPrNode instanceof DOMElement) {
                        $algn = $pPrNode->getAttribute('algn');
                        if ($algn) {
                            $style['align'] = $algn;
                        }
                    }
                }

                // Get runs
                $runs = $xpath->query('./a:r', $para);
                if ($runs !== false) {
                    foreach ($runs as $run) {
                        // Check run properties
                        $rPr = $xpath->query('./a:rPr', $run);
                        if ($rPr !== false && $rPr->length > 0) {
                            $rPrNode = $rPr->item(0);
                            if ($rPrNode instanceof DOMElement) {
                                // Font size (in hundredths of a point)
                                $sz = $rPrNode->getAttribute('sz');
                                if ($sz) {
                                    $style['fontSize'] = (int) $sz / 100;
                                }

                                // Bold
                                $b = $rPrNode->getAttribute('b');
                                if ($b === '1') {
                                    $style['bold'] = true;
                                }

                                // Italic
                                $i = $rPrNode->getAttribute('i');
                                if ($i === '1') {
                                    $style['italic'] = true;
                                }

                                // Color
                                $srgbClr = $xpath->query('./a:solidFill/a:srgbClr/@val', $rPrNode);
                                if ($srgbClr !== false && $srgbClr->length > 0) {
                                    $item = $srgbClr->item(0);
                                    if ($item !== null) {
                                        $style['color'] = $item->nodeValue;
                                    }
                                }
                            }
                        }

                        // Get text
                        $tNodes = $xpath->query('./a:t', $run);
                        if ($tNodes !== false) {
                            foreach ($tNodes as $t) {
                                $paraText .= $t->nodeValue ?? '';
                            }
                        }
                    }
                }

                if ($paraText !== '') {
                    $paraTexts[] = $paraText;
                }
            }
        }

        $text = implode("\n", $paraTexts);

        if (empty($text)) {
            return null;
        }

        return [
            'type' => 'text',
            'text' => $text,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'style' => $style,
        ];
    }

    /**
     * Render parsed slides to PDF.
     *
     * @param array<array{number: int, content: array<array{type: string, text: string, x: float, y: float, width: float, height: float, style: array<string, mixed>}>}> $slides
     */
    private function renderToPdf(array $slides): PdfDocument
    {
        $pdf = PdfDocument::create();

        $pageWidth = $this->pageSize->getWidth();
        $pageHeight = $this->pageSize->getHeight();
        $contentWidth = $pageWidth - $this->marginLeft - $this->marginRight;
        $contentHeight = $pageHeight - $this->marginTop - $this->marginBottom;

        // PPTX coordinates are typically based on slide dimensions
        // Standard slide is approximately 10" x 7.5" (720 x 540 points)
        $slideWidth = 720.0;
        $slideHeight = 540.0;

        // Calculate scale to fit slide in content area
        $scaleX = $contentWidth / $slideWidth;
        $scaleY = $contentHeight / $slideHeight;
        $scale = min($scaleX, $scaleY);

        // Center the slide
        $offsetX = $this->marginLeft + ($contentWidth - $slideWidth * $scale) / 2;
        $offsetY = $this->marginBottom + ($contentHeight - $slideHeight * $scale) / 2;

        foreach ($slides as $slide) {
            $page = new Page($this->pageSize);

            // Draw slide background (white rectangle)
            $page->addRectangle(
                $offsetX,
                $offsetY,
                $slideWidth * $scale,
                $slideHeight * $scale,
                [
                    'fill' => [255, 255, 255],
                    'stroke' => [200, 200, 200],
                    'lineWidth' => 1,
                ]
            );

            // Render content
            foreach ($slide['content'] as $item) {
                if ($item['type'] === 'text') {
                    $this->renderTextItem($page, $item, $scale, $offsetX, $offsetY, $slideHeight);
                }
            }

            // Add slide number
            if ($this->includeSlideNumbers) {
                $page->addText(
                    (string) $slide['number'],
                    $pageWidth - $this->marginRight - 20,
                    $this->marginBottom - 15,
                    [
                        'fontSize' => 10,
                        'color' => [128, 128, 128],
                    ]
                );
            }

            $pdf->addPageObject($page);
        }

        return $pdf;
    }

    /**
     * Render a text item to the page.
     *
     * @param array{type: string, text: string, x: float, y: float, width: float, height: float, style: array<string, mixed>} $item
     */
    private function renderTextItem(
        Page $page,
        array $item,
        float $scale,
        float $offsetX,
        float $offsetY,
        float $slideHeight
    ): void {
        $style = $item['style'];
        $fontSize = is_numeric($style['fontSize']) ? (float) $style['fontSize'] : $this->defaultFontSize;

        // Convert coordinates (PPTX uses top-left origin, PDF uses bottom-left)
        $x = $offsetX + $item['x'] * $scale;
        $y = $offsetY + ($slideHeight - $item['y']) * $scale - ($fontSize * $scale);

        $options = [
            'fontSize' => $fontSize * $scale,
            'fontFamily' => is_string($style['fontFamily']) ? $style['fontFamily'] : $this->defaultFontFamily,
        ];

        if ($style['bold'] ?? false) {
            $options['fontWeight'] = 'bold';
        }

        if ($style['italic'] ?? false) {
            $options['fontStyle'] = 'italic';
        }

        if (isset($style['color']) && is_string($style['color'])) {
            // Convert hex color to RGB
            $hex = $style['color'];
            if (strlen($hex) >= 6) {
                $options['color'] = [
                    hexdec(substr($hex, 0, 2)),
                    hexdec(substr($hex, 2, 2)),
                    hexdec(substr($hex, 4, 2)),
                ];
            }
        }

        // Handle multi-line text
        $lines = explode("\n", $item['text']);
        $lineHeight = ($fontSize * $scale) * 1.2;

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $page->addText($line, $x, $y, $options);
            }
            $y -= $lineHeight;
        }
    }
}
