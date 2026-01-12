<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PdfLib\Color\RgbColor;
use PdfLib\Content\ContentStream;
use PdfLib\Font\Type1Font;
use PHPUnit\Framework\TestCase;

class ContentStreamTest extends TestCase
{
    public function testEmptyContentStream(): void
    {
        $stream = new ContentStream();

        $this->assertSame('', $stream->toString());
    }

    public function testGraphicsStateSave(): void
    {
        $stream = new ContentStream();
        $stream->saveState();

        $this->assertStringContainsString('q', $stream->toString());
    }

    public function testGraphicsStateRestore(): void
    {
        $stream = new ContentStream();
        $stream->saveState();
        $stream->restoreState();

        $content = $stream->toString();
        $this->assertStringContainsString('q', $content);
        $this->assertStringContainsString('Q', $content);
    }

    public function testMoveTo(): void
    {
        $stream = new ContentStream();
        $stream->moveTo(100, 200);

        $this->assertStringContainsString('100', $stream->toString());
        $this->assertStringContainsString('200', $stream->toString());
        $this->assertStringContainsString('m', $stream->toString());
    }

    public function testLineTo(): void
    {
        $stream = new ContentStream();
        $stream->moveTo(0, 0);
        $stream->lineTo(100, 100);

        $this->assertStringContainsString('l', $stream->toString());
    }

    public function testCurveTo(): void
    {
        $stream = new ContentStream();
        $stream->moveTo(0, 0);
        $stream->curveTo(10, 20, 30, 40, 50, 60);

        $this->assertStringContainsString('c', $stream->toString());
    }

    public function testClosePath(): void
    {
        $stream = new ContentStream();
        $stream->moveTo(0, 0);
        $stream->lineTo(100, 0);
        $stream->lineTo(100, 100);
        $stream->closePath();

        $this->assertStringContainsString('h', $stream->toString());
    }

    public function testStroke(): void
    {
        $stream = new ContentStream();
        $stream->moveTo(0, 0);
        $stream->lineTo(100, 100);
        $stream->stroke();

        $this->assertStringContainsString('S', $stream->toString());
    }

    public function testFill(): void
    {
        $stream = new ContentStream();
        $stream->moveTo(0, 0);
        $stream->lineTo(100, 0);
        $stream->lineTo(50, 100);
        $stream->fill();

        $this->assertStringContainsString('f', $stream->toString());
    }

    public function testFillAndStroke(): void
    {
        $stream = new ContentStream();
        $stream->moveTo(0, 0);
        $stream->lineTo(100, 0);
        $stream->lineTo(50, 100);
        $stream->fillAndStroke();

        $this->assertStringContainsString('B', $stream->toString());
    }

    public function testSetStrokeColor(): void
    {
        $stream = new ContentStream();
        $stream->setStrokeColor(RgbColor::red());

        $this->assertStringContainsString('RG', $stream->toString());
    }

    public function testSetFillColor(): void
    {
        $stream = new ContentStream();
        $stream->setFillColor(RgbColor::blue());

        $this->assertStringContainsString('rg', $stream->toString());
    }

    public function testSetLineWidth(): void
    {
        $stream = new ContentStream();
        $stream->setLineWidth(2.5);

        $content = $stream->toString();
        $this->assertStringContainsString('2.5', $content);
        $this->assertStringContainsString('w', $content);
    }

    public function testSetLineCap(): void
    {
        $stream = new ContentStream();
        $stream->setLineCap(1);

        $this->assertStringContainsString('1 J', $stream->toString());
    }

    public function testSetLineJoin(): void
    {
        $stream = new ContentStream();
        $stream->setLineJoin(2);

        $this->assertStringContainsString('2 j', $stream->toString());
    }

    public function testSetDashPattern(): void
    {
        $stream = new ContentStream();
        $stream->setDashPattern([3.0, 2.0], 0);

        $this->assertStringContainsString('[3.0000 2.0000] 0.0000 d', $stream->toString());
    }

    public function testRectangle(): void
    {
        $stream = new ContentStream();
        $stream->rectangle(10, 20, 100, 50);

        $content = $stream->toString();
        $this->assertStringContainsString('10', $content);
        $this->assertStringContainsString('20', $content);
        $this->assertStringContainsString('100', $content);
        $this->assertStringContainsString('50', $content);
        $this->assertStringContainsString('re', $content);
    }

    public function testBeginText(): void
    {
        $stream = new ContentStream();
        $stream->beginText();

        $this->assertStringContainsString('BT', $stream->toString());
    }

