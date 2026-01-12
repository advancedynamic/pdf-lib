<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PdfLib\Color\RgbColor;
use PdfLib\Content\ContentStream;
use PdfLib\Content\Graphics\Canvas;
use PdfLib\Content\Graphics\Path;
use PdfLib\Content\Graphics\Shape;
use PdfLib\Font\Type1Font;
use PHPUnit\Framework\TestCase;

class GraphicsTest extends TestCase
{
    // Path Tests

    public function testPathCreation(): void
    {
        $path = new Path();
        $this->assertCount(0, $path->getOperations());
    }

    public function testPathMoveTo(): void
    {
        $path = new Path();
        $path->moveTo(100, 200);

        $this->assertCount(1, $path->getOperations());
    }

    public function testPathLineTo(): void
    {
        $path = new Path();
        $path->moveTo(0, 0);
        $path->lineTo(100, 100);

        $this->assertCount(2, $path->getOperations());
    }

    public function testPathCurveTo(): void
    {
        $path = new Path();
        $path->moveTo(0, 0);
        $path->curveTo(10, 50, 90, 50, 100, 0);

        $this->assertCount(2, $path->getOperations());
    }

    public function testPathQuadraticCurveTo(): void
    {
        $path = new Path();
        $path->moveTo(0, 0);
        $path->quadraticCurveTo(50, 50, 100, 0);

        // Quadratic is converted to cubic internally
        $this->assertCount(2, $path->getOperations());
    }

    public function testPathClose(): void
    {
        $path = new Path();
        $path->moveTo(0, 0);
        $path->lineTo(100, 0);
        $path->lineTo(100, 100);
        $path->close();

        $this->assertTrue($path->isClosed());
    }

    public function testPathArc(): void
    {
        $path = new Path();
        $path->arc(50, 50, 25, 0, 90);

        $this->assertGreaterThan(0, count($path->getOperations()));
    }

    public function testPathBoundingBox(): void
    {
        $path = new Path();
        $path->moveTo(10, 20);
        $path->lineTo(100, 150);

        $bounds = $path->getBoundingBox();

        $this->assertSame(10.0, $bounds['x']);
        $this->assertSame(20.0, $bounds['y']);
        $this->assertSame(90.0, $bounds['width']);
        $this->assertSame(130.0, $bounds['height']);
    }

    public function testPathBoundingBoxEmpty(): void
    {
        $path = new Path();
        $bounds = $path->getBoundingBox();

        $this->assertNull($bounds);
    }

    public function testPathTranslate(): void
    {
        $path = new Path();
        $path->moveTo(0, 0);
        $path->lineTo(100, 100);

        $translated = $path->translate(50, 50);
        $bounds = $translated->getBoundingBox();

        $this->assertSame(50.0, $bounds['x']);
        $this->assertSame(50.0, $bounds['y']);
    }

    public function testPathScale(): void
    {
        $path = new Path();
        $path->moveTo(0, 0);
        $path->lineTo(100, 100);

        $scaled = $path->scale(2, 2);
        $bounds = $scaled->getBoundingBox();

        $this->assertSame(200.0, $bounds['width']);
        $this->assertSame(200.0, $bounds['height']);
    }

    public function testPathApplyTo(): void
    {
        $path = new Path();
        $path->moveTo(0, 0);
        $path->lineTo(100, 100);

        $stream = new ContentStream();
        $path->applyTo($stream);

        $content = $stream->toString();
        $this->assertStringContainsString('m', $content);
        $this->assertStringContainsString('l', $content);
    }

    public function testPathApplyToWithClose(): void
    {
        $path = new Path();
        $path->moveTo(0, 0);
        $path->lineTo(100, 0);
        $path->lineTo(100, 100);
        $path->close();

        $stream = new ContentStream();
        $path->applyTo($stream);

        $content = $stream->toString();
        $this->assertStringContainsString('h', $content);
    }

    // Shape Tests

    public function testShapeRectangle(): void
    {
        $shape = Shape::rectangle(10, 20, 100, 50);
        $path = $shape->getPath();

        $this->assertNotNull($path);
        $this->assertTrue($path->isClosed());
    }

