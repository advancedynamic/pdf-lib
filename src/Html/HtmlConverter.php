<?php

declare(strict_types=1);

namespace PdfLib\Html;

use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;

/**
 * HTML to PDF Converter.
 *
 * Converts HTML content to PDF documents using pure PHP.
 * Supports common HTML elements and CSS styling.
 *
 * @example
 * ```php
 * $converter = new HtmlConverter();
 * $pdf = $converter->convert('<h1>Hello World</h1><p>This is a paragraph.</p>');
 * $pdf->save('output.pdf');
 *
 * // Or with options
 * $converter = HtmlConverter::create()
 *     ->setPageSize('A4')
 *     ->setMargins(50, 50, 50, 50)
 *     ->setDefaultFont('Helvetica', 12);
 *
 * $pdf = $converter->convertFile('document.html');
 * ```
 */
class HtmlConverter
{
    private PageSize $pageSize;
    private float $marginTop = 50;
    private float $marginRight = 50;
    private float $marginBottom = 50;
    private float $marginLeft = 50;
    private string $defaultFontFamily = 'Helvetica';
    private float $defaultFontSize = 12;
    private string $defaultEncoding = 'UTF-8';

    /** @var array<string, mixed> */
    private array $defaultStyles = [];

    /** @var array<string, string> */
    private array $fonts = [];

    private ?string $baseUrl = null;
    private bool $enableImages = true;
    private bool $enableLinks = true;

    public function __construct()
    {
        $this->pageSize = PageSize::a4();
        $this->initDefaultStyles();
    }

    /**
     * Create a new converter instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Convert HTML string to PDF.
     */
    public function convert(string $html): PdfDocument
    {
        $parser = new HtmlParser($html, $this->defaultEncoding);
        $elements = $parser->parse();

        $layoutEngine = new LayoutEngine(
            $this->pageSize,
            $this->marginTop,
            $this->marginRight,
            $this->marginBottom,
            $this->marginLeft
        );
        $layoutEngine->setDefaultFont($this->defaultFontFamily, $this->defaultFontSize);
        $layoutEngine->setDefaultStyles($this->defaultStyles);

        $pages = $layoutEngine->layout($elements);

        return $this->renderPages($pages);
    }