    public function testEndText(): void
    {
        $stream = new ContentStream();
        $stream->beginText();
        $stream->endText();

        $content = $stream->toString();
        $this->assertStringContainsString('BT', $content);
        $this->assertStringContainsString('ET', $content);
    }

    public function testSetFont(): void
    {
        $stream = new ContentStream();
        $stream->setFont('F1', 12);

        $content = $stream->toString();
        $this->assertStringContainsString('/F1', $content);
        $this->assertStringContainsString('12', $content);
        $this->assertStringContainsString('Tf', $content);
    }

    public function testMoveTextPosition(): void
    {
        $stream = new ContentStream();
        $stream->beginText();
        $stream->moveTextPosition(100, 200);

        $content = $stream->toString();
        $this->assertStringContainsString('100', $content);
        $this->assertStringContainsString('200', $content);
        $this->assertStringContainsString('Td', $content);
    }

    public function testShowText(): void
    {
        $stream = new ContentStream();
        $stream->beginText();
        $stream->setFont('F1', 12);
        $stream->showText('Hello World');
        $stream->endText();

        $content = $stream->toString();
        $this->assertStringContainsString('Hello World', $content);
        $this->assertStringContainsString('Tj', $content);
    }

    public function testSetTextMatrix(): void
    {
        $stream = new ContentStream();
        $stream->beginText();
        $stream->setTextMatrix(1, 0, 0, 1, 100, 200);

        $this->assertStringContainsString('Tm', $stream->toString());
    }

    public function testSetCharacterSpacing(): void
    {
        $stream = new ContentStream();
        $stream->beginText();
        $stream->setCharacterSpacing(0.5);

        $this->assertStringContainsString('Tc', $stream->toString());
    }

    public function testSetWordSpacing(): void
    {
        $stream = new ContentStream();
        $stream->beginText();
        $stream->setWordSpacing(1.5);

        $this->assertStringContainsString('Tw', $stream->toString());
    }

    public function testTransformMatrix(): void
    {
        $stream = new ContentStream();
        $stream->transform(1, 0, 0, 1, 50, 50);

        $this->assertStringContainsString('cm', $stream->toString());
    }

    public function testDrawXObject(): void
    {
        $stream = new ContentStream();
        $stream->drawXObject('Im1');

        $this->assertStringContainsString('/Im1 Do', $stream->toString());
    }

    public function testRegisterFont(): void
    {
        $stream = new ContentStream();
        $font = Type1Font::helvetica();
        $name = $stream->registerFont($font);

        $this->assertStringStartsWith('F', $name);

        $fonts = $stream->getFonts();
        $this->assertArrayHasKey($name, $fonts);
        $this->assertSame($font, $fonts[$name]);
    }

    public function testFluentInterface(): void
    {
        $stream = new ContentStream();

        $result = $stream
            ->saveState()
            ->setStrokeColor(RgbColor::black())
            ->setLineWidth(1)
            ->moveTo(0, 0)
            ->lineTo(100, 100)
            ->stroke()
            ->restoreState();

        $this->assertSame($stream, $result);
    }

    public function testGetBytes(): void
    {
        $stream = new ContentStream();
        $stream->moveTo(100, 100);

        $this->assertSame($stream->toString(), $stream->getBytes());
    }

    public function testEllipse(): void
    {
        $stream = new ContentStream();
        $stream->ellipse(50, 50, 30, 20);

        $content = $stream->toString();
        // Ellipse uses curveTo commands
        $this->assertStringContainsString('c', $content);
    }

    public function testCircle(): void
    {
        $stream = new ContentStream();
        $stream->circle(50, 50, 25);

        $content = $stream->toString();
        $this->assertStringContainsString('c', $content);
    }

    public function testLine(): void
    {
        $stream = new ContentStream();
        $stream->line(0, 0, 100, 100);

        $content = $stream->toString();
        $this->assertStringContainsString('m', $content);
        $this->assertStringContainsString('l', $content);
        $this->assertStringContainsString('S', $content);
    }

    public function testTextConvenience(): void
    {
        $stream = new ContentStream();
        $stream->text('Hello', 100, 200);

        $content = $stream->toString();
        $this->assertStringContainsString('BT', $content);
        $this->assertStringContainsString('Hello', $content);
        $this->assertStringContainsString('ET', $content);
    }

    public function testClip(): void
    {
        $stream = new ContentStream();
        $stream->rectangle(0, 0, 100, 100);
        $stream->clip();
        $stream->endPath();

        $content = $stream->toString();
        $this->assertStringContainsString('W', $content);
        $this->assertStringContainsString('n', $content);
    }
}
