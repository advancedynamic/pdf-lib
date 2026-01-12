<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PdfLib\Color\RgbColor;
use PdfLib\Content\ContentStream;
use PdfLib\Content\Text\Paragraph;
use PdfLib\Content\Text\TextBlock;
use PdfLib\Content\Text\TextStyle;
use PdfLib\Font\Type1Font;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    // TextStyle Tests

    public function testTextStyleCreation(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $this->assertSame($font, $style->getFont());
        $this->assertSame(12.0, $style->getFontSize());
    }

    public function testTextStyleWithColor(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12, RgbColor::red());

        $this->assertTrue($style->getColor()->equals(RgbColor::red()));
    }

    public function testTextStyleImmutability(): void
    {
        $font = Type1Font::helvetica();
        $style1 = new TextStyle($font, 12);
        $style2 = $style1->withFontSize(14);

        $this->assertSame(12.0, $style1->getFontSize());
        $this->assertSame(14.0, $style2->getFontSize());
        $this->assertNotSame($style1, $style2);
    }

    public function testTextStyleWithFont(): void
    {
        $font1 = Type1Font::helvetica();
        $font2 = Type1Font::timesBold();

        $style1 = new TextStyle($font1, 12);
        $style2 = $style1->withFont($font2);

        $this->assertSame($font1, $style1->getFont());
        $this->assertSame($font2, $style2->getFont());
    }

    public function testTextStyleWithColor2(): void
    {
        $font = Type1Font::helvetica();
        $style1 = new TextStyle($font, 12, RgbColor::black());
        $style2 = $style1->withColor(RgbColor::blue());

        $this->assertTrue($style1->getColor()->equals(RgbColor::black()));
        $this->assertTrue($style2->getColor()->equals(RgbColor::blue()));
    }

    public function testTextStyleCharacterSpacing(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $this->assertSame(0.0, $style->getCharacterSpacing());

        $style2 = $style->withCharacterSpacing(0.5);
        $this->assertSame(0.5, $style2->getCharacterSpacing());
    }

    public function testTextStyleWordSpacing(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $this->assertSame(0.0, $style->getWordSpacing());

        $style2 = $style->withWordSpacing(2.0);
        $this->assertSame(2.0, $style2->getWordSpacing());
    }

    public function testTextStyleLeading(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        // Default leading is fontSize * 1.2
        $this->assertEqualsWithDelta(14.4, $style->getLeading(), 0.0001);

        $style2 = $style->withLeading(18.0);
        $this->assertSame(18.0, $style2->getLeading());
    }

    public function testTextStyleUnderline(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $this->assertFalse($style->isUnderline());

        $style2 = $style->withUnderline(true);
        $this->assertTrue($style2->isUnderline());
    }

    public function testTextStyleStrikethrough(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $this->assertFalse($style->isStrikethrough());

        $style2 = $style->withStrikethrough(true);
        $this->assertTrue($style2->isStrikethrough());
    }

    public function testTextStyleHorizontalScaling(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $this->assertSame(100.0, $style->getHorizontalScaling());

        $style2 = $style->withHorizontalScaling(150.0);
        $this->assertSame(150.0, $style2->getHorizontalScaling());
    }

    public function testTextStyleRise(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $this->assertSame(0.0, $style->getRise());

        $style2 = $style->withRise(3.0);
        $this->assertSame(3.0, $style2->getRise());
    }

    public function testTextStyleRenderingMode(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $this->assertSame(0, $style->getRenderingMode());

        $style2 = $style->withRenderingMode(TextStyle::RENDER_STROKE);
        $this->assertSame(1, $style2->getRenderingMode());
    }

    public function testTextStyleGetTextWidth(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $width = $style->getTextWidth('Hello');
        $this->assertGreaterThan(0, $width);
    }

    public function testTextStyleLineHeight(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $lineHeight = $style->getLineHeight();
        $this->assertGreaterThan(0, $lineHeight);
    }

    public function testTextStyleAscender(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $ascender = $style->getAscender();
        $this->assertGreaterThan(0, $ascender);
    }

    public function testTextStyleDescender(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);

        $descender = $style->getDescender();
        $this->assertLessThan(0, $descender);
    }

    // TextBlock Tests

    public function testTextBlockCreation(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $block = new TextBlock('Hello World', $style);

        $this->assertSame('Hello World', $block->getText());
        $this->assertSame($style, $block->getStyle());
    }

    public function testTextBlockPosition(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $block = new TextBlock('Test', $style);
        $block->setPosition(100, 200);

        $this->assertSame(100.0, $block->getX());
        $this->assertSame(200.0, $block->getY());
    }

    public function testTextBlockAngle(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $block = new TextBlock('Rotated', $style);
        $block->setAngle(45);

        $this->assertSame(45.0, $block->getAngle());
    }

    public function testTextBlockWidth(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $block = new TextBlock('Hello', $style);

        $width = $block->getWidth();
        $this->assertGreaterThan(0, $width);
    }

    public function testTextBlockHeight(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $block = new TextBlock('Test', $style);

        $height = $block->getHeight();
        $this->assertGreaterThan(0, $height);
    }

    public function testTextBlockRender(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $block = new TextBlock('Hello', $style);
        $block->setPosition(100, 200);

        $stream = new ContentStream();
        $block->render($stream);
        $content = $stream->toString();

        $this->assertStringContainsString('BT', $content);
        $this->assertStringContainsString('ET', $content);
        $this->assertStringContainsString('Hello', $content);
    }

    public function testTextBlockRenderWithAngle(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $block = new TextBlock('Rotated', $style);
        $block->setPosition(100, 200);
        $block->setAngle(45);

        $stream = new ContentStream();
        $block->render($stream);
        $content = $stream->toString();

        // Should use text matrix for rotation
        $this->assertStringContainsString('Tm', $content);
    }

    public function testTextBlockSetText(): void
    {
        $block = new TextBlock('Original');
        $block->setText('Updated');

        $this->assertSame('Updated', $block->getText());
    }

    public function testTextBlockSetX(): void
    {
        $block = new TextBlock('Test');
        $block->setX(150);

        $this->assertSame(150.0, $block->getX());
    }

    public function testTextBlockSetY(): void
    {
        $block = new TextBlock('Test');
        $block->setY(250);

        $this->assertSame(250.0, $block->getY());
    }

    // Paragraph Tests

    public function testParagraphCreation(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $para = new Paragraph('This is a paragraph.', $style);

        $this->assertSame('This is a paragraph.', $para->getText());
    }

    public function testParagraphPosition(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $para = new Paragraph('Test', $style);
        $para->setPosition(50, 700);

        $this->assertSame(50.0, $para->getX());
        $this->assertSame(700.0, $para->getY());
    }

    public function testParagraphMaxWidth(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $para = new Paragraph('Test', $style);
        $para->setMaxWidth(200);

        $this->assertSame(200.0, $para->getMaxWidth());
    }

    public function testParagraphAlignment(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $para = new Paragraph('Test', $style);

        $this->assertSame('left', $para->getAlign());

        $para->setAlign('center');
        $this->assertSame('center', $para->getAlign());

        $para->setAlign('right');
        $this->assertSame('right', $para->getAlign());

        $para->setAlign('justify');
        $this->assertSame('justify', $para->getAlign());
    }

    public function testParagraphFirstLineIndent(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $para = new Paragraph('Test', $style);

        $this->assertSame(0.0, $para->getFirstLineIndent());

        $para->setFirstLineIndent(20);
        $this->assertSame(20.0, $para->getFirstLineIndent());
    }

    public function testParagraphParagraphSpacing(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $para = new Paragraph('Test', $style);

        $this->assertSame(0.0, $para->getParagraphSpacing());

        $para->setParagraphSpacing(10);
        $this->assertSame(10.0, $para->getParagraphSpacing());
    }

    public function testParagraphGetLinesNoWrap(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $text = "Line one\nLine two";
        $para = new Paragraph($text, $style);
        // No maxWidth set, so no wrapping

        $lines = $para->getLines();
        $this->assertCount(2, $lines);
        $this->assertSame('Line one', $lines[0]);
        $this->assertSame('Line two', $lines[1]);
    }

    public function testParagraphGetLinesWithWrap(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $text = 'This is a longer paragraph that should wrap to multiple lines when rendered.';
        $para = new Paragraph($text, $style);
        $para->setMaxWidth(100);

        $lines = $para->getLines();
        $this->assertGreaterThan(1, count($lines));
    }

    public function testParagraphHeight(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $text = 'Line one. Line two.';
        $para = new Paragraph($text, $style);
        $para->setMaxWidth(200);

        $height = $para->getHeight();
        $this->assertGreaterThan(0, $height);
    }

    public function testParagraphRender(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $para = new Paragraph('Hello World', $style);
        $para->setPosition(50, 700);
        $para->setMaxWidth(200);

        $stream = new ContentStream();
        $para->render($stream);
        $content = $stream->toString();

        $this->assertStringContainsString('BT', $content);
        $this->assertStringContainsString('ET', $content);
        $this->assertStringContainsString('Hello World', $content);
    }

    public function testParagraphWrapLongWord(): void
    {
        $font = Type1Font::helvetica();
        $style = new TextStyle($font, 12);
        $text = 'Supercalifragilisticexpialidocious';
        $para = new Paragraph($text, $style);
        $para->setMaxWidth(50);

        // Should handle long words by breaking them
        $lines = $para->getLines();
        $this->assertGreaterThan(0, count($lines));
    }

    public function testParagraphAlignmentConstants(): void
    {
        $this->assertSame('left', Paragraph::ALIGN_LEFT);
        $this->assertSame('center', Paragraph::ALIGN_CENTER);
        $this->assertSame('right', Paragraph::ALIGN_RIGHT);
        $this->assertSame('justify', Paragraph::ALIGN_JUSTIFY);
    }

    public function testParagraphSetText(): void
    {
        $para = new Paragraph('Original');
        $para->setText('Updated');

        $this->assertSame('Updated', $para->getText());
    }

    public function testParagraphSetStyle(): void
    {
        $font = Type1Font::helvetica();
        $style1 = new TextStyle($font, 12);
        $style2 = new TextStyle($font, 14);

        $para = new Paragraph('Test', $style1);
        $para->setStyle($style2);

        $this->assertSame($style2, $para->getStyle());
    }
}
