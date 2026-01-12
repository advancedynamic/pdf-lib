<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PdfLib\Color\CmykColor;
use PdfLib\Color\ColorFactory;
use PdfLib\Color\GrayColor;
use PdfLib\Color\RgbColor;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function testRgbColorFromHex(): void
    {
        $color = RgbColor::fromHex('#FF0000');

        $this->assertEqualsWithDelta(1.0, $color->getRed(), 0.001);
        $this->assertEqualsWithDelta(0.0, $color->getGreen(), 0.001);
        $this->assertEqualsWithDelta(0.0, $color->getBlue(), 0.001);
    }

    public function testRgbColorShortHex(): void
    {
        $color = RgbColor::fromHex('#F00');

        $this->assertEqualsWithDelta(1.0, $color->getRed(), 0.001);
        $this->assertEqualsWithDelta(0.0, $color->getGreen(), 0.001);
        $this->assertEqualsWithDelta(0.0, $color->getBlue(), 0.001);
    }

    public function testRgbColorFromInt(): void
    {
        $color = RgbColor::fromInt(255, 128, 0);

        $this->assertEqualsWithDelta(1.0, $color->getRed(), 0.001);
        $this->assertEqualsWithDelta(0.502, $color->getGreen(), 0.001);
        $this->assertEqualsWithDelta(0.0, $color->getBlue(), 0.001);
    }

    public function testRgbColorToHex(): void
    {
        $color = RgbColor::fromInt(255, 128, 64);
        $hex = $color->toHex();

        $this->assertSame('#FF8040', $hex);
    }

    public function testRgbNamedColors(): void
    {
        $this->assertEqualsWithDelta(0.0, RgbColor::black()->getRed(), 0.001);
        $this->assertEqualsWithDelta(1.0, RgbColor::white()->getRed(), 0.001);
        $this->assertEqualsWithDelta(1.0, RgbColor::red()->getRed(), 0.001);
    }

    public function testRgbToCmyk(): void
    {
        $rgb = RgbColor::red();
        $cmyk = $rgb->toCmyk();

        $this->assertEqualsWithDelta(0.0, $cmyk->getCyan(), 0.001);
        $this->assertEqualsWithDelta(1.0, $cmyk->getMagenta(), 0.001);
        $this->assertEqualsWithDelta(1.0, $cmyk->getYellow(), 0.001);
        $this->assertEqualsWithDelta(0.0, $cmyk->getBlack(), 0.001);
    }

    public function testRgbToGray(): void
    {
        $rgb = RgbColor::white();
        $gray = $rgb->toGray();

        $this->assertEqualsWithDelta(1.0, $gray->getGray(), 0.001);
    }

    public function testCmykColor(): void
    {
        $color = CmykColor::fromPercent(100, 0, 0, 0);

        $this->assertEqualsWithDelta(1.0, $color->getCyan(), 0.001);
        $this->assertEqualsWithDelta(0.0, $color->getMagenta(), 0.001);
    }

    public function testCmykToRgb(): void
    {
        $cmyk = CmykColor::cyan();
        $rgb = $cmyk->toRgb();

        $this->assertEqualsWithDelta(0.0, $rgb->getRed(), 0.001);
        $this->assertEqualsWithDelta(1.0, $rgb->getGreen(), 0.001);
        $this->assertEqualsWithDelta(1.0, $rgb->getBlue(), 0.001);
    }

    public function testGrayColor(): void
    {
        $gray = GrayColor::fromPercent(50);

        $this->assertEqualsWithDelta(0.5, $gray->getGray(), 0.001);
        $this->assertSame(50.0, $gray->toPercent());
    }

    public function testGrayToRgb(): void
    {
        $gray = GrayColor::mediumGray();
        $rgb = $gray->toRgb();

        $this->assertEqualsWithDelta(0.5, $rgb->getRed(), 0.001);
        $this->assertEqualsWithDelta(0.5, $rgb->getGreen(), 0.001);
        $this->assertEqualsWithDelta(0.5, $rgb->getBlue(), 0.001);
    }

    public function testColorFactoryParse(): void
    {
        $red = ColorFactory::parse('#FF0000');
        $this->assertTrue($red->equals(RgbColor::red()));

        $namedBlue = ColorFactory::parse('blue');
        $this->assertEqualsWithDelta(0.0, $namedBlue->toRgb()->getRed(), 0.001);
        $this->assertEqualsWithDelta(0.0, $namedBlue->toRgb()->getGreen(), 0.001);
        $this->assertEqualsWithDelta(1.0, $namedBlue->toRgb()->getBlue(), 0.001);
    }

    public function testColorFactoryRgb(): void
    {
        $color = ColorFactory::rgb(255, 0, 0);
        $this->assertTrue($color->equals(RgbColor::red()));
    }

    public function testColorFactoryCmyk(): void
    {
        $color = ColorFactory::cmyk(100, 0, 0, 0);
        $this->assertEqualsWithDelta(1.0, $color->getCyan(), 0.001);
    }

    public function testRgbGetStrokeOperator(): void
    {
        $color = RgbColor::red();
        $operator = $color->getStrokeOperator();

        $this->assertStringContainsString('RG', $operator);
        $this->assertStringContainsString('1.0000', $operator);
    }

    public function testRgbGetFillOperator(): void
    {
        $color = RgbColor::red();
        $operator = $color->getFillOperator();

        $this->assertStringContainsString('rg', $operator);
    }

    public function testColorMix(): void
    {
        $red = RgbColor::red();
        $white = RgbColor::white();
        $mixed = $red->mix($white, 0.5);

        $this->assertEqualsWithDelta(1.0, $mixed->getRed(), 0.001);
        $this->assertEqualsWithDelta(0.5, $mixed->getGreen(), 0.001);
        $this->assertEqualsWithDelta(0.5, $mixed->getBlue(), 0.001);
    }

    public function testColorComplement(): void
    {
        $red = RgbColor::red();
        $complement = $red->complement();

        $this->assertEqualsWithDelta(0.0, $complement->getRed(), 0.001);
        $this->assertEqualsWithDelta(1.0, $complement->getGreen(), 0.001);
        $this->assertEqualsWithDelta(1.0, $complement->getBlue(), 0.001);
    }
}
