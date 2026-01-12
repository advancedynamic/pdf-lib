<?php

declare(strict_types=1);

namespace PdfLib\Tests\Form;

use PHPUnit\Framework\TestCase;
use PdfLib\Form\FormParser;
use PdfLib\Form\TextField;
use PdfLib\Form\CheckboxField;
use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;

/**
 * Tests for form field coordinate extraction.
 *
 * This tests the functionality demonstrated in examples/extract-placeholder-coordinates.php
 * specifically the coordinate extraction APIs.
 */
class FormParserTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = __DIR__ . '/../output';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    // =========================================================================
    // TextField Coordinate Tests (Unit Tests)
    // =========================================================================

    public function testTextFieldCoordinates(): void
    {
        $field = TextField::create('name_field')
            ->setPosition(150.0, 695.0)
            ->setSize(200.0, 20.0);

        $this->assertEquals(150.0, $field->getX());
        $this->assertEquals(695.0, $field->getY());
        $this->assertEquals(200.0, $field->getWidth());
        $this->assertEquals(20.0, $field->getHeight());
    }

    public function testTextFieldRect(): void
    {
        $field = TextField::create('test_field')
            ->setPosition(100.0, 200.0)
            ->setSize(150.0, 25.0);

        $rect = $field->getRect();

        // Rect format: [x1, y1, x2, y2]
        $this->assertEquals([100.0, 200.0, 250.0, 225.0], $rect);
    }

    public function testTextFieldPage(): void
    {
        $field = TextField::create('page_test');

        // Default page is 1
        $this->assertEquals(1, $field->getPage());

        $field->setPage(3);
        $this->assertEquals(3, $field->getPage());
    }

    public function testTextFieldType(): void
    {
        $field = TextField::create('type_test');

        // Text fields have type 'Tx'
        $this->assertEquals('Tx', $field->getFieldType());
    }

    public function testCheckboxFieldCoordinates(): void
    {
        $field = CheckboxField::create('agree_checkbox')
            ->setPosition(100.0, 350.0)
            ->setSize(20.0, 20.0);

        $this->assertEquals(100.0, $field->getX());
        $this->assertEquals(350.0, $field->getY());
        $this->assertEquals(20.0, $field->getWidth());
        $this->assertEquals(20.0, $field->getHeight());
    }

    public function testCheckboxFieldType(): void
    {
        $field = CheckboxField::create('checkbox_test');

        // Checkbox has type 'Btn'
        $this->assertEquals('Btn', $field->getFieldType());
    }

    public function testExtractFieldCoordinatesAsArray(): void
    {
        // Simulate extracting coordinates from fields
        $fields = [
            'name_field' => TextField::create('name_field')
                ->setPosition(150.0, 695.0)
                ->setSize(200.0, 20.0),
            'signature_placeholder' => TextField::create('signature_placeholder')
                ->setPosition(100.0, 400.0)
                ->setSize(200.0, 80.0),
            'qr_placeholder' => TextField::create('qr_placeholder')
                ->setPosition(450.0, 50.0)
                ->setSize(100.0, 100.0),
        ];

        $coordinates = [];
        foreach ($fields as $name => $field) {
            $rect = $field->getRect();
            $coordinates[$name] = [
                'x' => $rect[0],
                'y' => $rect[1],
                'width' => $rect[2] - $rect[0],
                'height' => $rect[3] - $rect[1],
                'page' => $field->getPage(),
                'type' => $field->getFieldType(),
            ];
        }

        // Verify structure
        $this->assertArrayHasKey('qr_placeholder', $coordinates);
        $this->assertEquals(450.0, $coordinates['qr_placeholder']['x']);
        $this->assertEquals(50.0, $coordinates['qr_placeholder']['y']);
        $this->assertEquals(100.0, $coordinates['qr_placeholder']['width']);
        $this->assertEquals(100.0, $coordinates['qr_placeholder']['height']);
        $this->assertEquals(1, $coordinates['qr_placeholder']['page']);
        $this->assertEquals('Tx', $coordinates['qr_placeholder']['type']);
    }

    public function testDetailedFieldInformation(): void
    {
        $field = TextField::create('detailed_field')
            ->setPosition(50.0, 100.0)
            ->setSize(200.0, 30.0)
            ->setValue('Test Value')
            ->setTooltip('Enter your name')
            ->setRequired();

        $info = [
            'name' => $field->getName(),
            'type' => $field->getFieldType(),
            'x' => $field->getX(),
            'y' => $field->getY(),
            'width' => $field->getWidth(),
            'height' => $field->getHeight(),
            'rect' => $field->getRect(),
            'page' => $field->getPage(),
            'value' => $field->getValue(),
            'required' => $field->isRequired(),
            'readOnly' => $field->isReadOnly(),
        ];

        $this->assertEquals('detailed_field', $info['name']);
        $this->assertEquals('Tx', $info['type']);
        $this->assertEquals(50.0, $info['x']);
        $this->assertEquals(100.0, $info['y']);
        $this->assertEquals(200.0, $info['width']);
        $this->assertEquals(30.0, $info['height']);
        $this->assertEquals(1, $info['page']);
        $this->assertEquals('Test Value', $info['value']);
        $this->assertTrue($info['required']);
        $this->assertFalse($info['readOnly']);
    }

    public function testUseCoordinatesToPlaceContent(): void
    {
        // Simulate placeholder coordinates
        $qrPlaceholder = TextField::create('qr_placeholder')
            ->setPosition(450.0, 50.0)
            ->setSize(100.0, 100.0);

        // Create a new document and place content at those coordinates
        $document = PdfDocument::create();
        $page = new Page(PageSize::a4());

        // Place content at the placeholder coordinates
        $page->addText(
            'QR',
            $qrPlaceholder->getX() + 35,
            $qrPlaceholder->getY() + 45,
            ['fontSize' => 24]
        );

        $document->addPageObject($page);
        $outputPath = $this->outputDir . '/placed-at-coordinates.pdf';
        $document->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    // =========================================================================
    // FormParser Tests (for PDFs without forms)
    // =========================================================================

    public function testFormParserNoFormReturnsEmpty(): void
    {
        // Create a PDF without form fields
        $document = PdfDocument::create();
        $page = new Page(PageSize::a4());
        $page->addText('No forms here', 100, 700);
        $document->addPageObject($page);

        $pdfPath = $this->outputDir . '/no-form.pdf';
        $document->save($pdfPath);

        $parser = FormParser::fromFile($pdfPath);

        $this->assertFalse($parser->hasForm());
        $this->assertEmpty($parser->getFields());
    }

    public function testFormParserFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FormParser::fromFile('/nonexistent/file.pdf');
    }

    // =========================================================================
    // Coordinate Calculation Examples
    // =========================================================================

    public function testCalculateRectFromPosition(): void
    {
        // Given position and size
        $x = 100.0;
        $y = 200.0;
        $width = 150.0;
        $height = 25.0;

        // Calculate rect [x1, y1, x2, y2]
        $rect = [$x, $y, $x + $width, $y + $height];

        $this->assertEquals([100.0, 200.0, 250.0, 225.0], $rect);
    }

    public function testCalculateSizeFromRect(): void
    {
        // Given rect [x1, y1, x2, y2]
        $rect = [100.0, 200.0, 250.0, 225.0];

        // Calculate position and size
        $x = $rect[0];
        $y = $rect[1];
        $width = $rect[2] - $rect[0];
        $height = $rect[3] - $rect[1];

        $this->assertEquals(100.0, $x);
        $this->assertEquals(200.0, $y);
        $this->assertEquals(150.0, $width);
        $this->assertEquals(25.0, $height);
    }

    public function testFieldCoordinatesForImagePlacement(): void
    {
        // Example: Extract coordinates from a placeholder field
        // and use them to place an image at the same position

        $placeholder = TextField::create('logo_placeholder')
            ->setPosition(50.0, 750.0)
            ->setSize(150.0, 50.0);

        // Image placement would use these coordinates:
        $imageX = $placeholder->getX();
        $imageY = $placeholder->getY();
        $imageWidth = $placeholder->getWidth();
        $imageHeight = $placeholder->getHeight();

        $this->assertEquals(50.0, $imageX);
        $this->assertEquals(750.0, $imageY);
        $this->assertEquals(150.0, $imageWidth);
        $this->assertEquals(50.0, $imageHeight);
    }
}
