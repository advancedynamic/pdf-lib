<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PdfLib\Page\Page;
use PdfLib\Page\PageBox;
use PdfLib\Page\PageSize;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase
{
    public function testCreatePageWithDefaultSize(): void
    {
        $page = new Page();

        // Default is A4
        $this->assertEqualsWithDelta(595, $page->getWidth(), 1);
        $this->assertEqualsWithDelta(842, $page->getHeight(), 1);
    }

    public function testCreatePageWithLetterSize(): void
    {
        $page = new Page(PageSize::letter());

        $this->assertSame(612.0, $page->getWidth());
        $this->assertSame(792.0, $page->getHeight());
    }

    public function testCreatePageWithA4Size(): void
    {
        $page = Page::create(PageSize::a4());

        $this->assertEqualsWithDelta(595, $page->getWidth(), 1);
        $this->assertEqualsWithDelta(842, $page->getHeight(), 1);
    }

    public function testPageRotation(): void
    {
        $page = new Page();

        $this->assertSame(0, $page->getRotation());

        $page->setRotation(90);
        $this->assertSame(90, $page->getRotation());

        $page->rotateClockwise();
        $this->assertSame(180, $page->getRotation());

        $page->rotateCounterClockwise();
        $this->assertSame(90, $page->getRotation());
    }

    public function testPageOrientation(): void
    {
        $portraitPage = new Page(PageSize::a4());
        $this->assertTrue($portraitPage->isPortrait());
        $this->assertFalse($portraitPage->isLandscape());

        $landscapePage = new Page(PageSize::a4()->landscape());
        $this->assertTrue($landscapePage->isLandscape());
        $this->assertFalse($landscapePage->isPortrait());
    }

    public function testPageBoxes(): void
    {
        $page = new Page(PageSize::a4());

        // MediaBox is set by default
        $mediaBox = $page->getMediaBox();
        $this->assertEqualsWithDelta(595, $mediaBox->getWidth(), 1);

        // Other boxes default to MediaBox
        $this->assertEquals($mediaBox->toArray(), $page->getCropBox()->toArray());
        $this->assertEquals($mediaBox->toArray(), $page->getBleedBox()->toArray());
        $this->assertEquals($mediaBox->toArray(), $page->getTrimBox()->toArray());
        $this->assertEquals($mediaBox->toArray(), $page->getArtBox()->toArray());

        // Set custom CropBox
        $cropBox = PageBox::create(500, 700, 50, 50);
        $page->setCropBox($cropBox);
        $this->assertSame(500.0, $page->getCropBox()->getWidth());
    }

    public function testEffectiveDimensionsWithRotation(): void
    {
        $page = new Page(PageSize::a4());

        // No rotation
        $this->assertEqualsWithDelta(595, $page->getEffectiveWidth(), 1);
        $this->assertEqualsWithDelta(842, $page->getEffectiveHeight(), 1);

        // 90 degree rotation swaps dimensions
        $page->setRotation(90);
        $this->assertEqualsWithDelta(842, $page->getEffectiveWidth(), 1);
        $this->assertEqualsWithDelta(595, $page->getEffectiveHeight(), 1);
    }
}
