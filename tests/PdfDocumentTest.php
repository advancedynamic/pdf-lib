<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PdfLib\Document\PdfDocument;
use PdfLib\Page\PageSize;
use PHPUnit\Framework\TestCase;

class PdfDocumentTest extends TestCase
{
    public function testCreateEmptyDocument(): void
    {
        $document = PdfDocument::create();

        $this->assertSame(0, $document->getPageCount());
    }

    public function testAddPages(): void
    {
        $document = PdfDocument::create();

        $document->addPage(PageSize::a4());
        $this->assertSame(1, $document->getPageCount());

        $document->addPage(PageSize::letter());
        $this->assertSame(2, $document->getPageCount());
    }

    public function testFluentInterface(): void
    {
        $document = PdfDocument::create()
            ->setTitle('Test Document')
            ->setAuthor('Test Author')
            ->addPage(PageSize::a4())
            ->addPage(PageSize::letter());

        $this->assertSame('Test Document', $document->getTitle());
        $this->assertSame('Test Author', $document->getAuthor());
        $this->assertSame(2, $document->getPageCount());
    }

    public function testSetMetadata(): void
    {
        $document = PdfDocument::create()
            ->setTitle('My Title')
            ->setAuthor('John Doe')
            ->setSubject('Test Subject')
            ->setKeywords('test, pdf, library')
            ->setCreator('Test App');

        $this->assertSame('My Title', $document->getTitle());
        $this->assertSame('John Doe', $document->getAuthor());

        $metadata = $document->getMetadata();
        $this->assertSame('Test Subject', $metadata->getSubject());
        $this->assertSame('test, pdf, library', $metadata->getKeywords());
        $this->assertSame('Test App', $metadata->getCreator());
    }

    public function testRemovePage(): void
    {
        $document = PdfDocument::create()
            ->addPage(PageSize::a4())
            ->addPage(PageSize::letter())
            ->addPage(PageSize::legal());

        $this->assertSame(3, $document->getPageCount());

        $document->removePage(1);
        $this->assertSame(2, $document->getPageCount());
    }

    public function testRenderDocument(): void
    {
        $document = PdfDocument::create()
            ->setTitle('Test PDF')
            ->addPage(PageSize::a4());

        $content = $document->render();

        // Check PDF header
        $this->assertStringStartsWith('%PDF-', $content);

        // Check for %%EOF
        $this->assertStringContainsString('%%EOF', $content);

        // Check for page content
        $this->assertStringContainsString('/Type /Page', $content);
        $this->assertStringContainsString('/Type /Catalog', $content);
    }

    public function testExtractPages(): void
    {
        $document = PdfDocument::create()
            ->addPage(PageSize::a4())
            ->addPage(PageSize::letter())
            ->addPage(PageSize::legal())
            ->addPage(PageSize::a3());

        $extracted = $document->extractPages([1, 3]);

        $this->assertSame(2, $extracted->getPageCount());
        $this->assertSame(4, $document->getPageCount()); // Original unchanged
    }

    public function testSplitDocument(): void
    {
        $document = PdfDocument::create()
            ->addPage(PageSize::a4())
            ->addPage(PageSize::a4())
            ->addPage(PageSize::a4())
            ->addPage(PageSize::a4())
            ->addPage(PageSize::a4());

        $chunks = $document->split(2);

        $this->assertCount(3, $chunks);
        $this->assertSame(2, $chunks[0]->getPageCount());
        $this->assertSame(2, $chunks[1]->getPageCount());
        $this->assertSame(1, $chunks[2]->getPageCount());
    }

    public function testMergeDocuments(): void
    {
        $doc1 = PdfDocument::create()
            ->addPage(PageSize::a4())
            ->addPage(PageSize::a4());

        $doc2 = PdfDocument::create()
            ->addPage(PageSize::letter())
            ->addPage(PageSize::letter())
            ->addPage(PageSize::letter());

        $doc1->merge($doc2);

        $this->assertSame(5, $doc1->getPageCount());
    }

    public function testSetVersion(): void
    {
        $document = PdfDocument::create()
            ->setVersion('2.0')
            ->addPage();

        $this->assertSame('2.0', $document->getVersion());

        $content = $document->render();
        $this->assertStringStartsWith('%PDF-2.0', $content);
    }
}