    public function testShapeRoundedRectangle(): void
    {
        $shape = Shape::roundedRectangle(10, 20, 100, 50, 5);

        $path = $shape->getPath();
        $this->assertNotNull($path);
    }

    public function testShapeCircle(): void
    {
        $shape = Shape::circle(50, 50, 25);

        $path = $shape->getPath();
        $bounds = $path->getBoundingBox();

        $this->assertEqualsWithDelta(50.0, $bounds['width'], 1);
        $this->assertEqualsWithDelta(50.0, $bounds['height'], 1);
    }

    public function testShapeEllipse(): void
    {
        $shape = Shape::ellipse(50, 50, 40, 20);

        $path = $shape->getPath();
        $bounds = $path->getBoundingBox();

        $this->assertEqualsWithDelta(80.0, $bounds['width'], 1);
        $this->assertEqualsWithDelta(40.0, $bounds['height'], 1);
    }

    public function testShapeLine(): void
    {
        $shape = Shape::line(0, 0, 100, 100);

        $path = $shape->getPath();
        $this->assertGreaterThan(0, count($path->getOperations()));
    }

    public function testShapePolygon(): void
    {
        $points = [[0, 0], [100, 0], [100, 100], [0, 100]];
        $shape = Shape::polygon($points);

        $path = $shape->getPath();
        $this->assertTrue($path->isClosed());
    }

    public function testShapePolygonTooFewPoints(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Polygon requires at least 3 points');

        $points = [[0, 0], [100, 0]];
        Shape::polygon($points);
    }

    public function testShapeRegularPolygon(): void
    {
        // Hexagon
        $shape = Shape::regularPolygon(50, 50, 30, 6);

        $path = $shape->getPath();
        $this->assertTrue($path->isClosed());
    }

    public function testShapeRegularPolygonTooFewSides(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Polygon requires at least 3 sides');

        Shape::regularPolygon(50, 50, 30, 2);
    }

    public function testShapeStar(): void
    {
        $shape = Shape::star(50, 50, 30, 15, 5);

        $path = $shape->getPath();
        $this->assertTrue($path->isClosed());
    }

    public function testShapeStarTooFewPoints(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Star requires at least 3 points');

        Shape::star(50, 50, 30, 15, 2);
    }

    public function testShapeFillColor(): void
    {
        $shape = Shape::rectangle(0, 0, 100, 100);
        $shape->fill(RgbColor::red());

        $this->assertTrue($shape->getFillColor()->equals(RgbColor::red()));
    }

    public function testShapeStrokeColor(): void
    {
        $shape = Shape::rectangle(0, 0, 100, 100);
        $shape->stroke(RgbColor::blue());

        $this->assertTrue($shape->getStrokeColor()->equals(RgbColor::blue()));
    }

    public function testShapeStrokeWidth(): void
    {
        $shape = Shape::rectangle(0, 0, 100, 100);
        $shape->setStrokeWidth(2.5);

        $this->assertSame(2.5, $shape->getStrokeWidth());
    }

    public function testShapeLineCap(): void
    {
        $shape = Shape::line(0, 0, 100, 100);
        $shape->setLineCap(Shape::CAP_ROUND);

        // Line cap constants
        $this->assertSame(0, Shape::CAP_BUTT);
        $this->assertSame(1, Shape::CAP_ROUND);
        $this->assertSame(2, Shape::CAP_SQUARE);
    }

    public function testShapeLineJoin(): void
    {
        $shape = Shape::rectangle(0, 0, 100, 100);
        $shape->setLineJoin(Shape::JOIN_ROUND);

        // Line join constants
        $this->assertSame(0, Shape::JOIN_MITER);
        $this->assertSame(1, Shape::JOIN_ROUND);
        $this->assertSame(2, Shape::JOIN_BEVEL);
    }

    public function testShapeDashPattern(): void
    {
        $shape = Shape::line(0, 0, 100, 100);
        $result = $shape->setDashPattern([5.0, 3.0], 0);

        // Returns self for fluent interface
        $this->assertSame($shape, $result);
    }