    /**
     * Convert HTML file to PDF.
     */
    public function convertFile(string $path): PdfDocument
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("HTML file not found: $path");
        }

        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException("Could not read HTML file: $path");
        }

        // Set base URL for relative resources
        if ($this->baseUrl === null) {
            $this->baseUrl = dirname(realpath($path));
        }

        return $this->convert($html);
    }

    /**
     * Convert HTML from URL to PDF.
     */
    public function convertUrl(string $url): PdfDocument
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: PdfLib/1.0\r\n",
                'timeout' => 30,
            ],
        ]);

        $html = file_get_contents($url, false, $context);
        if ($html === false) {
            throw new \RuntimeException("Could not fetch URL: $url");
        }

        $this->baseUrl = dirname($url);

        return $this->convert($html);
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
        if ($landscape) {
            $this->pageSize = $this->pageSize->landscape();
        }

        return $this;
    }

    /**
     * Set page margins.
     */
    public function setMargins(
        float $top,
        float $right,
        float $bottom,
        float $left
    ): self {
        $this->marginTop = $top;
        $this->marginRight = $right;
        $this->marginBottom = $bottom;
        $this->marginLeft = $left;

        return $this;
    }

    /**
     * Set uniform margins.
     */
    public function setMargin(float $margin): self
    {
        return $this->setMargins($margin, $margin, $margin, $margin);
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
     * Set character encoding.
     */
    public function setEncoding(string $encoding): self
    {
        $this->defaultEncoding = $encoding;

        return $this;
    }

    /**
     * Set base URL for relative resources.
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');

        return $this;
    }

    /**
     * Enable or disable image loading.
     */
    public function enableImages(bool $enable = true): self
    {
        $this->enableImages = $enable;

        return $this;
    }

    /**
     * Enable or disable link handling.
     */
    public function enableLinks(bool $enable = true): self
    {
        $this->enableLinks = $enable;

        return $this;
    }

    /**
     * Set a CSS style default.
     *
     * @param array<string, mixed> $styles
     */
    public function setDefaultStyles(array $styles): self
    {
        $this->defaultStyles = array_merge($this->defaultStyles, $styles);

        return $this;
    }

    /**
     * Initialize default CSS styles for HTML elements.
     */
    private function initDefaultStyles(): void
    {
        $this->defaultStyles = [
            'body' => [
                'font-family' => 'Helvetica',
                'font-size' => '12pt',
                'line-height' => '1.4',
                'color' => '#000000',
            ],
            'h1' => [
                'font-size' => '24pt',
                'font-weight' => 'bold',
                'margin-top' => '20pt',
                'margin-bottom' => '10pt',
            ],
            'h2' => [
                'font-size' => '20pt',
                'font-weight' => 'bold',
                'margin-top' => '18pt',
                'margin-bottom' => '8pt',
            ],
            'h3' => [
                'font-size' => '16pt',
                'font-weight' => 'bold',
                'margin-top' => '16pt',
                'margin-bottom' => '6pt',
            ],
            'h4' => [
                'font-size' => '14pt',
                'font-weight' => 'bold',
                'margin-top' => '14pt',
                'margin-bottom' => '4pt',
            ],
            'h5' => [
                'font-size' => '12pt',
                'font-weight' => 'bold',
                'margin-top' => '12pt',
                'margin-bottom' => '4pt',
            ],
            'h6' => [
                'font-size' => '10pt',
                'font-weight' => 'bold',
                'margin-top' => '10pt',
                'margin-bottom' => '4pt',
            ],
            'p' => [
                'margin-top' => '0pt',
                'margin-bottom' => '10pt',
            ],
            'ul' => [
                'margin-top' => '0pt',
                'margin-bottom' => '10pt',
                'padding-left' => '20pt',
            ],
            'ol' => [
                'margin-top' => '0pt',
                'margin-bottom' => '10pt',
                'padding-left' => '20pt',
            ],
            'li' => [
                'margin-bottom' => '4pt',
            ],
            'table' => [
                'border-collapse' => 'collapse',
                'margin-bottom' => '10pt',
            ],
            'th' => [
                'font-weight' => 'bold',
                'padding' => '5pt',
                'border' => '1pt solid #000000',
                'background-color' => '#eeeeee',
            ],
            'td' => [
                'padding' => '5pt',
                'border' => '1pt solid #000000',
            ],
            'a' => [
                'color' => '#0000ff',
                'text-decoration' => 'underline',
            ],
            'strong' => [
                'font-weight' => 'bold',
            ],
            'b' => [
                'font-weight' => 'bold',
            ],
            'em' => [
                'font-style' => 'italic',
            ],
            'i' => [
                'font-style' => 'italic',
            ],
            'u' => [
                'text-decoration' => 'underline',
            ],
            'hr' => [
                'margin-top' => '10pt',
                'margin-bottom' => '10pt',
                'border-top' => '1pt solid #000000',
            ],
            'blockquote' => [
                'margin-left' => '20pt',
                'margin-right' => '20pt',
                'padding-left' => '10pt',
                'border-left' => '3pt solid #cccccc',
                'color' => '#666666',
            ],
            'code' => [
                'font-family' => 'Courier',
                'background-color' => '#f5f5f5',
                'padding' => '2pt',
            ],
            'pre' => [
                'font-family' => 'Courier',
                'background-color' => '#f5f5f5',
                'padding' => '10pt',
                'margin-bottom' => '10pt',
                'white-space' => 'pre',
            ],
            'img' => [
                'max-width' => '100%',
            ],
        ];
    }

    /**
     * Render layout pages to PDF document.
     *
     * @param array<LayoutPage> $pages
     */
    private function renderPages(array $pages): PdfDocument
    {
        $pdf = PdfDocument::create();

        foreach ($pages as $layoutPage) {
            $page = new Page($this->pageSize);
            $this->renderLayoutPage($page, $layoutPage);
            $pdf->addPageObject($page);
        }

        return $pdf;
    }

    /**
     * Render a layout page to a PDF page.
     */
    private function renderLayoutPage(Page $page, LayoutPage $layoutPage): void
    {
        foreach ($layoutPage->getElements() as $element) {
            $this->renderElement($page, $element);
        }
    }

    /**
     * Render a layout element to the page.
     */
    private function renderElement(Page $page, LayoutElement $element): void
    {
        switch ($element->getType()) {
            case 'text':
                $this->renderText($page, $element);
                break;
            case 'image':
                $this->renderImage($page, $element);
                break;
            case 'line':
                $this->renderLine($page, $element);
                break;
            case 'rectangle':
                $this->renderRectangle($page, $element);
                break;
        }
    }

    /**
     * Render text element.
     */
    private function renderText(Page $page, LayoutElement $element): void
    {
        $options = [
            'fontSize' => $element->getFontSize(),
            'fontFamily' => $element->getFontFamily(),
        ];

        if ($element->isBold()) {
            $options['fontWeight'] = 'bold';
        }

        if ($element->isItalic()) {
            $options['fontStyle'] = 'italic';
        }

        $color = $element->getColor();
        if ($color !== null) {
            $options['color'] = $color;
        }

        $page->addText(
            $element->getContent(),
            $element->getX(),
            $element->getY(),
            $options
        );
    }

    /**
     * Render image element.
     */
    private function renderImage(Page $page, LayoutElement $element): void
    {
        if (!$this->enableImages) {
            return;
        }

        $src = $element->getContent();

        // Handle relative URLs
        if ($this->baseUrl !== null && !preg_match('#^https?://#i', $src)) {
            $src = $this->baseUrl . '/' . ltrim($src, '/');
        }

        try {
            $page->addImage(
                $src,
                $element->getX(),
                $element->getY(),
                $element->getWidth(),
                $element->getHeight()
            );
        } catch (\Exception $e) {
            // Skip images that can't be loaded
        }
    }

    /**
     * Render line element (for hr, borders).
     */
    private function renderLine(Page $page, LayoutElement $element): void
    {
        // Lines are rendered as part of the content stream
        $page->addLine(
            $element->getX(),
            $element->getY(),
            $element->getX() + $element->getWidth(),
            $element->getY(),
            [
                'color' => $element->getColor() ?? [0, 0, 0],
                'lineWidth' => $element->getLineWidth(),
            ]
        );
    }

    /**
     * Render rectangle element (for backgrounds, borders).
     */
    private function renderRectangle(Page $page, LayoutElement $element): void
    {
        $options = [];

        $bgColor = $element->getBackgroundColor();
        if ($bgColor !== null) {
            $options['fill'] = $bgColor;
        }

        $borderColor = $element->getBorderColor();
        if ($borderColor !== null) {
            $options['stroke'] = $borderColor;
            $options['lineWidth'] = $element->getLineWidth();
        }

        if (!empty($options)) {
            $page->addRectangle(
                $element->getX(),
                $element->getY(),
                $element->getWidth(),
                $element->getHeight(),
                $options
            );
        }
    }
}
