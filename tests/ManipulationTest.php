<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PHPUnit\Framework\TestCase;
use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;
use PdfLib\Manipulation\Merger;
use PdfLib\Manipulation\Splitter;
use PdfLib\Manipulation\Stamper;
use PdfLib\Manipulation\Rotator;
use PdfLib\Manipulation\Cropper;
use PdfLib\Manipulation\Optimizer;

/**
 * Tests for PDF manipulation classes.
 */
final class ManipulationTest extends TestCase
{
    private string $targetDir;

    protected function setUp(): void
    {
        $this->targetDir = __DIR__ . '/target';
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0755, true);
        }
    }

    /**
     * Create a simple test PDF.
     */
    private function createTestPdf(int $pages = 3, string $prefix = 'Page'): string
    {
        $document = PdfDocument::create();

        for ($i = 1; $i <= $pages; $i++) {
            $page = new Page(PageSize::a4());
            $page->addText("{$prefix} {$i}", 100, 700, ['fontSize' => 24]);
            $page->addText("This is content for page {$i}", 100, 650, ['fontSize' => 12]);
            $document->addPageObject($page);
        }

        return $document->render();
    }

    // ==================== Merger Tests ====================

    public function testMergerCanMergeMultiplePdfs(): void
    {
        $pdf1 = $this->createTestPdf(2, 'Doc1 Page');
        $pdf2 = $this->createTestPdf(3, 'Doc2 Page');

        $merger = new Merger();
        $merger->addContent($pdf1)
               ->addContent($pdf2);

        $merged = $merger->merge();

        $this->assertStringStartsWith('%PDF-', $merged);
        $this->assertStringContainsString('%%EOF', $merged);
    }

    public function testMergerCanSelectSpecificPages(): void
    {
        $pdf = $this->createTestPdf(5);

        $merger = new Merger();
        $merger->addContent($pdf, [1, 3, 5]);

        $document = $merger->merge();
        $this->assertStringStartsWith('%PDF-', $document);
    }

    public function testMergerCanUseRangeString(): void
    {
        $pdf = $this->createTestPdf(10);

        $merger = new Merger();
        $merger->addContent($pdf, '1-3,5,7-9');

        $document = $merger->merge();
        $this->assertStringStartsWith('%PDF-', $document);
    }

    public function testMergerSavesToFile(): void
    {
        $pdf1 = $this->createTestPdf(2);
        $pdf2 = $this->createTestPdf(2);

        $outputPath = $this->targetDir . '/merged_test.pdf';

        $merger = new Merger();
        $result = $merger->addContent($pdf1)
                        ->addContent($pdf2)
                        ->save($outputPath);

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);
    }

    public function testMergerThrowsOnEmptySources(): void
    {
        $this->expectException(\RuntimeException::class);

        $merger = new Merger();
        $merger->merge();
    }

    public function testMergerThrowsOnInvalidPdf(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $merger = new Merger();
        $merger->addContent('not a pdf');
    }

    // ==================== Splitter Tests ====================

    public function testSplitterCanExtractSinglePage(): void
    {
        $pdf = $this->createTestPdf(5);

        $splitter = new Splitter();
        $splitter->loadContent($pdf);

        $this->assertEquals(5, $splitter->getPageCount());

        $extracted = $splitter->extractPage(3);
        $this->assertEquals(1, $extracted->getPageCount());
    }

    public function testSplitterCanExtractMultiplePages(): void
    {
        $pdf = $this->createTestPdf(10);

        $splitter = new Splitter();
        $splitter->loadContent($pdf);

        $extracted = $splitter->extractPages([1, 3, 5, 7]);
        $this->assertEquals(4, $extracted->getPageCount());
    }

    public function testSplitterCanExtractRange(): void
    {
        $pdf = $this->createTestPdf(10);

        $splitter = new Splitter();
        $splitter->loadContent($pdf);

        $extracted = $splitter->extractRange(3, 7);
        $this->assertEquals(5, $extracted->getPageCount());
    }

    public function testSplitterCanSplitByPageCount(): void
    {
        $pdf = $this->createTestPdf(10);

        $splitter = new Splitter();
        $splitter->loadContent($pdf);

        $chunks = $splitter->splitByPageCount(3);
        $this->assertCount(4, $chunks); // 3+3+3+1 = 10
    }

    public function testSplitterCanExtractOddPages(): void
    {
        $pdf = $this->createTestPdf(6);

        $splitter = new Splitter();
        $splitter->loadContent($pdf);

        $odd = $splitter->extractOddPages();
        $this->assertEquals(3, $odd->getPageCount()); // Pages 1, 3, 5
    }

    public function testSplitterCanExtractEvenPages(): void
    {
        $pdf = $this->createTestPdf(6);

        $splitter = new Splitter();
        $splitter->loadContent($pdf);

        $even = $splitter->extractEvenPages();
        $this->assertEquals(3, $even->getPageCount()); // Pages 2, 4, 6
    }

    public function testSplitterCanReversePages(): void
    {
        $pdf = $this->createTestPdf(5);

        $splitter = new Splitter();
        $splitter->loadContent($pdf);

        $reversed = $splitter->extractReversed();
        $this->assertEquals(5, $reversed->getPageCount());
    }

    public function testSplitterCanExtractFirstN(): void
    {
        $pdf = $this->createTestPdf(10);

        $splitter = new Splitter();
        $splitter->loadContent($pdf);

        $first = $splitter->extractFirst(3);
        $this->assertEquals(3, $first->getPageCount());
    }

    public function testSplitterCanExtractLastN(): void
    {
        $pdf = $this->createTestPdf(10);

        $splitter = new Splitter();
        $splitter->loadContent($pdf);

        $last = $splitter->extractLast(3);
        $this->assertEquals(3, $last->getPageCount());
    }

    // ==================== Stamper Tests ====================

    public function testStamperCanAddWatermark(): void
    {
        $pdf = $this->createTestPdf(3);

        $stamper = new Stamper();
        $stamper->loadContent($pdf);
        $stamper->addWatermark('CONFIDENTIAL', 45.0, 0.3);

        $document = $stamper->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testStamperCanAddPageNumbers(): void
    {
        $pdf = $this->createTestPdf(5);

        $stamper = new Stamper();
        $stamper->loadContent($pdf);
        $stamper->addPageNumbers('Page {page} of {total}', Stamper::POSITION_BOTTOM_CENTER);

        $document = $stamper->apply();
        $this->assertEquals(5, $document->getPageCount());
    }

    public function testStamperCanAddHeader(): void
    {
        $pdf = $this->createTestPdf(3);

        $stamper = new Stamper();
        $stamper->loadContent($pdf);
        $stamper->addHeader('My Document Header');

        $document = $stamper->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testStamperCanAddFooter(): void
    {
        $pdf = $this->createTestPdf(3);

        $stamper = new Stamper();
        $stamper->loadContent($pdf);
        $stamper->addFooter('Copyright 2024');

        $document = $stamper->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testStamperCanAddDraftWatermark(): void
    {
        $pdf = $this->createTestPdf(2);

        $stamper = new Stamper();
        $stamper->loadContent($pdf);
        $stamper->addDraftWatermark();

        $document = $stamper->apply();
        $this->assertEquals(2, $document->getPageCount());
    }

    public function testStamperCanAddConfidentialWatermark(): void
    {
        $pdf = $this->createTestPdf(2);

        $stamper = new Stamper();
        $stamper->loadContent($pdf);
        $stamper->addConfidentialWatermark();

        $document = $stamper->apply();
        $this->assertEquals(2, $document->getPageCount());
    }

    public function testStamperCanAddDateStamp(): void
    {
        $pdf = $this->createTestPdf(2);

        $stamper = new Stamper();
        $stamper->loadContent($pdf);
        $stamper->addDateStamp('Y-m-d', Stamper::POSITION_TOP_RIGHT);

        $document = $stamper->apply();
        $this->assertEquals(2, $document->getPageCount());
    }

    public function testStamperSavesToFile(): void
    {
        $pdf = $this->createTestPdf(3);
        $outputPath = $this->targetDir . '/stamped_test.pdf';

        $stamper = new Stamper();
        $stamper->loadContent($pdf);
        $stamper->addWatermark('TEST');

        $result = $stamper->save($outputPath);

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);
    }

    // ==================== Rotator Tests ====================

    public function testRotatorCanRotateSinglePage(): void
    {
        $pdf = $this->createTestPdf(5);

        $rotator = new Rotator();
        $rotator->loadContent($pdf);
        $rotator->rotatePage(1, 90);

        $document = $rotator->apply();
        $this->assertEquals(5, $document->getPageCount());
    }

    public function testRotatorCanRotateAllPages(): void
    {
        $pdf = $this->createTestPdf(5);

        $rotator = new Rotator();
        $rotator->loadContent($pdf);
        $rotator->rotatePages(90);

        $document = $rotator->apply();
        $this->assertEquals(5, $document->getPageCount());
    }

    public function testRotatorCanRotateClockwise(): void
    {
        $pdf = $this->createTestPdf(3);

        $rotator = new Rotator();
        $rotator->loadContent($pdf);
        $rotator->rotateClockwise();

        $document = $rotator->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testRotatorCanRotateCounterClockwise(): void
    {
        $pdf = $this->createTestPdf(3);

        $rotator = new Rotator();
        $rotator->loadContent($pdf);
        $rotator->rotateCounterClockwise();

        $document = $rotator->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testRotatorCanRotateUpsideDown(): void
    {
        $pdf = $this->createTestPdf(3);

        $rotator = new Rotator();
        $rotator->loadContent($pdf);
        $rotator->rotateUpsideDown();

        $document = $rotator->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testRotatorCanRotateOddPages(): void
    {
        $pdf = $this->createTestPdf(6);

        $rotator = new Rotator();
        $rotator->loadContent($pdf);
        $rotator->rotateOddPages(90);

        $document = $rotator->apply();
        $this->assertEquals(6, $document->getPageCount());
    }

    public function testRotatorCanRotateEvenPages(): void
    {
        $pdf = $this->createTestPdf(6);

        $rotator = new Rotator();
        $rotator->loadContent($pdf);
        $rotator->rotateEvenPages(90);

        $document = $rotator->apply();
        $this->assertEquals(6, $document->getPageCount());
    }

    public function testRotatorNormalizesRotation(): void
    {
        $pdf = $this->createTestPdf(3);

        $rotator = new Rotator();
        $rotator->loadContent($pdf);
        $rotator->rotatePage(1, 450); // Should normalize to 90

        $this->assertEquals(90, $rotator->getPageRotation(1));
    }

    public function testRotatorCanResetRotation(): void
    {
        $pdf = $this->createTestPdf(3);

        $rotator = new Rotator();
        $rotator->loadContent($pdf);
        $rotator->rotatePage(1, 90);
        $rotator->resetPageRotation(1);

        $this->assertEquals(0, $rotator->getPageRotation(1));
    }

    // ==================== Cropper Tests ====================

    public function testCropperCanSetCropBox(): void
    {
        $pdf = $this->createTestPdf(3);

        $cropper = new Cropper();
        $cropper->loadContent($pdf);
        $cropper->setCropBox(50, 50, 545, 742);

        $document = $cropper->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testCropperCanCropToStandardSize(): void
    {
        $pdf = $this->createTestPdf(3);

        $cropper = new Cropper();
        $cropper->loadContent($pdf);
        $cropper->cropToSize('A5');

        $document = $cropper->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testCropperCanResizeToStandardSize(): void
    {
        $pdf = $this->createTestPdf(3);

        $cropper = new Cropper();
        $cropper->loadContent($pdf);
        $cropper->resizeTo('LETTER');

        $document = $cropper->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testCropperCanAddMargins(): void
    {
        $pdf = $this->createTestPdf(3);

        $cropper = new Cropper();
        $cropper->loadContent($pdf);
        $cropper->addMargin(36); // 0.5 inch margin

        $document = $cropper->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testCropperCanAddDifferentMargins(): void
    {
        $pdf = $this->createTestPdf(3);

        $cropper = new Cropper();
        $cropper->loadContent($pdf);
        $cropper->addMargins(72, 36, 72, 36); // 1" top/bottom, 0.5" left/right

        $document = $cropper->apply();
        $this->assertEquals(3, $document->getPageCount());
    }

    public function testCropperGetMediaBox(): void
    {
        $pdf = $this->createTestPdf(1);

        $cropper = new Cropper();
        $cropper->loadContent($pdf);

        $mediaBox = $cropper->getMediaBox(1);
        $this->assertCount(4, $mediaBox);
    }

    public function testCropperGetSupportedSizes(): void
    {
        $sizes = Cropper::getSupportedSizes();

        $this->assertArrayHasKey('A4', $sizes);
        $this->assertArrayHasKey('LETTER', $sizes);
        $this->assertArrayHasKey('LEGAL', $sizes);
    }

    // ==================== Optimizer Tests ====================

    public function testOptimizerCanOptimizePdf(): void
    {
        $pdf = $this->createTestPdf(5);

        $optimizer = new Optimizer();
        $optimizer->loadContent($pdf);
        $optimizer->setLevel(Optimizer::LEVEL_STANDARD);

        $optimized = $optimizer->optimize();

        $this->assertStringStartsWith('%PDF-', $optimized);
        $this->assertStringContainsString('%%EOF', $optimized);
    }

    public function testOptimizerMinimalLevel(): void
    {
        $pdf = $this->createTestPdf(3);

        $optimizer = new Optimizer();
        $optimizer->loadContent($pdf);
        $optimizer->setLevel(Optimizer::LEVEL_MINIMAL);

        $optimized = $optimizer->optimize();
        $this->assertStringStartsWith('%PDF-', $optimized);
    }

    public function testOptimizerMaximumLevel(): void
    {
        $pdf = $this->createTestPdf(3);

        $optimizer = new Optimizer();
        $optimizer->loadContent($pdf);
        $optimizer->setLevel(Optimizer::LEVEL_MAXIMUM);

        $optimized = $optimizer->optimize();
        $this->assertStringStartsWith('%PDF-', $optimized);
    }

    public function testOptimizerGetStatistics(): void
    {
        $pdf = $this->createTestPdf(5);

        $optimizer = new Optimizer();
        $optimizer->loadContent($pdf);
        $optimizer->optimize();

        $stats = $optimizer->getStatistics();

        $this->assertArrayHasKey('originalSize', $stats);
        $this->assertArrayHasKey('optimizedSize', $stats);
        $this->assertArrayHasKey('compressionRatio', $stats);
        $this->assertArrayHasKey('objectsRemoved', $stats);
        $this->assertArrayHasKey('duplicatesRemoved', $stats);
    }

    public function testOptimizerSavesToFile(): void
    {
        $pdf = $this->createTestPdf(3);
        $outputPath = $this->targetDir . '/optimized_test.pdf';

        $optimizer = new Optimizer();
        $optimizer->loadContent($pdf);

        $result = $optimizer->save($outputPath);

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);
    }

    // ==================== Integration Tests with File Output ====================

    public function testCreateMergedPdfOutput(): void
    {
        $doc1 = PdfDocument::create();
        $page1 = new Page(PageSize::a4());
        $page1->addText('Document 1 - Page 1', 100, 700, ['fontSize' => 24]);
        $page1->addText('This is the first document', 100, 650, ['fontSize' => 12]);
        $doc1->addPageObject($page1);

        $page2 = new Page(PageSize::a4());
        $page2->addText('Document 1 - Page 2', 100, 700, ['fontSize' => 24]);
        $doc1->addPageObject($page2);

        $doc2 = PdfDocument::create();
        $page3 = new Page(PageSize::a4());
        $page3->addText('Document 2 - Page 1', 100, 700, ['fontSize' => 24]);
        $page3->addText('This is the second document', 100, 650, ['fontSize' => 12]);
        $doc2->addPageObject($page3);

        $outputPath = $this->targetDir . '/merged_documents.pdf';

        $merger = new Merger();
        $merger->addContent($doc1->render())
               ->addContent($doc2->render())
               ->save($outputPath);

        $this->assertFileExists($outputPath);
        echo "\nMerged PDF created: {$outputPath}\n";
    }

    public function testCreateSplitPdfOutput(): void
    {
        // Create a multi-page document
        $document = PdfDocument::create();
        for ($i = 1; $i <= 5; $i++) {
            $page = new Page(PageSize::a4());
            $page->addText("Original Page {$i}", 100, 700, ['fontSize' => 24]);
            $page->addText("Content for page {$i} of the original document", 100, 650, ['fontSize' => 12]);
            $document->addPageObject($page);
        }

        $pdfContent = $document->render();

        // Split and save first 2 pages
        $splitter = new Splitter();
        $splitter->loadContent($pdfContent);

        $first2 = $splitter->extractFirst(2);
        $outputPath1 = $this->targetDir . '/split_first2.pdf';
        $first2->save($outputPath1);

        // Extract odd pages
        $odd = $splitter->extractOddPages();
        $outputPath2 = $this->targetDir . '/split_odd.pdf';
        $odd->save($outputPath2);

        $this->assertFileExists($outputPath1);
        $this->assertFileExists($outputPath2);
        echo "\nSplit PDFs created: {$outputPath1}, {$outputPath2}\n";
    }

    public function testCreateStampedPdfOutput(): void
    {
        $document = PdfDocument::create();
        for ($i = 1; $i <= 3; $i++) {
            $page = new Page(PageSize::a4());
            $page->addText("Page {$i}", 100, 700, ['fontSize' => 24]);
            $page->addText("Regular content goes here.", 100, 650, ['fontSize' => 12]);
            $document->addPageObject($page);
        }

        $outputPath = $this->targetDir . '/stamped_document.pdf';

        $stamper = new Stamper();
        $stamper->loadContent($document->render());
        $stamper->addWatermark('DRAFT', 45.0, 0.2)
                ->addPageNumbers('Page {page} of {total}', Stamper::POSITION_BOTTOM_CENTER)
                ->addHeader('Company Name')
                ->addFooter('Confidential Document')
                ->save($outputPath);

        $this->assertFileExists($outputPath);
        echo "\nStamped PDF created: {$outputPath}\n";
    }

    public function testCreateRotatedPdfOutput(): void
    {
        $document = PdfDocument::create();
        for ($i = 1; $i <= 4; $i++) {
            $page = new Page(PageSize::a4());
            $page->addText("Page {$i}", 100, 700, ['fontSize' => 24]);
            $page->addText("This page may be rotated", 100, 650, ['fontSize' => 12]);
            $document->addPageObject($page);
        }

        $outputPath = $this->targetDir . '/rotated_document.pdf';

        $rotator = new Rotator();
        $rotator->loadContent($document->render());
        $rotator->rotatePage(1, 90)    // Page 1: 90 degrees
                ->rotatePage(2, 180)   // Page 2: 180 degrees
                ->rotatePage(3, 270)   // Page 3: 270 degrees
                // Page 4: no rotation
                ->save($outputPath);

        $this->assertFileExists($outputPath);
        echo "\nRotated PDF created: {$outputPath}\n";
    }

    public function testCreateCroppedPdfOutput(): void
    {
        $document = PdfDocument::create();
        for ($i = 1; $i <= 2; $i++) {
            $page = new Page(PageSize::a4());
            $page->addText("Page {$i}", 100, 700, ['fontSize' => 24]);
            $page->addText("This page has margins added", 100, 650, ['fontSize' => 12]);
            $document->addPageObject($page);
        }

        $outputPath = $this->targetDir . '/cropped_document.pdf';

        $cropper = new Cropper();
        $cropper->loadContent($document->render());
        $cropper->addMargins(72, 36, 72, 36) // 1" top/bottom, 0.5" left/right
                ->save($outputPath);

        $this->assertFileExists($outputPath);
        echo "\nCropped PDF created: {$outputPath}\n";
    }
}