    public function testShapeRenderWithFill(): void
    {
        $shape = Shape::rectangle(10, 20, 100, 50);
        $shape->fill(RgbColor::red());

        $stream = new ContentStream();
        $shape->render($stream);

        $content = $stream->toString();
        $this->assertStringContainsString('rg', $content); // fill color
        $this->assertStringContainsString('f', $content); // fill
    }

    public function testShapeRenderWithStroke(): void
    {
        $shape = Shape::rectangle(10, 20, 100, 50);
        $shape->stroke(RgbColor::black());

        $stream = new ContentStream();
        $shape->render($stream);

        $content = $stream->toString();
        $this->assertStringContainsString('RG', $content); // stroke color
        $this->assertStringContainsString('S', $content); // stroke
    }

    public function testShapeRenderWithFillAndStroke(): void
    {
        $shape = Shape::rectangle(10, 20, 100, 50);
        $shape->fill(RgbColor::red());
        $shape->stroke(RgbColor::black());

        $stream = new ContentStream();
        $shape->render($stream);

        $content = $stream->toString();
        $this->assertStringContainsString('rg', $content); // fill color
        $this->assertStringContainsString('RG', $content); // stroke color
        $this->assertStringContainsString('B', $content); // fill and stroke
    }

    public function testShapeRenderNoColorNoOutput(): void
    {
        $shape = Shape::rectangle(10, 20, 100, 50);
        // No fill or stroke color set

        $stream = new ContentStream();
        $shape->render($stream);

        // Should not output anything
        $this->assertSame('', $stream->toString());
    }

    public function testShapeFromPath(): void
    {
        $path = Path::create()
            ->moveTo(0, 0)
            ->lineTo(100, 0)
            ->lineTo(50, 100)
            ->close();

        $shape = Shape::fromPath($path);

        $this->assertSame($path, $shape->getPath());
    }

    // Canvas Tests

    public function testCanvasCreation(): void
    {
        $canvas = new Canvas(595, 842); // A4 size

        $this->assertSame(595.0, $canvas->getWidth());
        $this->assertSame(842.0, $canvas->getHeight());
    }

    public function testCanvasCreate(): void
    {
        $canvas = Canvas::create(612, 792); // Letter size

        $this->assertSame(612.0, $canvas->getWidth());
        $this->assertSame(792.0, $canvas->getHeight());
    }

    public function testCanvasGetContentStream(): void
    {
        $canvas = new Canvas(595, 842);

        $stream = $canvas->getContentStream();
        $this->assertInstanceOf(ContentStream::class, $stream);
    }

    public function testCanvasSaveRestore(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->save();
        $canvas->restore();

        $content = $canvas->toString();
        $this->assertStringContainsString('q', $content);
        $this->assertStringContainsString('Q', $content);
    }

    public function testCanvasTranslate(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->translate(100, 50);

        $content = $canvas->toString();
        $this->assertStringContainsString('cm', $content);
    }

    public function testCanvasScale(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->scale(2, 2);

        $content = $canvas->toString();
        $this->assertStringContainsString('cm', $content);
    }

    public function testCanvasRotate(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->rotate(45);

        $content = $canvas->toString();
        $this->assertStringContainsString('cm', $content);
    }

    public function testCanvasSetFillColor(): void
    {
        $canvas = new Canvas(595, 842);
        $result = $canvas->setFillColor(RgbColor::red());

        // Returns self for fluent interface
        $this->assertSame($canvas, $result);
    }

    public function testCanvasSetStrokeColor(): void
    {
        $canvas = new Canvas(595, 842);
        $result = $canvas->setStrokeColor(RgbColor::blue());

        $this->assertSame($canvas, $result);
    }

    public function testCanvasSetColor(): void
    {
        $canvas = new Canvas(595, 842);
        $result = $canvas->setColor(RgbColor::green());

        $this->assertSame($canvas, $result);
    }

