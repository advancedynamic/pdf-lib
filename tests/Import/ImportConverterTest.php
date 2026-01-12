<?php

declare(strict_types=1);

namespace PdfLib\Tests\Import;

use PHPUnit\Framework\TestCase;
use PdfLib\Import\Docx\DocxToPdfConverter;
use PdfLib\Import\Xlsx\XlsxToPdfConverter;
use PdfLib\Import\Pptx\PptxToPdfConverter;
use PdfLib\Export\Docx\DocxWriter;
use PdfLib\Export\Docx\DocxParagraph;
use PdfLib\Export\Xlsx\XlsxWriter;
use PdfLib\Document\PdfDocument;
use PdfLib\Page\PageSize;

/**
 * Tests for Office document to PDF converters.
 *
 * Tests DOCX, XLSX, and PPTX to PDF conversion functionality.
 */
class ImportConverterTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = __DIR__ . '/../output';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    // =========================================================================
    // DocxToPdfConverter Tests
    // =========================================================================

    public function testDocxToPdfConverterCreate(): void
    {
        $converter = DocxToPdfConverter::create();
        $this->assertInstanceOf(DocxToPdfConverter::class, $converter);
    }

    public function testDocxToPdfConverterFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $converter = new DocxToPdfConverter();
        $converter->toPdfDocument('/nonexistent/file.docx');
    }

    public function testDocxToPdfConverterSimpleDocument(): void
    {
        // Create a simple DOCX file
        $docxPath = $this->outputDir . '/test-simple.docx';
        $writer = new DocxWriter();
        $writer->addHeading('Test Document', 1);
        $writer->addText('This is a test paragraph.');
        $writer->addText('This is another paragraph with more content.');
        $writer->save($docxPath);

        // Convert to PDF
        $converter = new DocxToPdfConverter();
        $pdf = $converter->toPdfDocument($docxPath);

        $this->assertInstanceOf(PdfDocument::class, $pdf);

        // Save and verify
        $pdfPath = $this->outputDir . '/converted-simple.pdf';
        $pdf->save($pdfPath);

        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath));
    }

    public function testDocxToPdfConverterWithOptions(): void
    {
        // Create DOCX
        $docxPath = $this->outputDir . '/test-options.docx';
        $writer = new DocxWriter();
        $writer->setPageSizeA4();
        $writer->addHeading('Report Title', 1);
        $writer->addHeading('Section 1', 2);
        $writer->addText('Content for section 1.');
        $writer->addHeading('Section 2', 2);
        $writer->addText('Content for section 2.');
        $writer->save($docxPath);

        // Convert with options
        $converter = DocxToPdfConverter::create()
            ->setPageSize('A4')
            ->setMargins(72, 72, 72, 72)
            ->setDefaultFont('Helvetica', 11);

        $pdfPath = $this->outputDir . '/converted-options.pdf';
        $converter->convert($docxPath, $pdfPath);

        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath));
    }

    public function testDocxToPdfConverterConvertToString(): void
    {
        // Create DOCX
        $docxPath = $this->outputDir . '/test-string.docx';
        $writer = new DocxWriter();
        $writer->addText('Binary content test');
        $writer->save($docxPath);

        // Convert to string
        $converter = new DocxToPdfConverter();
        $content = $converter->convertToString($docxPath);

        $this->assertNotEmpty($content);
        // PDF signature
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function testDocxToPdfConverterFormattedDocument(): void
    {
        // Create DOCX with formatting
        $docxPath = $this->outputDir . '/test-formatted.docx';
        $writer = new DocxWriter();
        $writer->setDefaultFont('Arial', 12);

        $writer->addHeading('Formatted Document', 1);

        $paragraph = new DocxParagraph();
        $paragraph->addRun('This text is ');
        $paragraph->addRun('bold', true);
        $paragraph->addRun(', this is ');
        $paragraph->addRun('italic', false, true);
        $paragraph->addRun(', and this is ');
        $paragraph->addRun('underlined', false, false, true);
        $paragraph->addRun('.');
        $writer->addParagraph($paragraph);

        $writer->addPageBreak();
        $writer->addHeading('Page 2', 1);
        $writer->addText('Content on the second page.');

        $writer->save($docxPath);

        // Convert
        $converter = new DocxToPdfConverter();
        $pdfPath = $this->outputDir . '/converted-formatted.pdf';
        $converter->convert($docxPath, $pdfPath);

        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath));
    }

    public function testDocxToPdfConverterPageSizes(): void
    {
        $docxPath = $this->outputDir . '/test-pagesize.docx';
        $writer = new DocxWriter();
        $writer->addText('Page size test');
        $writer->save($docxPath);

        $sizes = ['a4', 'letter', 'legal', 'a3', 'a5'];

        foreach ($sizes as $size) {
            $converter = DocxToPdfConverter::create()
                ->setPageSize($size);

            $pdfPath = $this->outputDir . "/converted-{$size}.pdf";
            $converter->convert($docxPath, $pdfPath);

            $this->assertFileExists($pdfPath);
        }
    }

    // =========================================================================
    // XlsxToPdfConverter Tests
    // =========================================================================

    public function testXlsxToPdfConverterCreate(): void
    {
        $converter = XlsxToPdfConverter::create();
        $this->assertInstanceOf(XlsxToPdfConverter::class, $converter);
    }

    public function testXlsxToPdfConverterFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $converter = new XlsxToPdfConverter();
        $converter->toPdfDocument('/nonexistent/file.xlsx');
    }

    public function testXlsxToPdfConverterSimpleSpreadsheet(): void
    {
        // Create a simple XLSX file
        $xlsxPath = $this->outputDir . '/test-simple.xlsx';
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Data');
        $sheet->setRow(1, ['Name', 'Age', 'City'], 1);
        $sheet->setRow(2, ['John', 30, 'New York']);
        $sheet->setRow(3, ['Jane', 25, 'Los Angeles']);
        $sheet->setRow(4, ['Bob', 35, 'Chicago']);
        $writer->save($xlsxPath);

        // Convert to PDF
        $converter = new XlsxToPdfConverter();
        $pdf = $converter->toPdfDocument($xlsxPath);

        $this->assertInstanceOf(PdfDocument::class, $pdf);

        // Save and verify
        $pdfPath = $this->outputDir . '/converted-spreadsheet.pdf';
        $pdf->save($pdfPath);

        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath));
    }

    public function testXlsxToPdfConverterWithFormulas(): void
    {
        // Create XLSX with formulas
        $xlsxPath = $this->outputDir . '/test-formulas.xlsx';
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Sales');

        $sheet->setRow(1, ['Product', 'Q1', 'Q2', 'Q3', 'Q4', 'Total'], 1);
        $sheet->setRow(2, ['Widget A', 1000, 1200, 1100, 1500, '=SUM(B2:E2)']);
        $sheet->setRow(3, ['Widget B', 800, 900, 1000, 1100, '=SUM(B3:E3)']);
        $sheet->setRow(4, ['Total', '=SUM(B2:B3)', '=SUM(C2:C3)', '=SUM(D2:D3)', '=SUM(E2:E3)', '=SUM(F2:F3)'], 1);

        $writer->save($xlsxPath);

        // Convert
        $converter = XlsxToPdfConverter::create()
            ->setLandscape(true)
            ->showGridlines(true);

        $pdfPath = $this->outputDir . '/converted-formulas.pdf';
        $converter->convert($xlsxPath, $pdfPath);

        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath));
    }

    public function testXlsxToPdfConverterMultipleSheets(): void
    {
        // Create XLSX with multiple sheets
        $xlsxPath = $this->outputDir . '/test-multisheets.xlsx';
        $writer = new XlsxWriter();

        $sheet1 = $writer->addSheet('Q1 Data');
        $sheet1->setRow(1, ['Month', 'Revenue']);
        $sheet1->setRow(2, ['January', 10000]);
        $sheet1->setRow(3, ['February', 12000]);
        $sheet1->setRow(4, ['March', 11500]);

        $sheet2 = $writer->addSheet('Q2 Data');
        $sheet2->setRow(1, ['Month', 'Revenue']);
        $sheet2->setRow(2, ['April', 13000]);
        $sheet2->setRow(3, ['May', 14500]);
        $sheet2->setRow(4, ['June', 15000]);

        $writer->save($xlsxPath);

        // Convert with sheet per page
        $converter = XlsxToPdfConverter::create()
            ->setSheetPerPage(true);

        $pdfPath = $this->outputDir . '/converted-multisheets.pdf';
        $converter->convert($xlsxPath, $pdfPath);

        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath));
    }

    public function testXlsxToPdfConverterSelectiveSheets(): void
    {
        // Create XLSX with multiple sheets
        $xlsxPath = $this->outputDir . '/test-selective.xlsx';
        $writer = new XlsxWriter();

        $writer->addSheet('Sheet1')->setRow(1, ['Sheet 1 Data']);
        $writer->addSheet('Sheet2')->setRow(1, ['Sheet 2 Data']);
        $writer->addSheet('Sheet3')->setRow(1, ['Sheet 3 Data']);

        $writer->save($xlsxPath);

        // Convert only sheet 2
        $converter = XlsxToPdfConverter::create()
            ->setSheets([2]);

        $pdfPath = $this->outputDir . '/converted-selective.pdf';
        $converter->convert($xlsxPath, $pdfPath);

        $this->assertFileExists($pdfPath);
    }

    public function testXlsxToPdfConverterConvertToString(): void
    {
        $xlsxPath = $this->outputDir . '/test-xlsx-string.xlsx';
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Test');
        $sheet->setRow(1, ['Binary test']);
        $writer->save($xlsxPath);

        $converter = new XlsxToPdfConverter();
        $content = $converter->convertToString($xlsxPath);

        $this->assertNotEmpty($content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function testXlsxToPdfConverterOptions(): void
    {
        $xlsxPath = $this->outputDir . '/test-xlsx-options.xlsx';
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Options Test');
        $sheet->setRow(1, ['Column A', 'Column B', 'Column C']);
        $sheet->setRow(2, ['Data 1', 'Data 2', 'Data 3']);
        $writer->save($xlsxPath);

        $converter = XlsxToPdfConverter::create()
            ->setPageSize('letter')
            ->setLandscape(false)
            ->setMargins(50, 50, 50, 50)
            ->setDefaultFont('Courier', 9)
            ->showGridlines(false)
            ->showHeaders(false);

        $pdfPath = $this->outputDir . '/converted-xlsx-options.pdf';
        $converter->convert($xlsxPath, $pdfPath);

        $this->assertFileExists($pdfPath);
    }

    public function testXlsxToPdfConverterFinancialReport(): void
    {
        // Create financial report XLSX
        $xlsxPath = $this->outputDir . '/test-financial.xlsx';
        $writer = new XlsxWriter();

        $sheet = $writer->addSheet('Income Statement');
        $sheet->setRow(1, ['Income Statement', '', '', ''], 1);
        $sheet->setRow(2, ['For Year Ended 2024']);
        $sheet->setRow(3, []);
        $sheet->setRow(4, ['Revenue', '', '', ''], 1);
        $sheet->setRow(5, ['Product Sales', '', '', 1500000]);
        $sheet->setRow(6, ['Service Revenue', '', '', 250000]);
        $sheet->setRow(7, ['Total Revenue', '', '', '=D5+D6'], 1);
        $sheet->setRow(8, []);
        $sheet->setRow(9, ['Expenses', '', '', ''], 1);
        $sheet->setRow(10, ['Cost of Goods Sold', '', '', 750000]);
        $sheet->setRow(11, ['Operating Expenses', '', '', 350000]);
        $sheet->setRow(12, ['Total Expenses', '', '', '=D10+D11'], 1);
        $sheet->setRow(13, []);
        $sheet->setRow(14, ['Net Income', '', '', '=D7-D12'], 1);

        $writer->save($xlsxPath);

        $converter = XlsxToPdfConverter::create()
            ->setPageSize('A4')
            ->showGridlines(true);

        $pdfPath = $this->outputDir . '/converted-financial.pdf';
        $converter->convert($xlsxPath, $pdfPath);

        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath));
    }

    // =========================================================================
    // PptxToPdfConverter Tests
    // =========================================================================

    public function testPptxToPdfConverterCreate(): void
    {
        $converter = PptxToPdfConverter::create();
        $this->assertInstanceOf(PptxToPdfConverter::class, $converter);
    }

    public function testPptxToPdfConverterFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $converter = new PptxToPdfConverter();
        $converter->toPdfDocument('/nonexistent/file.pptx');
    }

    public function testPptxToPdfConverterOptions(): void
    {
        $converter = PptxToPdfConverter::create()
            ->setPageSize('A4')
            ->setMargins(36, 36, 36, 36)
            ->setDefaultFont('Arial', 14)
            ->setSlides([1, 2])
            ->setHandoutMode(2)
            ->includeSlideNumbers(false);

        $this->assertInstanceOf(PptxToPdfConverter::class, $converter);
    }

    // =========================================================================
    // Integration Tests
    // =========================================================================

    public function testDocxRoundTrip(): void
    {
        // Create DOCX -> Convert to PDF -> Verify PDF exists
        $docxPath = $this->outputDir . '/roundtrip.docx';
        $pdfPath = $this->outputDir . '/roundtrip.pdf';

        $writer = new DocxWriter();
        $writer->addHeading('Round Trip Test', 1);
        $writer->addText('This document will be converted to PDF.');
        $writer->addText('It tests the complete conversion pipeline.');
        $writer->save($docxPath);

        $this->assertFileExists($docxPath);

        $converter = new DocxToPdfConverter();
        $converter->convert($docxPath, $pdfPath);

        $this->assertFileExists($pdfPath);

        // Verify PDF content
        $pdfContent = file_get_contents($pdfPath);
        $this->assertStringStartsWith('%PDF', $pdfContent);
        $this->assertStringContainsString('%%EOF', $pdfContent);
    }

    public function testXlsxRoundTrip(): void
    {
        // Create XLSX -> Convert to PDF -> Verify PDF exists
        $xlsxPath = $this->outputDir . '/roundtrip.xlsx';
        $pdfPath = $this->outputDir . '/roundtrip-xlsx.pdf';

        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Round Trip');
        $sheet->setRow(1, ['Round Trip Test'], 1);
        $sheet->setRow(2, ['This spreadsheet will be converted to PDF']);
        $writer->save($xlsxPath);

        $this->assertFileExists($xlsxPath);

        $converter = new XlsxToPdfConverter();
        $converter->convert($xlsxPath, $pdfPath);

        $this->assertFileExists($pdfPath);

        // Verify PDF content
        $pdfContent = file_get_contents($pdfPath);
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    public function testConverterChaining(): void
    {
        // Test fluent interface chaining
        $docxConverter = DocxToPdfConverter::create()
            ->setPageSize('A4')
            ->setMargins(50, 50, 50, 50)
            ->setDefaultFont('Helvetica', 12)
            ->setPages([1]);

        $this->assertInstanceOf(DocxToPdfConverter::class, $docxConverter);

        $xlsxConverter = XlsxToPdfConverter::create()
            ->setPageSize('letter')
            ->setLandscape(true)
            ->setMargins(36, 36, 36, 36)
            ->setDefaultFont('Arial', 10)
            ->setSheetPerPage(true)
            ->showGridlines(true)
            ->showHeaders(true)
            ->setSheets([1, 2]);

        $this->assertInstanceOf(XlsxToPdfConverter::class, $xlsxConverter);

        $pptxConverter = PptxToPdfConverter::create()
            ->setPageSize('letter')
            ->setMargins(36, 36, 36, 36)
            ->setDefaultFont('Arial', 12)
            ->setSlides([1, 2, 3])
            ->setHandoutMode(0)
            ->includeSlideNumbers(true);

        $this->assertInstanceOf(PptxToPdfConverter::class, $pptxConverter);
    }

    public function testLargeSpreadsheet(): void
    {
        // Create a larger spreadsheet to test pagination
        $xlsxPath = $this->outputDir . '/test-large.xlsx';
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Large Data');

        // Header
        $sheet->setRow(1, ['ID', 'Name', 'Value', 'Category', 'Status'], 1);

        // 100 rows of data
        for ($i = 2; $i <= 101; $i++) {
            $sheet->setRow($i, [
                $i - 1,
                "Item " . ($i - 1),
                rand(100, 10000),
                "Category " . (($i % 5) + 1),
                $i % 2 === 0 ? 'Active' : 'Inactive',
            ]);
        }

        $writer->save($xlsxPath);

        $converter = XlsxToPdfConverter::create()
            ->setPageSize('A4')
            ->showGridlines(true);

        $pdfPath = $this->outputDir . '/converted-large.pdf';
        $converter->convert($xlsxPath, $pdfPath);

        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(1000, filesize($pdfPath)); // Should be sizeable
    }

    public function testLongDocument(): void
    {
        // Create a long document to test pagination
        $docxPath = $this->outputDir . '/test-long.docx';
        $writer = new DocxWriter();

        $writer->addHeading('Long Document Test', 1);

        for ($i = 1; $i <= 20; $i++) {
            $writer->addHeading("Chapter $i", 2);
            $writer->addText("This is the content for chapter $i. It contains several sentences to make it longer. " .
                "The purpose is to test how the converter handles documents that span multiple pages. " .
                "Each chapter adds more content to ensure proper pagination.");
            $writer->addText("Additional paragraph for chapter $i with more text content.");
        }

        $writer->save($docxPath);

        $converter = new DocxToPdfConverter();
        $pdfPath = $this->outputDir . '/converted-long.pdf';
        $converter->convert($docxPath, $pdfPath);

        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(1000, filesize($pdfPath));
    }
}
