<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PHPUnit\Framework\TestCase;
use PdfLib\Html\HtmlConverter;
use PdfLib\Html\HtmlParser;
use PdfLib\Html\HtmlElement;
use PdfLib\Html\LayoutEngine;
use PdfLib\Html\LayoutElement;
use PdfLib\Html\LayoutPage;
use PdfLib\Document\PdfDocument;
use PdfLib\Page\PageSize;

/**
 * Tests for HTML to PDF conversion.
 */
class HtmlConverterTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = __DIR__ . '/output';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    // =========================================================================
    // HtmlParser Tests
    // =========================================================================

    public function testParseSimpleHtml(): void
    {
        $html = '<p>Hello World</p>';
        $parser = new HtmlParser($html);
        $elements = $parser->parse();

        $this->assertNotEmpty($elements);
        $this->assertInstanceOf(HtmlElement::class, $elements[0]);
    }

    public function testParseHeadings(): void
    {
        $html = '<h1>Title</h1><h2>Subtitle</h2>';
        $parser = new HtmlParser($html);
        $elements = $parser->parse();

        $this->assertGreaterThanOrEqual(2, count($elements));
    }

    public function testParseNestedElements(): void
    {
        $html = '<div><p>Nested <strong>text</strong></p></div>';
        $parser = new HtmlParser($html);
        $elements = $parser->parse();

        $this->assertNotEmpty($elements);
    }

    public function testParseWithStyles(): void
    {
        $html = '<style>.red { color: red; }</style><p class="red">Red text</p>';
        $parser = new HtmlParser($html);
        $elements = $parser->parse();

        $this->assertNotEmpty($elements);
    }

    public function testParseTable(): void
    {
        $html = '<table><tr><td>Cell</td></tr></table>';
        $parser = new HtmlParser($html);
        $elements = $parser->parse();

        $this->assertNotEmpty($elements);
        $this->assertEquals('table', $elements[0]->getTagName());
    }

    public function testParseList(): void
    {
        $html = '<ul><li>Item 1</li><li>Item 2</li></ul>';
        $parser = new HtmlParser($html);
        $elements = $parser->parse();

        $this->assertNotEmpty($elements);
        $this->assertEquals('ul', $elements[0]->getTagName());
    }

    public function testParseInlineStyles(): void
    {
        $html = '<p style="color: blue; font-size: 14pt;">Styled</p>';
        $parser = new HtmlParser($html);
        $elements = $parser->parse();

        $this->assertNotEmpty($elements);
        $p = $elements[0];
        $this->assertEquals('blue', $p->getStyle('color'));
        $this->assertEquals('14pt', $p->getStyle('font-size'));
    }

    public function testParseAttributes(): void
    {
        $html = '<a href="https://example.com" id="link1" class="nav-link">Link</a>';
        $parser = new HtmlParser($html);
        $elements = $parser->parse();

        $this->assertNotEmpty($elements);
        $a = $elements[0];
        $this->assertEquals('https://example.com', $a->getAttribute('href'));
        $this->assertEquals('link1', $a->getId());
        $this->assertContains('nav-link', $a->getClasses());
    }

    // =========================================================================
    // HtmlElement Tests
    // =========================================================================

    public function testHtmlElementCreation(): void
    {
        $element = new HtmlElement('div');

        $this->assertEquals('div', $element->getTagName());
        $this->assertEquals('', $element->getContent());
        $this->assertFalse($element->hasChildren());
    }

    public function testHtmlElementWithContent(): void
    {
        $element = new HtmlElement('text', 'Hello');

        $this->assertEquals('text', $element->getTagName());
        $this->assertEquals('Hello', $element->getContent());
        $this->assertTrue($element->isText());
    }

    public function testHtmlElementChildren(): void
    {
        $parent = new HtmlElement('div');
        $child = new HtmlElement('p', 'Child');

        $parent->addChild($child);

        $this->assertTrue($parent->hasChildren());
        $this->assertCount(1, $parent->getChildren());
        $this->assertSame($parent, $child->getParent());
    }

    public function testHtmlElementIsBlock(): void
    {
        $div = new HtmlElement('div');
        $p = new HtmlElement('p');
        $span = new HtmlElement('span');

        $this->assertTrue($div->isBlock());
        $this->assertTrue($p->isBlock());
        $this->assertFalse($span->isBlock());
    }

    public function testHtmlElementIsInline(): void
    {
        $span = new HtmlElement('span');
        $strong = new HtmlElement('strong');
        $div = new HtmlElement('div');

        $this->assertTrue($span->isInline());
        $this->assertTrue($strong->isInline());
        $this->assertFalse($div->isInline());
    }

    public function testHtmlElementHeading(): void
    {
        $h1 = new HtmlElement('h1');
        $h3 = new HtmlElement('h3');
        $p = new HtmlElement('p');

        $this->assertTrue($h1->isHeading());
        $this->assertEquals(1, $h1->getHeadingLevel());
        $this->assertTrue($h3->isHeading());
        $this->assertEquals(3, $h3->getHeadingLevel());
        $this->assertFalse($p->isHeading());
        $this->assertEquals(0, $p->getHeadingLevel());
    }

    // =========================================================================
    // LayoutElement Tests
    // =========================================================================

    public function testLayoutElementText(): void
    {
        $element = LayoutElement::text('Hello', 100, 500);

        $this->assertEquals('text', $element->getType());
        $this->assertEquals('Hello', $element->getContent());
        $this->assertEquals(100, $element->getX());
        $this->assertEquals(500, $element->getY());
    }

    public function testLayoutElementImage(): void
    {
        $element = LayoutElement::image('image.png', 50, 400, 200, 150);

        $this->assertEquals('image', $element->getType());
        $this->assertEquals('image.png', $element->getContent());
        $this->assertEquals(200, $element->getWidth());
        $this->assertEquals(150, $element->getHeight());
    }

    public function testLayoutElementFontProperties(): void
    {
        $element = LayoutElement::text('Test', 0, 0);
        $element->setFontFamily('Courier');
        $element->setFontSize(14);
        $element->setBold(true);
        $element->setItalic(true);

        $this->assertEquals('Courier', $element->getFontFamily());
        $this->assertEquals(14, $element->getFontSize());
        $this->assertTrue($element->isBold());
        $this->assertTrue($element->isItalic());
    }

    public function testLayoutElementColors(): void
    {
        $element = LayoutElement::text('Test', 0, 0);
        $element->setColor('#ff0000');
        $element->setBackgroundColor('blue');

        $color = $element->getColor();
        $this->assertNotNull($color);
        $this->assertEquals(1.0, $color[0]); // Red

        $bgColor = $element->getBackgroundColor();
        $this->assertNotNull($bgColor);
        $this->assertEquals(1.0, $bgColor[2]); // Blue
    }

    // =========================================================================
    // LayoutPage Tests
    // =========================================================================

    public function testLayoutPageCreation(): void
    {
        $page = new LayoutPage(1, 595.28, 841.89);

        $this->assertEquals(1, $page->getPageNumber());
        $this->assertEquals(595.28, $page->getWidth());
        $this->assertEquals(841.89, $page->getHeight());
        $this->assertFalse($page->hasElements());
    }

    public function testLayoutPageElements(): void
    {
        $page = new LayoutPage(1, 595.28, 841.89);
        $element = LayoutElement::text('Test', 100, 500);

        $page->addElement($element);

        $this->assertTrue($page->hasElements());
        $this->assertEquals(1, $page->getElementCount());
        $this->assertContains($element, $page->getElements());
    }

    // =========================================================================
    // LayoutEngine Tests
    // =========================================================================

    public function testLayoutEngineBasic(): void
    {
        $engine = new LayoutEngine(PageSize::a4());
        $engine->setDefaultFont('Helvetica', 12);

        $elements = [
            new HtmlElement('p', 'Test paragraph'),
        ];

        $pages = $engine->layout($elements);

        $this->assertNotEmpty($pages);
        $this->assertInstanceOf(LayoutPage::class, $pages[0]);
    }

    public function testLayoutEngineMultipleElements(): void
    {
        $engine = new LayoutEngine(PageSize::a4());

        $elements = [
            new HtmlElement('h1', 'Title'),
            new HtmlElement('p', 'Paragraph 1'),
            new HtmlElement('p', 'Paragraph 2'),
        ];

        $pages = $engine->layout($elements);

        $this->assertNotEmpty($pages);
        $this->assertTrue($pages[0]->hasElements());
    }

    // =========================================================================
    // HtmlConverter Tests
    // =========================================================================

    public function testConverterCreate(): void
    {
        $converter = HtmlConverter::create();

        $this->assertInstanceOf(HtmlConverter::class, $converter);
    }

    public function testConverterSimpleHtml(): void
    {
        $converter = HtmlConverter::create();
        $pdf = $converter->convert('<p>Hello World</p>');

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterWithHeadings(): void
    {
        $html = <<<'HTML'
        <h1>Main Title</h1>
        <h2>Section 1</h2>
        <p>Content here.</p>
        <h2>Section 2</h2>
        <p>More content.</p>
        HTML;

        $converter = HtmlConverter::create();
        $pdf = $converter->convert($html);

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterWithList(): void
    {
        $html = <<<'HTML'
        <ul>
            <li>Item 1</li>
            <li>Item 2</li>
            <li>Item 3</li>
        </ul>
        HTML;

        $converter = HtmlConverter::create();
        $pdf = $converter->convert($html);

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterWithTable(): void
    {
        $html = <<<'HTML'
        <table border="1">
            <tr><th>Header 1</th><th>Header 2</th></tr>
            <tr><td>Cell 1</td><td>Cell 2</td></tr>
        </table>
        HTML;

        $converter = HtmlConverter::create();
        $pdf = $converter->convert($html);

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterPageSize(): void
    {
        $converter = HtmlConverter::create()
            ->setPageSize('Letter');

        $pdf = $converter->convert('<p>Letter size</p>');

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterLandscape(): void
    {
        $converter = HtmlConverter::create()
            ->setPageSize('A4')
            ->setLandscape(true);

        $pdf = $converter->convert('<p>Landscape</p>');

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterMargins(): void
    {
        $converter = HtmlConverter::create()
            ->setMargins(72, 72, 72, 72);

        $pdf = $converter->convert('<p>Custom margins</p>');

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterUniformMargin(): void
    {
        $converter = HtmlConverter::create()
            ->setMargin(50);

        $pdf = $converter->convert('<p>Uniform margin</p>');

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterDefaultFont(): void
    {
        $converter = HtmlConverter::create()
            ->setDefaultFont('Courier', 10);

        $pdf = $converter->convert('<p>Courier font</p>');

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterSaveToFile(): void
    {
        $outputPath = $this->outputDir . '/html-test.pdf';

        $converter = HtmlConverter::create();
        $pdf = $converter->convert('<h1>Test</h1><p>This is a test PDF.</p>');
        $pdf->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));

        // Verify it starts with PDF header
        $content = file_get_contents($outputPath);
        $this->assertStringStartsWith('%PDF-', $content);
    }

    public function testConverterComplexDocument(): void
    {
        $html = <<<'HTML'
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                h1 { color: navy; font-size: 24pt; }
                p { margin-bottom: 10pt; }
                .highlight { background-color: yellow; }
            </style>
        </head>
        <body>
            <h1>Complex Document</h1>

            <p>This is a <strong>complex</strong> document with various elements.</p>

            <h2>List Section</h2>
            <ul>
                <li>First item</li>
                <li>Second item</li>
            </ul>

            <h2>Table Section</h2>
            <table border="1">
                <tr><th>Name</th><th>Value</th></tr>
                <tr><td>A</td><td>100</td></tr>
                <tr><td>B</td><td>200</td></tr>
            </table>

            <blockquote>
                This is a blockquote with some text.
            </blockquote>

            <pre>
            function hello() {
                return "world";
            }
            </pre>
        </body>
        </html>
        HTML;

        $outputPath = $this->outputDir . '/html-complex.pdf';

        $converter = HtmlConverter::create()
            ->setPageSize('A4')
            ->setMargins(50, 50, 50, 50);

        $pdf = $converter->convert($html);
        $pdf->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(1000, filesize($outputPath));
    }

    public function testConverterFromFile(): void
    {
        // Create a temp HTML file
        $htmlPath = $this->outputDir . '/test.html';
        $htmlContent = '<h1>From File</h1><p>This was loaded from a file.</p>';
        file_put_contents($htmlPath, $htmlContent);

        $converter = HtmlConverter::create();
        $pdf = $converter->convertFile($htmlPath);

        $this->assertInstanceOf(PdfDocument::class, $pdf);

        unlink($htmlPath);
    }

    public function testConverterFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $converter = HtmlConverter::create();
        $converter->convertFile('/nonexistent/file.html');
    }

    public function testConverterChainedMethods(): void
    {
        $converter = HtmlConverter::create()
            ->setPageSize('A4')
            ->setLandscape(false)
            ->setMargins(50, 50, 50, 50)
            ->setDefaultFont('Helvetica', 12)
            ->setEncoding('UTF-8')
            ->enableImages(true)
            ->enableLinks(true);

        $pdf = $converter->convert('<p>Chained methods test</p>');

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterDefaultStyles(): void
    {
        $converter = HtmlConverter::create()
            ->setDefaultStyles([
                'body' => ['font-size' => '14pt'],
                'p' => ['margin-bottom' => '15pt'],
            ]);

        $pdf = $converter->convert('<p>Custom default styles</p>');

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterEmptyHtml(): void
    {
        $converter = HtmlConverter::create();
        $pdf = $converter->convert('');

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testConverterMalformedHtml(): void
    {
        // The converter should handle malformed HTML gracefully
        $html = '<p>Unclosed paragraph<div>Mixed tags</p></div>';

        $converter = HtmlConverter::create();
        $pdf = $converter->convert($html);

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    // =========================================================================
    // Real-World HTML to PDF Examples
    // =========================================================================

    public function testConverterProfessionalInvoice(): void
    {
        $invoiceHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; }
        .header { margin-bottom: 30pt; }
        .company { font-size: 18pt; font-weight: bold; color: #2c3e50; }
        .invoice-title { font-size: 32pt; color: #e74c3c; }
        table { width: 100%; border-collapse: collapse; margin: 20pt 0; }
        th { background-color: #34495e; color: white; padding: 10pt; text-align: left; }
        td { padding: 10pt; border-bottom: 1pt solid #ecf0f1; }
        .amount { text-align: right; }
        .total-row { background-color: #ecf0f1; font-weight: bold; }
        .grand-total { background-color: #2c3e50; color: white; font-size: 14pt; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">ACME Corporation</div>
        <p>123 Business Street, New York, NY 10001</p>
    </div>

    <div class="invoice-title">INVOICE</div>

    <table>
        <tr><td><strong>Invoice #:</strong></td><td>INV-2026-0001</td></tr>
        <tr><td><strong>Date:</strong></td><td>January 5, 2026</td></tr>
        <tr><td><strong>Due Date:</strong></td><td>February 5, 2026</td></tr>
    </table>

    <h2>Bill To:</h2>
    <p><strong>John Smith</strong><br>XYZ Company<br>456 Customer Avenue<br>Los Angeles, CA 90001</p>

    <table>
        <thead>
            <tr>
                <th style="width: 50%">Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Web Development Services</td>
                <td>40 hrs</td>
                <td class="amount">$100.00</td>
                <td class="amount">$4,000.00</td>
            </tr>
            <tr>
                <td>UI/UX Design</td>
                <td>20 hrs</td>
                <td class="amount">$85.00</td>
                <td class="amount">$1,700.00</td>
            </tr>
            <tr>
                <td>Server Hosting (Annual)</td>
                <td>1</td>
                <td class="amount">$500.00</td>
                <td class="amount">$500.00</td>
            </tr>
            <tr class="total-row">
                <td colspan="3">Subtotal</td>
                <td class="amount">$6,200.00</td>
            </tr>
            <tr>
                <td colspan="3">Tax (8%)</td>
                <td class="amount">$496.00</td>
            </tr>
            <tr class="grand-total">
                <td colspan="3"><strong>TOTAL DUE</strong></td>
                <td class="amount"><strong>$6,696.00</strong></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 40pt; padding-top: 20pt; border-top: 1pt solid #bdc3c7; color: #7f8c8d; font-size: 9pt;">
        <p><strong>Payment Terms:</strong> Net 30</p>
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
HTML;

        $outputPath = $this->outputDir . '/invoice.pdf';

        $converter = HtmlConverter::create()
            ->setPageSize('Letter')
            ->setMargins(72, 72, 72, 72);

        $pdf = $converter->convert($invoiceHtml);
        $pdf->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(1000, filesize($outputPath));

        // Verify PDF structure
        $content = file_get_contents($outputPath);
        $this->assertStringStartsWith('%PDF-', $content);
    }

    public function testConverterStyledMonthlyReport(): void
    {
        $reportHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Helvetica; font-size: 11pt; line-height: 1.5; }
        h1 { color: #2c3e50; border-bottom: 2pt solid #3498db; padding-bottom: 10pt; }
        h2 { color: #34495e; }
        .highlight { background-color: #fff3cd; padding: 10pt; border-left: 4pt solid #ffc107; }
        .info { background-color: #d1ecf1; padding: 10pt; border-left: 4pt solid #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin: 15pt 0; }
        th, td { border: 1pt solid #dee2e6; padding: 8pt; text-align: left; }
        th { background-color: #343a40; color: white; }
        tr:nth-child(even) { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <h1>Monthly Report - January 2026</h1>

    <h2>Executive Summary</h2>
    <p>This report provides an overview of key metrics and achievements for the month.</p>

    <div class="highlight">
        <strong>Key Achievement:</strong> Revenue increased by 15% compared to last month!
    </div>

    <h2>Sales Performance</h2>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Units Sold</th>
                <th>Revenue</th>
                <th>Growth</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Product A</td>
                <td>1,250</td>
                <td>$125,000</td>
                <td>+12%</td>
            </tr>
            <tr>
                <td>Product B</td>
                <td>890</td>
                <td>$89,000</td>
                <td>+8%</td>
            </tr>
            <tr>
                <td>Product C</td>
                <td>650</td>
                <td>$65,000</td>
                <td>+22%</td>
            </tr>
        </tbody>
    </table>

    <div class="info">
        <strong>Note:</strong> All figures are preliminary and subject to final audit.
    </div>

    <h2>Next Steps</h2>
    <ol>
        <li>Review marketing strategy for Q2</li>
        <li>Expand Product C distribution</li>
        <li>Launch customer feedback program</li>
    </ol>
</body>
</html>
HTML;

        $outputPath = $this->outputDir . '/monthly-report.pdf';

        $converter = HtmlConverter::create()
            ->setPageSize('A4')
            ->setMargins(50, 50, 50, 50);

        $pdf = $converter->convert($reportHtml);
        $pdf->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(1000, filesize($outputPath));
    }

    public function testConverterSimpleHtmlToPdf(): void
    {
        $simpleHtml = <<<'HTML'
<h1>Hello World</h1>
<p>This is a <strong>simple</strong> HTML to PDF conversion.</p>
<p>Features include:</p>
<ul>
    <li>Bold and <em>italic</em> text</li>
    <li>Links: <a href="https://example.com">Click here</a></li>
    <li>Lists (ordered and unordered)</li>
</ul>
HTML;

        $outputPath = $this->outputDir . '/simple-html.pdf';

        $pdf = HtmlConverter::create()->convert($simpleHtml);
        $pdf->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    public function testConverterRenderToBinary(): void
    {
        $html = '<h1>Binary Output Test</h1><p>This tests the render() method.</p>';

        $converter = HtmlConverter::create();
        $pdf = $converter->convert($html);
        $content = $pdf->render();

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(100, strlen($content));
    }
}