    public function testCanvasSetLineWidth(): void
    {
        $canvas = new Canvas(595, 842);
        $result = $canvas->setLineWidth(3);

        $this->assertSame($canvas, $result);
    }

    public function testCanvasLine(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->line(0, 0, 100, 100);

        $content = $canvas->toString();
        $this->assertStringContainsString('m', $content);
        $this->assertStringContainsString('l', $content);
        $this->assertStringContainsString('S', $content);
    }

    public function testCanvasRect(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->rect(10, 20, 100, 50);

        $content = $canvas->toString();
        // Shape is rendered through the path
        $this->assertGreaterThan(0, strlen($content));
    }

    public function testCanvasFillRect(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->fillRect(10, 20, 100, 50);

        $content = $canvas->toString();
        $this->assertStringContainsString('f', $content);
    }

    public function testCanvasStrokeRect(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->strokeRect(10, 20, 100, 50);

        $content = $canvas->toString();
        $this->assertStringContainsString('S', $content);
    }

    public function testCanvasCircle(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->circle(50, 50, 25);

        $content = $canvas->toString();
        // Circle is drawn using bezier curves
        $this->assertStringContainsString('c', $content);
    }

    public function testCanvasEllipse(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->ellipse(50, 50, 40, 20);

        $content = $canvas->toString();
        $this->assertStringContainsString('c', $content);
    }

    public function testCanvasPolygon(): void
    {
        $canvas = new Canvas(595, 842);
        $points = [[0, 0], [100, 0], [100, 100]];

        $canvas->polygon($points);

        $content = $canvas->toString();
        $this->assertStringContainsString('m', $content);
        $this->assertStringContainsString('l', $content);
    }

    public function testCanvasRoundedRect(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->roundedRect(10, 20, 100, 50, 5);

        $content = $canvas->toString();
        $this->assertGreaterThan(0, strlen($content));
    }

    public function testCanvasShape(): void
    {
        $canvas = new Canvas(595, 842);

        $shape = Shape::rectangle(10, 20, 100, 50);
        $shape->fill(RgbColor::blue());

        $canvas->shape($shape);

        $content = $canvas->toString();
        $this->assertStringContainsString('f', $content);
    }

    public function testCanvasPath(): void
    {
        $canvas = new Canvas(595, 842);

        $path = Path::create()
            ->moveTo(0, 0)
            ->lineTo(100, 100);

        $canvas->path($path, false, true);

        $content = $canvas->toString();
        $this->assertStringContainsString('S', $content);
    }

    public function testCanvasText(): void
    {
        $canvas = new Canvas(595, 842);
        $font = Type1Font::helvetica();
        $canvas->setFont($font, 12);

        $canvas->text('Hello', 100, 200);

        $content = $canvas->toString();
        $this->assertStringContainsString('BT', $content);
        $this->assertStringContainsString('ET', $content);
        $this->assertStringContainsString('Hello', $content);
    }

    public function testCanvasClipRect(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->clipRect(0, 0, 100, 100);

        $content = $canvas->toString();
        $this->assertStringContainsString('re', $content);
        $this->assertStringContainsString('W', $content); // clip
    }

    public function testCanvasClipCircle(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->clipCircle(50, 50, 25);

        $content = $canvas->toString();
        $this->assertStringContainsString('W', $content); // clip
    }

    public function testCanvasImage(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->image('Im1', 100, 200, 150, 100);

        $content = $canvas->toString();
        $this->assertStringContainsString('/Im1 Do', $content);
    }

    public function testCanvasRaw(): void
    {
        $canvas = new Canvas(595, 842);

        $canvas->raw('% Custom content');

        $content = $canvas->toString();
        $this->assertStringContainsString('% Custom content', $content);
    }

    public function testCanvasFluentInterface(): void
    {
        $canvas = new Canvas(595, 842);

        $result = $canvas
            ->save()
            ->setFillColor(RgbColor::red())
            ->setStrokeColor(RgbColor::black())
            ->setLineWidth(2)
            ->translate(50, 50)
            ->rect(0, 0, 100, 100, true, true)
            ->restore();

        $this->assertSame($canvas, $result);
    }
}
