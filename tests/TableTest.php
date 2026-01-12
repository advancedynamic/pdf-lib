<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PHPUnit\Framework\TestCase;
use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;
use PdfLib\Content\Table\Table;
use PdfLib\Content\Table\TableCell;

/**
 * Tests for Table classes.
 */
final class TableTest extends TestCase
{
    private string $targetDir;

    protected function setUp(): void
    {
        $this->targetDir = __DIR__ . '/target';
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0755, true);
        }
    }

    // ==================== TableCell Tests ====================

    public function testTableCellCreation(): void
    {
        $cell = new TableCell('Test Content');

        $this->assertEquals('Test Content', $cell->getContent());
        $this->assertEquals(1, $cell->getColspan());
        $this->assertEquals(1, $cell->getRowspan());
        $this->assertFalse($cell->isHeader());
    }

    public function testTableCellStaticCreate(): void
    {
        $cell = TableCell::create('Static Create');

        $this->assertEquals('Static Create', $cell->getContent());
    }

    public function testTableCellHeader(): void
    {
        $cell = TableCell::header('Header Cell');

        $this->assertEquals('Header Cell', $cell->getContent());
        $this->assertTrue($cell->isHeader());
    }

    public function testTableCellColspan(): void
    {
        $cell = new TableCell('Spanning Cell');
        $cell->setColspan(3);

        $this->assertEquals(3, $cell->getColspan());
    }

    public function testTableCellColspanMinimum(): void
    {
        $cell = new TableCell('Test');
        $cell->setColspan(0); // Should be normalized to 1

        $this->assertEquals(1, $cell->getColspan());
    }

    public function testTableCellRowspan(): void
    {
        $cell = new TableCell('Spanning Cell');
        $cell->setRowspan(2);

        $this->assertEquals(2, $cell->getRowspan());
    }

    public function testTableCellHorizontalAlignment(): void
    {
        $cell = new TableCell('Aligned');

        $cell->alignLeft();
        $this->assertEquals(TableCell::ALIGN_LEFT, $cell->getHAlign());

        $cell->alignCenter();
        $this->assertEquals(TableCell::ALIGN_CENTER, $cell->getHAlign());

        $cell->alignRight();
        $this->assertEquals(TableCell::ALIGN_RIGHT, $cell->getHAlign());

        $cell->alignJustify();
        $this->assertEquals(TableCell::ALIGN_JUSTIFY, $cell->getHAlign());
    }

    public function testTableCellVerticalAlignment(): void
    {
        $cell = new TableCell('Aligned');

        $cell->valignTop();
        $this->assertEquals(TableCell::VALIGN_TOP, $cell->getVAlign());

        $cell->valignMiddle();
        $this->assertEquals(TableCell::VALIGN_MIDDLE, $cell->getVAlign());

        $cell->valignBottom();
        $this->assertEquals(TableCell::VALIGN_BOTTOM, $cell->getVAlign());
    }

    public function testTableCellBackgroundColor(): void
    {
        $cell = new TableCell('Colored');
        $cell->setBackgroundColor('#FF0000');

        $this->assertEquals('#FF0000', $cell->getBackgroundColor());
    }

    public function testTableCellTextColor(): void
    {
        $cell = new TableCell('Colored');
        $cell->setTextColor('#0000FF');

        $this->assertEquals('#0000FF', $cell->getTextColor());
    }

    public function testTableCellFontSize(): void
    {
        $cell = new TableCell('Sized');
        $cell->setFontSize(14.0);

        $this->assertEquals(14.0, $cell->getFontSize());
    }

    public function testTableCellBorderWidth(): void
    {
        $cell = new TableCell('Bordered');
        $cell->setBorderWidth(1.5);

        $this->assertEquals(1.5, $cell->getBorderWidth());
    }

    public function testTableCellBorderColor(): void
    {
        $cell = new TableCell('Bordered');
        $cell->setBorderColor('#00FF00');

        $this->assertEquals('#00FF00', $cell->getBorderColor());
    }

    public function testTableCellIndividualBorders(): void
    {
        $cell = new TableCell('Borders');

        $this->assertTrue($cell->hasBorderTop());
        $this->assertTrue($cell->hasBorderRight());
        $this->assertTrue($cell->hasBorderBottom());
        $this->assertTrue($cell->hasBorderLeft());

        $cell->setBorderTop(false);
        $cell->setBorderRight(false);

        $this->assertFalse($cell->hasBorderTop());
        $this->assertFalse($cell->hasBorderRight());
        $this->assertTrue($cell->hasBorderBottom());
        $this->assertTrue($cell->hasBorderLeft());
    }

    public function testTableCellRemoveBorders(): void
    {
        $cell = new TableCell('No Borders');
        $cell->removeBorders();

        $this->assertFalse($cell->hasBorderTop());
        $this->assertFalse($cell->hasBorderRight());
        $this->assertFalse($cell->hasBorderBottom());
        $this->assertFalse($cell->hasBorderLeft());
    }

    public function testTableCellPadding(): void
    {
        $cell = new TableCell('Padded');
        $cell->setPadding(5.0);

        $this->assertEquals(5.0, $cell->getPaddingTop());
        $this->assertEquals(5.0, $cell->getPaddingRight());
        $this->assertEquals(5.0, $cell->getPaddingBottom());
        $this->assertEquals(5.0, $cell->getPaddingLeft());
    }

    public function testTableCellIndividualPadding(): void
    {
        $cell = new TableCell('Padded');
        $cell->setPaddingTop(10.0);
        $cell->setPaddingRight(8.0);
        $cell->setPaddingBottom(6.0);
        $cell->setPaddingLeft(4.0);

        $this->assertEquals(10.0, $cell->getPaddingTop());
        $this->assertEquals(8.0, $cell->getPaddingRight());
        $this->assertEquals(6.0, $cell->getPaddingBottom());
        $this->assertEquals(4.0, $cell->getPaddingLeft());
    }

    public function testTableCellHorizontalPadding(): void
    {
        $cell = new TableCell('Test');
        $cell->setPaddingLeft(5.0);
        $cell->setPaddingRight(3.0);

        $this->assertEquals(8.0, $cell->getHorizontalPadding());
    }

    public function testTableCellVerticalPadding(): void
    {
        $cell = new TableCell('Test');
        $cell->setPaddingTop(4.0);
        $cell->setPaddingBottom(6.0);

        $this->assertEquals(10.0, $cell->getVerticalPadding());
    }

    public function testTableCellMinimumHeight(): void
    {
        $cell = new TableCell("Line 1\nLine 2\nLine 3");

        $minHeight = $cell->getMinimumHeight(12.0);

        // 3 lines * 12 * 1.2 + padding
        $this->assertGreaterThan(40, $minHeight);
    }

    public function testTableCellStyle(): void
    {
        $cell = new TableCell('Styled');

        $style = [
            'backgroundColor' => '#EEEEEE',
            'textColor' => '#333333',
            'fontSize' => 11.0,
        ];

        $cell->setStyle($style);

        $this->assertEquals($style, $cell->getStyle());
    }

    public function testTableCellMergeStyle(): void
    {
        $cell = new TableCell('Styled');
        $cell->setStyle(['backgroundColor' => '#EEEEEE']);
        $cell->mergeStyle(['textColor' => '#333333']);

        $style = $cell->getStyle();
        $this->assertEquals('#EEEEEE', $style['backgroundColor']);
        $this->assertEquals('#333333', $style['textColor']);
    }

    public function testTableCellDimensions(): void
    {
        $cell = new TableCell('Test');

        $cell->setX(100);
        $cell->setY(200);
        $cell->setWidth(150);
        $cell->setHeight(30);

        $this->assertEquals(100, $cell->getX());
        $this->assertEquals(200, $cell->getY());
        $this->assertEquals(150, $cell->getWidth());
        $this->assertEquals(30, $cell->getHeight());
    }

    public function testTableCellPosition(): void
    {
        $cell = new TableCell('Test');

        $cell->setRowIndex(2);
        $cell->setColIndex(3);

        $this->assertEquals(2, $cell->getRowIndex());
        $this->assertEquals(3, $cell->getColIndex());
    }

    // ==================== Table Tests ====================

    public function testTableCreation(): void
    {
        $table = new Table();

        $this->assertEquals(0, $table->getRowCount());
        $this->assertEquals(0, $table->getColumnCount());
    }

    public function testTableStaticCreate(): void
    {
        $table = Table::create();

        $this->assertInstanceOf(Table::class, $table);
    }

    public function testTablePosition(): void
    {
        $table = new Table();
        $table->setPosition(100, 700);

        $this->assertEquals(100, $table->getX());
        $this->assertEquals(700, $table->getY());
    }

    public function testTableWidth(): void
    {
        $table = new Table();
        $table->setWidth(500);

        $this->assertEquals(500, $table->getWidth());
    }

    public function testTableAddRow(): void
    {
        $table = new Table();
        $table->addRow(['Cell 1', 'Cell 2', 'Cell 3']);

        $this->assertEquals(1, $table->getRowCount());
        $this->assertEquals(3, $table->getColumnCount());
    }

    public function testTableAddMultipleRows(): void
    {
        $table = new Table();
        $table->addRow(['A', 'B', 'C']);
        $table->addRow(['D', 'E', 'F']);
        $table->addRow(['G', 'H', 'I']);

        $this->assertEquals(3, $table->getRowCount());
        $this->assertEquals(3, $table->getColumnCount());
    }

    public function testTableAddHeaderRow(): void
    {
        $table = new Table();
        $table->addHeaderRow(['Name', 'Age', 'City']);
        $table->addRow(['John', '30', 'NYC']);

        $this->assertEquals(2, $table->getRowCount());

        $headerRow = $table->getRow(0);
        $this->assertNotNull($headerRow);
        $this->assertTrue($headerRow[0]->isHeader());
    }

    public function testTableGetCell(): void
    {
        $table = new Table();
        $table->addRow(['A', 'B', 'C']);
        $table->addRow(['D', 'E', 'F']);

        $cell = $table->getCell(1, 1);
        $this->assertNotNull($cell);
        $this->assertEquals('E', $cell->getContent());
    }

    public function testTableWithTableCellObjects(): void
    {
        $table = new Table();

        $cell1 = TableCell::create('Custom Cell')
            ->setBackgroundColor('#FFEEEE')
            ->alignCenter();

        $cell2 = TableCell::create('Another Cell')
            ->setColspan(2);

        $table->addRow([$cell1, $cell2]);

        $this->assertEquals(1, $table->getRowCount());
        $this->assertEquals(3, $table->getColumnCount()); // 1 + colspan 2
    }

    public function testTableStyleMethods(): void
    {
        $table = new Table();

        $table->setCellPadding(5.0)
              ->setBorderWidth(1.0)
              ->setBorderColor('#000000')
              ->setFontSize(12.0)
              ->setFontFamily('Arial');

        $this->expectNotToPerformAssertions();
    }

    public function testTableHeaderStyling(): void
    {
        $table = new Table();

        $table->setHeaderBackgroundColor('#CCCCCC')
              ->setHeaderTextColor('#000000')
              ->setHeaderBold(true);

        $this->expectNotToPerformAssertions();
    }

    public function testTableColumnWidths(): void
    {
        $table = new Table();
        $table->setColumnWidths([100, 150, 200]);

        $this->expectNotToPerformAssertions();
    }

    public function testTableAutoSize(): void
    {
        $table = new Table();
        $table->setAutoSize(true);

        $this->expectNotToPerformAssertions();
    }

    public function testTableCalculation(): void
    {
        $table = new Table();
        $table->setWidth(500);
        $table->setPosition(50, 700);
        $table->addHeaderRow(['Column 1', 'Column 2', 'Column 3']);
        $table->addRow(['Data A', 'Data B', 'Data C']);

        $table->calculate();

        // After calculation, cells should have positions set
        $cell = $table->getCell(0, 0);
        $this->assertGreaterThan(0, $cell->getWidth());
        $this->assertGreaterThan(0, $cell->getHeight());
    }

    public function testTableTotalHeight(): void
    {
        $table = new Table();
        $table->setWidth(500);
        $table->addRow(['Row 1']);
        $table->addRow(['Row 2']);
        $table->addRow(['Row 3']);

        $height = $table->getTotalHeight();

        $this->assertGreaterThan(0, $height);
    }

    public function testTableRenderToPage(): void
    {
        $table = new Table();
        $table->setWidth(400);
        $table->setPosition(100, 700);
        $table->addHeaderRow(['Name', 'Value']);
        $table->addRow(['Item 1', '100']);
        $table->addRow(['Item 2', '200']);

        $page = new Page(PageSize::a4());
        $table->render($page);

        $this->expectNotToPerformAssertions();
    }

    // ==================== Integration Tests with File Output ====================

    public function testCreateSimpleTablePdfOutput(): void
    {
        $document = PdfDocument::create();
        $page = new Page(PageSize::a4());

        $page->addText('Simple Table Example', 50, 800, ['fontSize' => 18]);

        $table = new Table();
        $table->setWidth(500);
        $table->setPosition(50, 750);
        $table->addHeaderRow(['Name', 'Age', 'City', 'Country']);
        $table->addRow(['John Doe', '30', 'New York', 'USA']);
        $table->addRow(['Jane Smith', '25', 'London', 'UK']);
        $table->addRow(['Bob Wilson', '35', 'Sydney', 'Australia']);
        $table->addRow(['Alice Brown', '28', 'Toronto', 'Canada']);

        $table->render($page);
        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/simple_table.pdf';
        $document->save($outputPath);

        $this->assertFileExists($outputPath);
        echo "\nSimple table PDF created: {$outputPath}\n";
    }

    public function testCreateStyledTablePdfOutput(): void
    {
        $document = PdfDocument::create();
        $page = new Page(PageSize::a4());

        $page->addText('Styled Table Example', 50, 800, ['fontSize' => 18]);

        $table = new Table();
        $table->setWidth(500);
        $table->setPosition(50, 750);
        $table->setCellPadding(5.0);
        $table->setBorderWidth(0.5);
        $table->setBorderColor('#333333');
        $table->setFontSize(10.0);
        $table->setHeaderBackgroundColor('#4472C4');
        $table->setHeaderTextColor('#FFFFFF');

        $table->addHeaderRow(['Product', 'Quantity', 'Unit Price', 'Total']);
        $table->addRow(['Widget A', '10', '$25.00', '$250.00']);
        $table->addRow(['Widget B', '5', '$40.00', '$200.00']);
        $table->addRow(['Widget C', '20', '$15.00', '$300.00']);

        // Add a subtotal row with different styling
        $subtotalCell = TableCell::create('Subtotal')
            ->setColspan(3)
            ->alignRight()
            ->setBackgroundColor('#E0E0E0');
        $totalCell = TableCell::create('$750.00')
            ->setBackgroundColor('#E0E0E0');
        $table->addRow([$subtotalCell, $totalCell]);

        $table->render($page);
        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/styled_table.pdf';
        $document->save($outputPath);

        $this->assertFileExists($outputPath);
        echo "\nStyled table PDF created: {$outputPath}\n";
    }

    public function testCreateColspanTablePdfOutput(): void
    {
        $document = PdfDocument::create();
        $page = new Page(PageSize::a4());

        $page->addText('Table with Colspan', 50, 800, ['fontSize' => 18]);

        $table = new Table();
        $table->setWidth(500);
        $table->setPosition(50, 750);
        $table->setCellPadding(4.0);

        // Header spanning all columns
        $headerCell = TableCell::create('Sales Report 2024')
            ->setColspan(4)
            ->alignCenter()
            ->setBackgroundColor('#2E75B6')
            ->setTextColor('#FFFFFF');
        $table->addRow([$headerCell]);

        $table->addHeaderRow(['Region', 'Q1', 'Q2', 'Total']);
        $table->addRow(['North', '$10,000', '$12,000', '$22,000']);
        $table->addRow(['South', '$8,000', '$9,500', '$17,500']);
        $table->addRow(['East', '$15,000', '$14,000', '$29,000']);
        $table->addRow(['West', '$12,000', '$13,500', '$25,500']);

        // Footer spanning columns
        $footerLabel = TableCell::create('Grand Total')
            ->setColspan(3)
            ->alignRight()
            ->setBackgroundColor('#D0D0D0');
        $footerValue = TableCell::create('$94,000')
            ->setBackgroundColor('#D0D0D0');
        $table->addRow([$footerLabel, $footerValue]);

        $table->render($page);
        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/colspan_table.pdf';
        $document->save($outputPath);

        $this->assertFileExists($outputPath);
        echo "\nColspan table PDF created: {$outputPath}\n";
    }

    public function testCreateAlignedTablePdfOutput(): void
    {
        $document = PdfDocument::create();
        $page = new Page(PageSize::a4());

        $page->addText('Table with Alignment', 50, 800, ['fontSize' => 18]);

        $table = new Table();
        $table->setWidth(400);
        $table->setPosition(100, 750);

        $table->addHeaderRow(['Description', 'Amount']);

        // Left aligned text, right aligned numbers
        $desc1 = TableCell::create('Item Description 1')->alignLeft();
        $amt1 = TableCell::create('$1,234.56')->alignRight();
        $table->addRow([$desc1, $amt1]);

        $desc2 = TableCell::create('Item Description 2')->alignLeft();
        $amt2 = TableCell::create('$789.00')->alignRight();
        $table->addRow([$desc2, $amt2]);

        $desc3 = TableCell::create('Item Description 3')->alignLeft();
        $amt3 = TableCell::create('$2,456.78')->alignRight();
        $table->addRow([$desc3, $amt3]);

        // Centered total
        $totalLabel = TableCell::create('Total')->alignCenter();
        $totalValue = TableCell::create('$4,480.34')->alignRight()->setBackgroundColor('#FFFFCC');
        $table->addRow([$totalLabel, $totalValue]);

        $table->render($page);
        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/aligned_table.pdf';
        $document->save($outputPath);

        $this->assertFileExists($outputPath);
        echo "\nAligned table PDF created: {$outputPath}\n";
    }

    public function testCreateMultipleTablesPdfOutput(): void
    {
        $document = PdfDocument::create();
        $page = new Page(PageSize::a4());

        $page->addText('Multiple Tables on One Page', 50, 800, ['fontSize' => 18]);

        // First table
        $table1 = new Table();
        $table1->setWidth(220);
        $table1->setPosition(50, 750);
        $table1->setHeaderBackgroundColor('#70AD47');
        $table1->setHeaderTextColor('#FFFFFF');
        $table1->addHeaderRow(['Product', 'Stock']);
        $table1->addRow(['Apples', '150']);
        $table1->addRow(['Oranges', '200']);
        $table1->addRow(['Bananas', '175']);
        $table1->render($page);

        // Second table (positioned to the right)
        $table2 = new Table();
        $table2->setWidth(220);
        $table2->setPosition(300, 750);
        $table2->setHeaderBackgroundColor('#ED7D31');
        $table2->setHeaderTextColor('#FFFFFF');
        $table2->addHeaderRow(['Employee', 'Dept']);
        $table2->addRow(['John', 'Sales']);
        $table2->addRow(['Jane', 'Marketing']);
        $table2->addRow(['Bob', 'IT']);
        $table2->render($page);

        // Third table (below the first two)
        $table3 = new Table();
        $table3->setWidth(470);
        $table3->setPosition(50, 550);
        $table3->setHeaderBackgroundColor('#5B9BD5');
        $table3->setHeaderTextColor('#FFFFFF');
        $table3->addHeaderRow(['Month', 'Revenue', 'Expenses', 'Profit']);
        $table3->addRow(['January', '$50,000', '$35,000', '$15,000']);
        $table3->addRow(['February', '$55,000', '$38,000', '$17,000']);
        $table3->addRow(['March', '$60,000', '$40,000', '$20,000']);
        $table3->render($page);

        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/multiple_tables.pdf';
        $document->save($outputPath);

        $this->assertFileExists($outputPath);
        echo "\nMultiple tables PDF created: {$outputPath}\n";
    }
}
