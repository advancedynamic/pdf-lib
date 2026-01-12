<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PdfLib\Font\FontFactory;
use PdfLib\Font\Type1Font;
use PHPUnit\Framework\TestCase;

class FontTest extends TestCase
{
    public function testType1FontHelvetica(): void
    {
        $font = Type1Font::helvetica();

        $this->assertSame('Helvetica', $font->getName());
        $this->assertSame('Type1', $font->getType());
        $this->assertFalse($font->isEmbedded());
    }

    public function testType1FontTimes(): void
    {
        $font = Type1Font::timesRoman();

        $this->assertSame('Times-Roman', $font->getName());
        $this->assertSame('Type1', $font->getType());
    }

    public function testType1FontCourier(): void
    {
        $font = Type1Font::courier();

        $this->assertSame('Courier', $font->getName());
        $this->assertTrue($font->getMetrics()->isFixedPitch());
    }

    public function testFontMetrics(): void
    {
        $font = Type1Font::helvetica();
        $metrics = $font->getMetrics();

        $this->assertGreaterThan(0, $metrics->getAscender());
        $this->assertLessThan(0, $metrics->getDescender());
        $this->assertGreaterThan(0, $metrics->getCapHeight());
        $this->assertGreaterThan(0, $metrics->getXHeight());
    }

    public function testTextWidth(): void
    {
        $font = Type1Font::helvetica();
        $width = $font->getTextWidth('Hello', 12);

        $this->assertGreaterThan(0, $width);
    }

    public function testCharWidth(): void
    {
        $font = Type1Font::helvetica();
        $width = $font->getCharWidth('A');

        $this->assertGreaterThan(0, $width);
    }

    public function testCourierMonospace(): void
    {
        $font = Type1Font::courier();

        // All chars in Courier should have same width
        $widthA = $font->getCharWidth('A');
        $widthI = $font->getCharWidth('i');

        $this->assertSame($widthA, $widthI);
    }

    public function testFontToDictionary(): void
    {
        $font = Type1Font::helvetica();
        $dict = $font->toDictionary();

        $this->assertSame('Font', $dict->get('Type')->getValue());
        $this->assertSame('Type1', $dict->get('Subtype')->getValue());
        $this->assertSame('Helvetica', $dict->get('BaseFont')->getValue());
    }

    public function testFontFactoryCreate(): void
    {
        $font = FontFactory::create('Helvetica');
        $this->assertSame('Helvetica', $font->getName());

        $font = FontFactory::create('arial'); // alias
        $this->assertSame('Helvetica', $font->getName());
    }

    public function testFontFactoryIsAvailable(): void
    {
        $this->assertTrue(FontFactory::isAvailable('Helvetica'));
        $this->assertTrue(FontFactory::isAvailable('arial'));
        $this->assertTrue(FontFactory::isAvailable('Times-Roman'));
        $this->assertFalse(FontFactory::isAvailable('NonExistentFont'));
    }

    public function testStandardFontNames(): void
    {
        $names = FontFactory::getStandardFontNames();

        $this->assertContains('Helvetica', $names);
        $this->assertContains('Times-Roman', $names);
        $this->assertContains('Courier', $names);
        $this->assertContains('Symbol', $names);
        $this->assertContains('ZapfDingbats', $names);
        $this->assertCount(14, $names);
    }

    public function testFontBoldVariant(): void
    {
        $font = Type1Font::helveticaBold();
        $metrics = $font->getMetrics();

        $this->assertTrue($metrics->isForceBold());
    }

    public function testFontItalicVariant(): void
    {
        $font = Type1Font::helveticaOblique();
        $metrics = $font->getMetrics();

        $this->assertTrue($metrics->isItalic());
        $this->assertNotSame(0, $metrics->getItalicAngle());
    }

    public function testFontLineHeight(): void
    {
        $font = Type1Font::helvetica();
        $lineHeight = $font->getLineHeight();

        $this->assertGreaterThan($font->getAscender(), $lineHeight);
    }

    public function testHasCharacter(): void
    {
        $font = Type1Font::helvetica();

        $this->assertTrue($font->hasCharacter('A'));
        $this->assertTrue($font->hasCharacter('z'));
        $this->assertTrue($font->hasCharacter('0'));
        $this->assertTrue($font->hasCharacter(' '));
    }
}
