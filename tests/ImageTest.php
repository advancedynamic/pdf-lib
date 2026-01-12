<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PdfLib\Content\Image\Image;
use PdfLib\Content\Image\ImageFactory;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase
{
    // Image Tests

    public function testImageFromRawData(): void
    {
        // Create 2x2 RGB image
        $data = str_repeat("\xFF\x00\x00", 4); // 4 red pixels
        $image = Image::fromRawData($data, 2, 2, Image::COLORSPACE_RGB, 8);

        $this->assertSame(2, $image->getWidth());
        $this->assertSame(2, $image->getHeight());
        $this->assertSame(8, $image->getBitsPerComponent());
        $this->assertSame(Image::COLORSPACE_RGB, $image->getColorSpace());
    }

    public function testImageAspectRatio(): void
    {
        $data = str_repeat("\x00", 200 * 100 * 3); // 200x100 RGB
        $image = Image::fromRawData($data, 200, 100, Image::COLORSPACE_RGB, 8);

        $this->assertSame(2.0, $image->getAspectRatio());
    }

    public function testImageColorSpaces(): void
    {
        $this->assertSame('DeviceGray', Image::COLORSPACE_GRAY);
        $this->assertSame('DeviceRGB', Image::COLORSPACE_RGB);
        $this->assertSame('DeviceCMYK', Image::COLORSPACE_CMYK);
    }

    public function testImageFilters(): void
    {
        $this->assertSame('', Image::FILTER_NONE);
        $this->assertSame('FlateDecode', Image::FILTER_FLATE);
        $this->assertSame('DCTDecode', Image::FILTER_DCT);
        $this->assertSame('ASCII85Decode', Image::FILTER_ASCII85);
    }

    public function testImageToPdfStream(): void
    {
        $data = str_repeat("\x80", 10 * 10 * 3);
        $image = Image::fromRawData($data, 10, 10, Image::COLORSPACE_RGB, 8);

        $stream = $image->toPdfStream();

        $dict = $stream->getDictionary();
        $this->assertSame('XObject', $dict->get('Type')->getValue());
        $this->assertSame('Image', $dict->get('Subtype')->getValue());
        $this->assertSame(10, $dict->get('Width')->getValue());
        $this->assertSame(10, $dict->get('Height')->getValue());
        $this->assertSame(8, $dict->get('BitsPerComponent')->getValue());
        $this->assertSame('DeviceRGB', $dict->get('ColorSpace')->getValue());
    }

    public function testImageNoSoftMask(): void
    {
        $data = str_repeat("\x00", 10 * 10 * 3);
        $image = Image::fromRawData($data, 10, 10, Image::COLORSPACE_RGB, 8);

        $this->assertFalse($image->hasSoftMask());
        $this->assertNull($image->getSoftMaskData());
        $this->assertNull($image->createSoftMaskStream());
    }

    public function testImageGrayscale(): void
    {
        $data = str_repeat("\x80", 5 * 5);
        $image = Image::fromRawData($data, 5, 5, Image::COLORSPACE_GRAY, 8);

        $this->assertSame(Image::COLORSPACE_GRAY, $image->getColorSpace());
    }

    // ImageFactory Tests

    public function testImageFactoryDetectJpeg(): void
    {
        // JPEG magic bytes: FF D8 FF
        $jpegHeader = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00";
        $type = ImageFactory::detectImageType($jpegHeader);

        $this->assertSame('jpeg', $type);
    }

    public function testImageFactoryDetectPng(): void
    {
        // PNG magic bytes
        $pngHeader = "\x89PNG\r\n\x1a\n";
        $type = ImageFactory::detectImageType($pngHeader);

        $this->assertSame('png', $type);
    }

    public function testImageFactoryDetectGif(): void
    {
        $gif89a = "GIF89a\x00\x00";
        $type = ImageFactory::detectImageType($gif89a);

        $this->assertSame('gif', $type);

        $gif87a = "GIF87a\x00\x00";
        $type = ImageFactory::detectImageType($gif87a);

        $this->assertSame('gif', $type);
    }

    public function testImageFactoryDetectBmp(): void
    {
        $bmpHeader = "BM\x00\x00\x00\x00\x00\x00";
        $type = ImageFactory::detectImageType($bmpHeader);

        $this->assertSame('bmp', $type);
    }

    public function testImageFactoryDetectTiffLittleEndian(): void
    {
        $tiffLE = "II\x2A\x00\x00\x00\x00\x00";
        $type = ImageFactory::detectImageType($tiffLE);

        $this->assertSame('tiff', $type);
    }

    public function testImageFactoryDetectTiffBigEndian(): void
    {
        $tiffBE = "MM\x00\x2A\x00\x00\x00\x00";
        $type = ImageFactory::detectImageType($tiffBE);

        $this->assertSame('tiff', $type);
    }

    public function testImageFactoryDetectWebP(): void
    {
        $webpHeader = "RIFF\x00\x00\x00\x00WEBP";
        $type = ImageFactory::detectImageType($webpHeader);

        $this->assertSame('webp', $type);
    }

    public function testImageFactoryDetectUnknown(): void
    {
        $unknown = "\x00\x00\x00\x00\x00\x00\x00\x00";
        $type = ImageFactory::detectImageType($unknown);

        $this->assertSame('unknown', $type);
    }

    public function testImageFactoryDetectTooShort(): void
    {
        $short = "\xFF\xD8";
        $type = ImageFactory::detectImageType($short);

        $this->assertSame('unknown', $type);
    }

    public function testImageFactorySupportedFormats(): void
    {
        $formats = ImageFactory::getSupportedFormats();

        $this->assertContains('jpeg', $formats);
        $this->assertContains('jpg', $formats);
        $this->assertContains('png', $formats);
        $this->assertCount(3, $formats);
    }

    public function testImageFactoryFromFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image file not found');

        ImageFactory::fromFile('/nonexistent/path/to/image.jpg');
    }

    public function testImageFactoryFromStringUnsupported(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported image format');

        // Pass an unsupported format (GIF)
        $gifData = "GIF89a\x01\x00\x01\x00\x00\xFF\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x00;";
        ImageFactory::fromString($gifData);
    }

    public function testImageFactoryPlaceholder(): void
    {
        $image = ImageFactory::placeholder(100, 50, 200, 100, 50);

        $this->assertSame(100, $image->getWidth());
        $this->assertSame(50, $image->getHeight());
        $this->assertSame(Image::COLORSPACE_RGB, $image->getColorSpace());
    }

    public function testImageFactoryPlaceholderDefaultColor(): void
    {
        $image = ImageFactory::placeholder(10, 10);

        $this->assertSame(10, $image->getWidth());
        $this->assertSame(10, $image->getHeight());
    }

    /**
     * Test JPEG filter is set correctly.
     */
    public function testJpegImageFilter(): void
    {
        // Skip if GD extension is not available
        if (!function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD extension not available');
        }

        // Create a small valid JPEG using GD
        $gd = imagecreatetruecolor(2, 2);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 255, 0, 0));

        ob_start();
        imagejpeg($gd, null, 100);
        $jpegData = ob_get_clean();
        imagedestroy($gd);

        $image = Image::fromJpegData($jpegData);

        $this->assertSame(Image::FILTER_DCT, $image->getFilter());
        $this->assertSame(2, $image->getWidth());
        $this->assertSame(2, $image->getHeight());
    }

    /**
     * Test PNG parsing with GD.
     */
    public function testPngImageWithGd(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        // Create a small PNG using GD
        $gd = imagecreatetruecolor(4, 4);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 0, 0, 255));

        ob_start();
        imagepng($gd);
        $pngData = ob_get_clean();
        imagedestroy($gd);

        $image = Image::fromPngData($pngData);

        $this->assertSame(4, $image->getWidth());
        $this->assertSame(4, $image->getHeight());
        $this->assertSame(Image::FILTER_FLATE, $image->getFilter());
    }

    /**
     * Test PNG with alpha channel.
     */
    public function testPngWithAlpha(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        // Create a PNG with transparency
        $gd = imagecreatetruecolor(4, 4);
        imagesavealpha($gd, true);
        imagealphablending($gd, false);

        $transparent = imagecolorallocatealpha($gd, 255, 0, 0, 64);
        imagefill($gd, 0, 0, $transparent);

        ob_start();
        imagepng($gd);
        $pngData = ob_get_clean();
        imagedestroy($gd);

        $image = Image::fromPngData($pngData);

        $this->assertSame(4, $image->getWidth());
        $this->assertSame(4, $image->getHeight());
        $this->assertTrue($image->hasSoftMask());
        $this->assertNotNull($image->getSoftMaskData());
    }

    /**
     * Test ImageFactory::fromGd.
     */
    public function testImageFactoryFromGd(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $gd = imagecreatetruecolor(8, 8);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 0, 255, 0));

        $image = ImageFactory::fromGd($gd, 'png');
        imagedestroy($gd);

        $this->assertSame(8, $image->getWidth());
        $this->assertSame(8, $image->getHeight());
    }

    /**
     * Test ImageFactory::fromGd with JPEG.
     */
    public function testImageFactoryFromGdJpeg(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $gd = imagecreatetruecolor(8, 8);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 128, 128, 128));

        $image = ImageFactory::fromGd($gd, 'jpeg');
        imagedestroy($gd);

        $this->assertSame(8, $image->getWidth());
        $this->assertSame(Image::FILTER_DCT, $image->getFilter());
    }

    /**
     * Test ImageFactory::fromGd with unsupported format.
     */
    public function testImageFactoryFromGdUnsupportedFormat(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $gd = imagecreatetruecolor(2, 2);
        $exception = null;

        try {
            ImageFactory::fromGd($gd, 'bmp');
        } catch (\InvalidArgumentException $e) {
            $exception = $e;
        }

        imagedestroy($gd);

        $this->assertNotNull($exception);
        $this->assertStringContainsString('Unsupported GD format', $exception->getMessage());
    }

    public function testImageGetData(): void
    {
        $originalData = str_repeat("\xFF", 4 * 4 * 3);
        $image = Image::fromRawData($originalData, 4, 4, Image::COLORSPACE_RGB, 8);

        $data = $image->getData();
        $this->assertNotEmpty($data);
    }

    public function testImageGetFilter(): void
    {
        $data = str_repeat("\x00", 4 * 4 * 3);
        $image = Image::fromRawData($data, 4, 4, Image::COLORSPACE_RGB, 8);

        // Raw data has no filter
        $this->assertSame(Image::FILTER_NONE, $image->getFilter());
    }

    // ===== Integration Tests - PDF Output =====

    /**
     * Ensure target directory exists.
     */
    private function ensureTargetDirectory(): string
    {
        $targetDir = __DIR__ . '/target';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        return $targetDir;
    }

    /**
     * Test creating a PDF with a raw RGB image.
     */
    public function testPdfWithRawImage(): void
    {
        $targetDir = $this->ensureTargetDirectory();

        // Create a 100x100 gradient image
        $width = 100;
        $height = 100;
        $data = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $r = (int)(255 * $x / $width);
                $g = (int)(255 * $y / $height);
                $b = 128;
                $data .= chr($r) . chr($g) . chr($b);
            }
        }

        $image = Image::fromRawData($data, $width, $height, Image::COLORSPACE_RGB, 8);

        $doc = new \PdfLib\Document\PdfDocument();
        $page = new \PdfLib\Page\Page(\PdfLib\Page\PageSize::a4());
        $page->drawImage($image, 50, 700, 200, 200);
        $page->addText('Raw RGB Image Test', 50, 650);
        $doc->addPageObject($page);

        $outputPath = $targetDir . '/image_raw_rgb.pdf';
        $doc->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));

        // Verify PDF header
        $content = file_get_contents($outputPath);
        $this->assertStringStartsWith('%PDF-', $content);
    }

    /**
     * Test creating a PDF with a placeholder image.
     */
    public function testPdfWithPlaceholderImage(): void
    {
        $targetDir = $this->ensureTargetDirectory();

        // Create placeholder images with different colors
        $redImage = ImageFactory::placeholder(150, 100, 255, 0, 0);
        $greenImage = ImageFactory::placeholder(150, 100, 0, 255, 0);
        $blueImage = ImageFactory::placeholder(150, 100, 0, 0, 255);

        $doc = new \PdfLib\Document\PdfDocument();
        $page = new \PdfLib\Page\Page(\PdfLib\Page\PageSize::a4());

        $page->addText('Placeholder Images Test', 50, 800);
        $page->drawImage($redImage, 50, 650, 150, 100);
        $page->addText('Red', 50, 630);

        $page->drawImage($greenImage, 220, 650, 150, 100);
        $page->addText('Green', 220, 630);

        $page->drawImage($blueImage, 390, 650, 150, 100);
        $page->addText('Blue', 390, 630);

        $doc->addPageObject($page);

        $outputPath = $targetDir . '/image_placeholders.pdf';
        $doc->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    /**
     * Test creating a PDF with a JPEG image from GD.
     */
    public function testPdfWithJpegFromGd(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $targetDir = $this->ensureTargetDirectory();

        // Create a colorful image with GD
        $gd = imagecreatetruecolor(200, 150);

        // Draw a gradient background
        for ($x = 0; $x < 200; $x++) {
            $color = imagecolorallocate($gd, $x, 100, 255 - $x);
            imageline($gd, $x, 0, $x, 150, $color);
        }

        // Draw some shapes
        $white = imagecolorallocate($gd, 255, 255, 255);
        imagefilledellipse($gd, 100, 75, 80, 60, $white);

        $black = imagecolorallocate($gd, 0, 0, 0);
        imagerectangle($gd, 20, 20, 180, 130, $black);

        $image = ImageFactory::fromGd($gd, 'jpeg');
        imagedestroy($gd);

        $doc = new \PdfLib\Document\PdfDocument();
        $page = new \PdfLib\Page\Page(\PdfLib\Page\PageSize::a4());

        $page->addText('JPEG Image from GD Test', 50, 800);
        $page->drawImage($image, 50, 600, 300, 225);

        $doc->addPageObject($page);

        $outputPath = $targetDir . '/image_jpeg_gd.pdf';
        $doc->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    /**
     * Test creating a PDF with a PNG image from GD.
     */
    public function testPdfWithPngFromGd(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $targetDir = $this->ensureTargetDirectory();

        // Create PNG with transparency
        $gd = imagecreatetruecolor(200, 200);
        imagesavealpha($gd, true);
        imagealphablending($gd, false);

        // Fill with transparent background
        $transparent = imagecolorallocatealpha($gd, 0, 0, 0, 127);
        imagefill($gd, 0, 0, $transparent);

        // Draw a semi-transparent red circle
        $red = imagecolorallocatealpha($gd, 255, 0, 0, 50);
        imagefilledellipse($gd, 100, 100, 150, 150, $red);

        // Draw a semi-transparent blue circle overlapping
        $blue = imagecolorallocatealpha($gd, 0, 0, 255, 50);
        imagefilledellipse($gd, 130, 100, 150, 150, $blue);

        $image = ImageFactory::fromGd($gd, 'png');
        imagedestroy($gd);

        $doc = new \PdfLib\Document\PdfDocument();
        $page = new \PdfLib\Page\Page(\PdfLib\Page\PageSize::a4());

        $page->addText('PNG Image with Alpha from GD Test', 50, 800);
        $page->drawImage($image, 50, 550, 200, 200);

        $doc->addPageObject($page);

        $outputPath = $targetDir . '/image_png_alpha_gd.pdf';
        $doc->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    /**
     * Test creating a PDF with grayscale image.
     */
    public function testPdfWithGrayscaleImage(): void
    {
        $targetDir = $this->ensureTargetDirectory();

        // Create a grayscale gradient
        $width = 256;
        $height = 50;
        $data = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $data .= chr($x); // 0-255 grayscale gradient
            }
        }

        $image = Image::fromRawData($data, $width, $height, Image::COLORSPACE_GRAY, 8);

        $doc = new \PdfLib\Document\PdfDocument();
        $page = new \PdfLib\Page\Page(\PdfLib\Page\PageSize::a4());

        $page->addText('Grayscale Image Test', 50, 800);
        $page->drawImage($image, 50, 700, 512, 100);
        $page->addText('0 (black)', 50, 680);
        $page->addText('255 (white)', 500, 680);

        $doc->addPageObject($page);

        $outputPath = $targetDir . '/image_grayscale.pdf';
        $doc->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    /**
     * Test creating a PDF with multiple images on same page.
     */
    public function testPdfWithMultipleImages(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $targetDir = $this->ensureTargetDirectory();

        // Create several different images
        $images = [];
        $colors = [
            ['r' => 255, 'g' => 100, 'b' => 100, 'name' => 'Coral'],
            ['r' => 100, 'g' => 255, 'b' => 100, 'name' => 'Lime'],
            ['r' => 100, 'g' => 100, 'b' => 255, 'name' => 'Sky'],
            ['r' => 255, 'g' => 255, 'b' => 100, 'name' => 'Yellow'],
            ['r' => 255, 'g' => 100, 'b' => 255, 'name' => 'Pink'],
            ['r' => 100, 'g' => 255, 'b' => 255, 'name' => 'Cyan'],
        ];

        foreach ($colors as $color) {
            $gd = imagecreatetruecolor(80, 80);
            $bgColor = imagecolorallocate($gd, $color['r'], $color['g'], $color['b']);
            imagefill($gd, 0, 0, $bgColor);

            // Add a border
            $borderColor = imagecolorallocate($gd, 0, 0, 0);
            imagerectangle($gd, 0, 0, 79, 79, $borderColor);

            $images[] = [
                'image' => ImageFactory::fromGd($gd, 'png'),
                'name' => $color['name']
            ];
            imagedestroy($gd);
        }

        $doc = new \PdfLib\Document\PdfDocument();
        $page = new \PdfLib\Page\Page(\PdfLib\Page\PageSize::a4());

        $page->addText('Multiple Images Test', 50, 800);

        $x = 50;
        $y = 700;
        $col = 0;
        foreach ($images as $item) {
            $page->drawImage($item['image'], $x, $y, 80, 80);
            $page->addText($item['name'], $x, $y - 15);

            $x += 100;
            $col++;
            if ($col >= 5) {
                $col = 0;
                $x = 50;
                $y -= 120;
            }
        }

        $doc->addPageObject($page);

        $outputPath = $targetDir . '/image_multiple.pdf';
        $doc->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    /**
     * Test creating a PDF with image scaling (aspect ratio).
     */
    public function testPdfWithImageScaling(): void
    {
        $targetDir = $this->ensureTargetDirectory();

        // Create a wide image (300x100)
        $width = 300;
        $height = 100;
        $data = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Create a checkerboard pattern
                $isWhite = (($x / 20) + ($y / 20)) % 2 < 1;
                $val = $isWhite ? 255 : 0;
                $data .= chr($val) . chr($val) . chr($val);
            }
        }

        $image = Image::fromRawData($data, $width, $height, Image::COLORSPACE_RGB, 8);

        $doc = new \PdfLib\Document\PdfDocument();
        $page = new \PdfLib\Page\Page(\PdfLib\Page\PageSize::a4());

        $page->addText('Image Scaling Test', 50, 800);

        // Original size
        $page->addText('Original (300x100):', 50, 750);
        $page->drawImage($image, 50, 640, 300, 100);

        // Scaled down maintaining aspect
        $page->addText('Scaled (150x50):', 50, 620);
        $page->drawImage($image, 50, 560, 150, 50);

        // Stretched
        $page->addText('Stretched (200x200):', 50, 530);
        $page->drawImage($image, 50, 320, 200, 200);

        $doc->addPageObject($page);

        $outputPath = $targetDir . '/image_scaling.pdf';
        $doc->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }
}
