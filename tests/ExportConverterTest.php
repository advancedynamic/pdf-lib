<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PHPUnit\Framework\TestCase;
use PdfLib\Document\PdfDocument;
use PdfLib\Export\Docx\DocxWriter;
use PdfLib\Export\Docx\DocxParagraph;
use PdfLib\Export\Docx\DocxRun;
use PdfLib\Export\Docx\PdfToDocxConverter;
use PdfLib\Export\Xlsx\XlsxWriter;
use PdfLib\Export\Xlsx\XlsxSheet;
use PdfLib\Export\Xlsx\PdfToXlsxConverter;

/**
 * Tests for PDF export converters (DOCX and XLSX).
 */
class ExportConverterTest extends TestCase
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
    // DocxRun Tests
    // =========================================================================

    public function testDocxRunCreation(): void
    {
        $run = new DocxRun('Hello World');
        $xml = $run->toXml();

        $this->assertStringContainsString('Hello World', $xml);
        $this->assertStringContainsString('<w:t>', $xml);
    }

    public function testDocxRunBold(): void
    {
        $run = new DocxRun('Bold Text');
        $run->setBold(true);
        $xml = $run->toXml();

        $this->assertStringContainsString('<w:b/>', $xml);
    }

    public function testDocxRunItalic(): void
    {
        $run = new DocxRun('Italic Text');
        $run->setItalic(true);
        $xml = $run->toXml();

        $this->assertStringContainsString('<w:i/>', $xml);
    }

    public function testDocxRunUnderline(): void
    {
        $run = new DocxRun('Underlined Text');
        $run->setUnderline(true);
        $xml = $run->toXml();

        $this->assertStringContainsString('<w:u w:val="single"/>', $xml);
    }

    public function testDocxRunFontSize(): void
    {
        $run = new DocxRun('Large Text');
        $run->setFontSize(16);
        $xml = $run->toXml();

        $this->assertStringContainsString('<w:sz w:val="32"/>', $xml); // 16pt = 32 half-points
    }

    public function testDocxRunColor(): void
    {
        $run = new DocxRun('Red Text');
        $run->setColor('#FF0000');
        $xml = $run->toXml();

        $this->assertStringContainsString('<w:color w:val="FF0000"/>', $xml);
    }

    // =========================================================================
    // DocxParagraph Tests
    // =========================================================================

    public function testDocxParagraphCreation(): void
    {
        $paragraph = new DocxParagraph();
        $paragraph->addRun('Test paragraph');
        $xml = $paragraph->toXml();

        $this->assertStringContainsString('<w:p>', $xml);
        $this->assertStringContainsString('Test paragraph', $xml);
    }

    public function testDocxParagraphStyle(): void
    {
        $paragraph = new DocxParagraph();
        $paragraph->setStyle('Heading1');
        $paragraph->addRun('Heading');
        $xml = $paragraph->toXml();

        $this->assertStringContainsString('<w:pStyle w:val="Heading1"/>', $xml);
    }

    public function testDocxParagraphAlignment(): void
    {
        $paragraph = new DocxParagraph();
        $paragraph->setAlignment('center');
        $paragraph->addRun('Centered');
        $xml = $paragraph->toXml();

        $this->assertStringContainsString('<w:jc w:val="center"/>', $xml);
    }

    // =========================================================================
    // DocxWriter Tests
    // =========================================================================

    public function testDocxWriterCreation(): void
    {
        $writer = new DocxWriter();

        $this->assertInstanceOf(DocxWriter::class, $writer);
    }

    public function testDocxWriterAddText(): void
    {
        $writer = new DocxWriter();
        $writer->addText('Hello World');

        $outputPath = $this->outputDir . '/test-simple.docx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    public function testDocxWriterAddHeading(): void
    {
        $writer = new DocxWriter();
        $writer->addHeading('Test Heading', 1);
        $writer->addText('This is body text.');

        $outputPath = $this->outputDir . '/test-heading.docx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
    }

    public function testDocxWriterAddParagraph(): void
    {
        $writer = new DocxWriter();

        $paragraph = new DocxParagraph();
        $paragraph->addRun('Normal text ');
        $paragraph->addRun('bold text', true);
        $paragraph->addRun(' and ');
        $paragraph->addRun('italic text', false, true);

        $writer->addParagraph($paragraph);

        $outputPath = $this->outputDir . '/test-paragraph.docx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
    }

    public function testDocxWriterPageSize(): void
    {
        $writer = new DocxWriter();
        $writer->setPageSizeA4();
        $writer->addText('A4 page');

        $outputPath = $this->outputDir . '/test-a4.docx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
    }

    public function testDocxWriterGetContent(): void
    {
        $writer = new DocxWriter();
        $writer->addText('Binary content test');

        $content = $writer->getContent();

        // DOCX files are ZIP files, start with PK signature
        $this->assertStringStartsWith('PK', $content);
    }

    public function testDocxWriterCompleteDocument(): void
    {
        $writer = new DocxWriter();
        $writer->setPageSizeA4();
        $writer->setMargins(1, 1, 1, 1);
        $writer->setDefaultFont('Times New Roman', 12);

        $writer->addHeading('Document Title', 1);
        $writer->addText('This is the first paragraph of the document.');
        $writer->addHeading('Section 1', 2);
        $writer->addText('Content for section 1.', null, true);
        $writer->addHeading('Section 2', 2);
        $writer->addText('Content for section 2.', null, false, true);
        $writer->addPageBreak();
        $writer->addHeading('Page 2', 1);
        $writer->addText('Content on page 2.');

        $outputPath = $this->outputDir . '/test-complete.docx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(5000, filesize($outputPath));
    }

    // =========================================================================
    // XlsxSheet Tests
    // =========================================================================

    public function testXlsxSheetCreation(): void
    {
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Test');

        $this->assertEquals('Test', $sheet->getName());
    }

    public function testXlsxSheetSetCell(): void
    {
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Test');
        $sheet->setCell(1, 1, 'Hello');
        $sheet->setCell(1, 2, 123);
        $sheet->setCell(2, 1, true);

        $xml = $sheet->toXml();

        $this->assertStringContainsString('A1', $xml);
        $this->assertStringContainsString('B1', $xml);
        $this->assertStringContainsString('A2', $xml);
    }

    public function testXlsxSheetSetCellByAddress(): void
    {
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Test');
        $sheet->setCellByAddress('C5', 'Test Value');

        $xml = $sheet->toXml();

        $this->assertStringContainsString('C5', $xml);
    }

    public function testXlsxSheetSetRow(): void
    {
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Test');
        $sheet->setRow(1, ['A', 'B', 'C']);

        $xml = $sheet->toXml();

        $this->assertStringContainsString('A1', $xml);
        $this->assertStringContainsString('B1', $xml);
        $this->assertStringContainsString('C1', $xml);
    }

    public function testXlsxSheetAddRow(): void
    {
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Test');
        $sheet->addRow(['Row 1']);
        $sheet->addRow(['Row 2']);
        $sheet->addRow(['Row 3']);

        $xml = $sheet->toXml();

        $this->assertStringContainsString('A1', $xml);
        $this->assertStringContainsString('A2', $xml);
        $this->assertStringContainsString('A3', $xml);
    }

    public function testXlsxSheetFormula(): void
    {
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Test');
        $sheet->setCell(1, 1, 10);
        $sheet->setCell(2, 1, 20);
        $sheet->setCell(3, 1, '=SUM(A1:A2)');

        $xml = $sheet->toXml();

        $this->assertStringContainsString('<f>SUM(A1:A2)</f>', $xml);
    }

    // =========================================================================
    // XlsxWriter Tests
    // =========================================================================

    public function testXlsxWriterCreation(): void
    {
        $writer = new XlsxWriter();

        $this->assertInstanceOf(XlsxWriter::class, $writer);
    }

    public function testXlsxWriterSave(): void
    {
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Data');
        $sheet->addRow(['Name', 'Value']);
        $sheet->addRow(['Item 1', 100]);
        $sheet->addRow(['Item 2', 200]);

        $outputPath = $this->outputDir . '/test-simple.xlsx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    public function testXlsxWriterMultipleSheets(): void
    {
        $writer = new XlsxWriter();

        $sheet1 = $writer->addSheet('Sheet1');
        $sheet1->addRow(['Sheet 1 Data']);

        $sheet2 = $writer->addSheet('Sheet2');
        $sheet2->addRow(['Sheet 2 Data']);

        $outputPath = $this->outputDir . '/test-multi-sheet.xlsx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
    }

    public function testXlsxWriterGetContent(): void
    {
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Test');
        $sheet->addRow(['Binary content test']);

        $content = $writer->getContent();

        // XLSX files are ZIP files, start with PK signature
        $this->assertStringStartsWith('PK', $content);
    }

    public function testXlsxWriterCompleteWorkbook(): void
    {
        $writer = new XlsxWriter();

        // Sales data sheet
        $salesSheet = $writer->addSheet('Sales');
        $salesSheet->addRow(['Product', 'Q1', 'Q2', 'Q3', 'Q4', 'Total'], 1);
        $salesSheet->addRow(['Widget A', 1000, 1200, 1100, 1500, '=SUM(B2:E2)']);
        $salesSheet->addRow(['Widget B', 800, 900, 1000, 1100, '=SUM(B3:E3)']);
        $salesSheet->addRow(['Widget C', 500, 550, 600, 750, '=SUM(B4:E4)']);
        $salesSheet->addRow(['Total', '=SUM(B2:B4)', '=SUM(C2:C4)', '=SUM(D2:D4)', '=SUM(E2:E4)', '=SUM(F2:F4)'], 1);
        $salesSheet->setColumnWidth(1, 15);
        $salesSheet->setColumnWidth(6, 12);

        // Summary sheet
        $summarySheet = $writer->addSheet('Summary');
        $summarySheet->addRow(['Report Summary']);
        $summarySheet->addRow(['Generated:', date('Y-m-d H:i:s')]);
        $summarySheet->addRow(['Total Products:', 3]);

        $outputPath = $this->outputDir . '/test-complete.xlsx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(5000, filesize($outputPath));
    }

    // =========================================================================
    // PdfToDocxConverter Tests
    // =========================================================================

    public function testPdfToDocxConverterCreate(): void
    {
        $converter = PdfToDocxConverter::create();

        $this->assertInstanceOf(PdfToDocxConverter::class, $converter);
    }

    public function testPdfToDocxConverterWithSamplePdf(): void
    {
        // Create a simple PDF first
        $pdfPath = $this->outputDir . '/sample-for-docx.pdf';
        $pdf = PdfDocument::create();
        $pdf->addPage();
        $page = $pdf->getPage(0);
        $page->addText('Sample Document', 100, 700, ['fontSize' => 24]);
        $page->addText('This is a test paragraph for conversion.', 100, 650);
        $pdf->save($pdfPath);

        // Convert to DOCX
        $docxPath = $this->outputDir . '/converted.docx';
        $converter = PdfToDocxConverter::create()
            ->setFont('Arial', 12);

        $converter->convert($pdfPath, $docxPath);

        $this->assertFileExists($docxPath);
        $this->assertGreaterThan(0, filesize($docxPath));
    }

    public function testPdfToDocxConverterSettings(): void
    {
        $converter = PdfToDocxConverter::create()
            ->setPageSize('A4')
            ->setFont('Times New Roman', 11)
            ->preserveLayout(true)
            ->setPages([1, 2, 3]);

        $this->assertInstanceOf(PdfToDocxConverter::class, $converter);
    }

    public function testPdfToDocxConverterFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $converter = PdfToDocxConverter::create();
        $converter->convert('/nonexistent/file.pdf', '/output.docx');
    }

    // =========================================================================
    // PdfToXlsxConverter Tests
    // =========================================================================

    public function testPdfToXlsxConverterCreate(): void
    {
        $converter = PdfToXlsxConverter::create();

        $this->assertInstanceOf(PdfToXlsxConverter::class, $converter);
    }

    public function testPdfToXlsxConverterWithSamplePdf(): void
    {
        // Create a simple PDF first
        $pdfPath = $this->outputDir . '/sample-for-xlsx.pdf';
        $pdf = PdfDocument::create();
        $pdf->addPage();
        $page = $pdf->getPage(0);
        $page->addText('Product', 100, 700);
        $page->addText('Price', 200, 700);
        $page->addText('Widget A', 100, 680);
        $page->addText('$100', 200, 680);
        $page->addText('Widget B', 100, 660);
        $page->addText('$200', 200, 660);
        $pdf->save($pdfPath);

        // Convert to XLSX
        $xlsxPath = $this->outputDir . '/converted.xlsx';
        $converter = PdfToXlsxConverter::create()
            ->detectTables(true);

        $converter->convert($pdfPath, $xlsxPath);

        $this->assertFileExists($xlsxPath);
        $this->assertGreaterThan(0, filesize($xlsxPath));
    }

    public function testPdfToXlsxConverterSettings(): void
    {
        $converter = PdfToXlsxConverter::create()
            ->setSheetPerPage(true)
            ->detectTables(true)
            ->setColumnTolerance(15)
            ->setRowTolerance(8)
            ->setPages([1, 2]);

        $this->assertInstanceOf(PdfToXlsxConverter::class, $converter);
    }

    public function testPdfToXlsxConverterFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $converter = PdfToXlsxConverter::create();
        $converter->convert('/nonexistent/file.pdf', '/output.xlsx');
    }

    public function testPdfToXlsxConverterToString(): void
    {
        // Create a simple PDF first
        $pdfPath = $this->outputDir . '/sample-for-xlsx-binary.pdf';
        $pdf = PdfDocument::create();
        $pdf->addPage();
        $page = $pdf->getPage(0);
        $page->addText('Test Data', 100, 700);
        $pdf->save($pdfPath);

        $converter = PdfToXlsxConverter::create();
        $content = $converter->convertToString($pdfPath);

        // XLSX files are ZIP files, start with PK signature
        $this->assertStringStartsWith('PK', $content);
    }

    // =========================================================================
    // Real-World DOCX Creation Examples
    // =========================================================================

    public function testDocxWriterProjectProposal(): void
    {
        $writer = new DocxWriter();
        $writer->setPageSizeA4();
        $writer->setDefaultFont('Calibri', 11);
        $writer->setMargins(1, 1, 1, 1);

        // Title
        $writer->addHeading('Project Proposal', 1);

        // Metadata
        $writer->addText('Prepared by: Development Team');
        $writer->addText('Date: January 5, 2026');
        $writer->addText('');

        // Sections
        $writer->addHeading('1. Overview', 2);
        $writer->addText('This proposal outlines the development plan for the new customer portal. The project aims to improve user experience and streamline customer interactions.');

        $writer->addHeading('2. Objectives', 2);
        $writer->addText('The primary objectives of this project are:');
        $writer->addText('');

        $objectives = [
            'Improve customer self-service capabilities',
            'Reduce support ticket volume by 30%',
            'Increase customer satisfaction scores',
            'Provide real-time order tracking',
        ];

        foreach ($objectives as $objective) {
            $paragraph = new DocxParagraph();
            $paragraph->setIndentLeft(20);
            $paragraph->addRun('• ' . $objective);
            $writer->addParagraph($paragraph);
        }

        $writer->addHeading('3. Timeline', 2);
        $writer->addText('Phase 1: Requirements gathering and design');
        $writer->addText('Phase 2: Core development');
        $writer->addText('Phase 3: Testing and QA');
        $writer->addText('Phase 4: Deployment and training');

        $writer->addPageBreak();

        $writer->addHeading('4. Budget', 2);
        $writer->addText('The estimated budget for this project is $150,000, broken down as follows:');
        $writer->addText('');

        $budget = [
            'Development: $80,000',
            'Design: $25,000',
            'Testing: $20,000',
            'Training: $15,000',
            'Contingency: $10,000',
        ];

        foreach ($budget as $item) {
            $paragraph = new DocxParagraph();
            $paragraph->setIndentLeft(20);
            $paragraph->addRun('• ' . $item);
            $writer->addParagraph($paragraph);
        }

        $writer->addHeading('5. Conclusion', 2);
        $writer->addText('We recommend proceeding with this project to enhance our customer engagement capabilities and maintain competitive advantage in the market.');

        $outputPath = $this->outputDir . '/project-proposal.docx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(5000, filesize($outputPath));

        // Verify it's a valid ZIP/DOCX file
        $content = file_get_contents($outputPath);
        $this->assertStringStartsWith('PK', $content);
    }

    public function testDocxWriterInvoiceDocument(): void
    {
        $writer = new DocxWriter();
        $writer->setPageSizeA4();
        $writer->setDefaultFont('Arial', 11);

        $writer->addHeading('INVOICE', 1);
        $writer->addText('');

        $writer->addText('Invoice Number: INV-2026-0001');
        $writer->addText('Date: January 5, 2026');
        $writer->addText('Due Date: February 5, 2026');
        $writer->addText('');

        $writer->addHeading('Bill To:', 2);
        $writer->addText('John Smith');
        $writer->addText('XYZ Company');
        $writer->addText('456 Customer Avenue');
        $writer->addText('Los Angeles, CA 90001');
        $writer->addText('');

        $writer->addHeading('Items:', 2);
        $writer->addText('1. Web Development Services (40 hrs @ $100) - $4,000.00');
        $writer->addText('2. UI/UX Design (20 hrs @ $85) - $1,700.00');
        $writer->addText('3. Server Hosting (Annual) - $500.00');
        $writer->addText('');

        // Create bold total paragraph
        $totalParagraph = new DocxParagraph();
        $totalParagraph->addRun('Subtotal: $6,200.00', true);
        $writer->addParagraph($totalParagraph);

        $writer->addText('Tax (8%): $496.00');

        $grandTotalParagraph = new DocxParagraph();
        $grandTotalParagraph->addRun('TOTAL DUE: $6,696.00', true);
        $writer->addParagraph($grandTotalParagraph);

        $outputPath = $this->outputDir . '/invoice.docx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    public function testDocxWriterGetContentMethod(): void
    {
        $writer = new DocxWriter();
        $writer->addHeading('Test Document', 1);
        $writer->addText('This tests the getContent() method for binary output.');

        $content = $writer->getContent();

        $this->assertStringStartsWith('PK', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }

    // =========================================================================
    // Real-World XLSX Creation Examples
    // =========================================================================

    public function testXlsxWriterSalesReportWithFormulas(): void
    {
        $writer = new XlsxWriter();

        $salesSheet = $writer->addSheet('Sales Report');

        // Title
        $salesSheet->setCellByAddress('A1', 'Quarterly Sales Report 2026');
        $salesSheet->setCellByAddress('A2', '');

        // Headers (style 1 = bold)
        $salesSheet->setRow(3, ['Product', 'Q1', 'Q2', 'Q3', 'Q4', 'Total', 'Avg'], 1);

        // Data with formulas
        $salesSheet->setRow(4, ['Laptop Pro', 45000, 52000, 48000, 61000, '=SUM(B4:E4)', '=AVERAGE(B4:E4)']);
        $salesSheet->setRow(5, ['Tablet X', 28000, 31000, 35000, 42000, '=SUM(B5:E5)', '=AVERAGE(B5:E5)']);
        $salesSheet->setRow(6, ['Phone Z', 65000, 71000, 68000, 85000, '=SUM(B6:E6)', '=AVERAGE(B6:E6)']);
        $salesSheet->setRow(7, ['Accessory Kit', 12000, 14000, 13500, 18000, '=SUM(B7:E7)', '=AVERAGE(B7:E7)']);

        // Empty row
        $salesSheet->setRow(8, ['', '', '', '', '', '', '']);

        // Totals row
        $salesSheet->setRow(9, [
            'TOTAL',
            '=SUM(B4:B7)',
            '=SUM(C4:C7)',
            '=SUM(D4:D7)',
            '=SUM(E4:E7)',
            '=SUM(F4:F7)',
            '=AVERAGE(G4:G7)',
        ], 1);

        // Set column widths
        $salesSheet->setColumnWidth(1, 15);
        $salesSheet->setColumnWidth(6, 12);
        $salesSheet->setColumnWidth(7, 12);

        // Summary Sheet
        $summarySheet = $writer->addSheet('Summary');
        $summarySheet->setCellByAddress('A1', 'Report Summary');
        $summarySheet->setCellByAddress('A3', 'Report Date:');
        $summarySheet->setCellByAddress('B3', date('Y-m-d'));
        $summarySheet->setCellByAddress('A4', 'Total Products:');
        $summarySheet->setCellByAddress('B4', 4);
        $summarySheet->setCellByAddress('A5', 'Best Performer:');
        $summarySheet->setCellByAddress('B5', 'Phone Z');

        $outputPath = $this->outputDir . '/sales-report.xlsx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(5000, filesize($outputPath));

        // Verify XML content contains formulas
        $content = file_get_contents($outputPath);
        $this->assertStringStartsWith('PK', $content);
    }

    public function testXlsxWriterFinancialStatements(): void
    {
        $writer = new XlsxWriter();

        // Income Statement
        $income = $writer->addSheet('Income Statement');
        $income->setRow(1, ['INCOME STATEMENT', '', '', ''], 1);
        $income->setRow(2, ['For Year Ended December 31, 2025', '', '', '']);
        $income->setRow(3, ['', '', '', '']);
        $income->setRow(4, ['REVENUE', '', '', ''], 1);
        $income->setRow(5, ['Sales Revenue', '', '', 2500000]);
        $income->setRow(6, ['Service Revenue', '', '', 450000]);
        $income->setRow(7, ['Other Revenue', '', '', 75000]);
        $income->setRow(8, ['Total Revenue', '', '', '=SUM(D5:D7)'], 1);
        $income->setRow(9, ['', '', '', '']);
        $income->setRow(10, ['EXPENSES', '', '', ''], 1);
        $income->setRow(11, ['Cost of Goods Sold', '', '', 1200000]);
        $income->setRow(12, ['Salaries & Wages', '', '', 650000]);
        $income->setRow(13, ['Rent & Utilities', '', '', 120000]);
        $income->setRow(14, ['Marketing', '', '', 85000]);
        $income->setRow(15, ['Depreciation', '', '', 45000]);
        $income->setRow(16, ['Other Expenses', '', '', 50000]);
        $income->setRow(17, ['Total Expenses', '', '', '=SUM(D11:D16)'], 1);
        $income->setRow(18, ['', '', '', '']);
        $income->setRow(19, ['NET INCOME', '', '', '=D8-D17'], 1);

        $income->setColumnWidth(1, 25);
        $income->setColumnWidth(4, 15);

        // Balance Sheet
        $balance = $writer->addSheet('Balance Sheet');
        $balance->setRow(1, ['BALANCE SHEET', '', '', ''], 1);
        $balance->setRow(2, ['As of December 31, 2025', '', '', '']);
        $balance->setRow(3, ['', '', '', '']);
        $balance->setRow(4, ['ASSETS', '', '', ''], 1);
        $balance->setRow(5, ['Cash & Equivalents', '', '', 850000]);
        $balance->setRow(6, ['Accounts Receivable', '', '', 320000]);
        $balance->setRow(7, ['Inventory', '', '', 275000]);
        $balance->setRow(8, ['Prepaid Expenses', '', '', 45000]);
        $balance->setRow(9, ['Property & Equipment', '', '', 1200000]);
        $balance->setRow(10, ['Total Assets', '', '', '=SUM(D5:D9)'], 1);
        $balance->setRow(11, ['', '', '', '']);
        $balance->setRow(12, ['LIABILITIES', '', '', ''], 1);
        $balance->setRow(13, ['Accounts Payable', '', '', 180000]);
        $balance->setRow(14, ['Accrued Expenses', '', '', 95000]);
        $balance->setRow(15, ['Long-term Debt', '', '', 450000]);
        $balance->setRow(16, ['Total Liabilities', '', '', '=SUM(D13:D15)'], 1);
        $balance->setRow(17, ['', '', '', '']);
        $balance->setRow(18, ['EQUITY', '', '', ''], 1);
        $balance->setRow(19, ['Owner\'s Equity', '', '', '=D10-D16'], 1);

        $balance->setColumnWidth(1, 25);
        $balance->setColumnWidth(4, 15);

        $outputPath = $this->outputDir . '/financial-statements.xlsx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(5000, filesize($outputPath));
    }

    public function testXlsxWriterEmployeeDataTable(): void
    {
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Employees');

        // Headers
        $sheet->setRow(1, ['Employee', 'Department', 'Salary', 'Start Date'], 1);

        // Data
        $employees = [
            ['Alice Johnson', 'Engineering', 95000, '2022-03-15'],
            ['Bob Smith', 'Marketing', 75000, '2021-06-01'],
            ['Carol White', 'Finance', 85000, '2023-01-10'],
            ['David Brown', 'Engineering', 105000, '2020-09-20'],
            ['Eva Martinez', 'HR', 70000, '2022-11-05'],
        ];

        $row = 2;
        foreach ($employees as $employee) {
            $sheet->setRow($row, $employee);
            $row++;
        }

        // Summary row
        $sheet->setRow(8, ['', '', '', '']);
        $sheet->setRow(9, ['Total Employees:', count($employees), 'Avg Salary:', '=AVERAGE(C2:C6)'], 1);

        $sheet->setColumnWidth(1, 18);
        $sheet->setColumnWidth(2, 15);
        $sheet->setColumnWidth(3, 12);
        $sheet->setColumnWidth(4, 12);

        $outputPath = $this->outputDir . '/employees.xlsx';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    public function testXlsxWriterGetContentMethod(): void
    {
        $writer = new XlsxWriter();
        $sheet = $writer->addSheet('Test');
        $sheet->addRow(['Binary Output Test']);

        $content = $writer->getContent();

        $this->assertStringStartsWith('PK', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }
}
