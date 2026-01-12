<?php

declare(strict_types=1);

namespace PdfLib\Page;

use PdfLib\Color\Color;
use PdfLib\Color\RgbColor;
use PdfLib\Content\ContentStream;
use PdfLib\Content\Graphics\Canvas;
use PdfLib\Content\Graphics\Path;
use PdfLib\Content\Graphics\Shape;
use PdfLib\Content\Image\Image;
use PdfLib\Content\Text\Paragraph;
use PdfLib\Content\Text\TextBlock;
use PdfLib\Content\Text\TextStyle;
use PdfLib\Font\Font;
use PdfLib\Font\Type1Font;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfStream;

/**
 * Represents a single PDF page.
 */
final class Page
{
    private PageBox $mediaBox;
    private ?PageBox $cropBox = null;
    private ?PageBox $bleedBox = null;
    private ?PageBox $trimBox = null;
    private ?PageBox $artBox = null;

    private int $rotation = 0;

    private ?PdfDictionary $resources = null;

    /** @var array<int, PdfStream> */
    private array $contentStreams = [];

    /** @var array<string, mixed> */
    private array $annotations = [];

    private ?PdfReference $parentRef = null;

    // Content rendering
    private ?ContentStream $contentStream = null;

    /** @var array<string, Font> */
    private array $fonts = [];

    /** @var array<string, Image> */
    private array $images = [];

    private ?Font $currentFont = null;
    private float $currentFontSize = 12;
    private ?Color $currentFillColor = null;
    private ?Color $currentStrokeColor = null;
    private float $currentLineWidth = 1.0;

    public function __construct(?PageSize $size = null)
    {
        $size = $size ?? PageSize::a4();
        $this->mediaBox = PageBox::fromPageSize($size);
    }

    /**
     * Create a page from a PageSize.
     */
    public static function create(PageSize $size): self
    {
        return new self($size);
    }

    /**
     * Create a page from a PdfDictionary (parsed from existing PDF).
     */
    public static function fromDictionary(PdfDictionary $dict): self
    {
        $page = new self();

        // Get MediaBox
        $mediaBoxObj = $dict->get(PageBox::MEDIA_BOX);
        if ($mediaBoxObj instanceof PdfArray) {
            $page->mediaBox = PageBox::fromPdfArray($mediaBoxObj);
        }

        // Get other boxes
        $cropBoxObj = $dict->get(PageBox::CROP_BOX);
        if ($cropBoxObj instanceof PdfArray) {
            $page->cropBox = PageBox::fromPdfArray($cropBoxObj);
        }

        $bleedBoxObj = $dict->get(PageBox::BLEED_BOX);
        if ($bleedBoxObj instanceof PdfArray) {
            $page->bleedBox = PageBox::fromPdfArray($bleedBoxObj);
        }

        $trimBoxObj = $dict->get(PageBox::TRIM_BOX);
        if ($trimBoxObj instanceof PdfArray) {
            $page->trimBox = PageBox::fromPdfArray($trimBoxObj);
        }

        $artBoxObj = $dict->get(PageBox::ART_BOX);
        if ($artBoxObj instanceof PdfArray) {
            $page->artBox = PageBox::fromPdfArray($artBoxObj);
        }

        // Get rotation
        $rotateObj = $dict->get('Rotate');
        if ($rotateObj instanceof PdfNumber) {
            $page->rotation = $rotateObj->toInt();
        }

        // Get resources
        $resourcesObj = $dict->get('Resources');
        if ($resourcesObj instanceof PdfDictionary) {
            $page->resources = $resourcesObj;
        }

        return $page;
    }

    /**
     * Get MediaBox.
     */
    public function getMediaBox(): PageBox
    {
        return $this->mediaBox;
    }

    /**
     * Set MediaBox.
     */
    public function setMediaBox(PageBox $box): self
    {
        $this->mediaBox = $box;
        return $this;
    }

    /**
     * Get CropBox (defaults to MediaBox).
     */
    public function getCropBox(): PageBox
    {
        return $this->cropBox ?? $this->mediaBox;
    }

    /**
     * Set CropBox.
     */
    public function setCropBox(?PageBox $box): self
    {
        $this->cropBox = $box;
        return $this;
    }

    /**
     * Get BleedBox (defaults to CropBox).
     */
    public function getBleedBox(): PageBox
    {
        return $this->bleedBox ?? $this->getCropBox();
    }

    /**
     * Set BleedBox.
     */
    public function setBleedBox(?PageBox $box): self
    {
        $this->bleedBox = $box;
        return $this;
    }

    /**
     * Get TrimBox (defaults to CropBox).
     */
    public function getTrimBox(): PageBox
    {
        return $this->trimBox ?? $this->getCropBox();
    }

    /**
     * Set TrimBox.
     */
    public function setTrimBox(?PageBox $box): self
    {
        $this->trimBox = $box;
        return $this;
    }

    /**
     * Get ArtBox (defaults to CropBox).
     */
    public function getArtBox(): PageBox
    {
        return $this->artBox ?? $this->getCropBox();
    }

    /**
     * Set ArtBox.
     */
    public function setArtBox(?PageBox $box): self
    {
        $this->artBox = $box;
        return $this;
    }

    /**
     * Get page width from MediaBox.
     */
    public function getWidth(): float
    {
        return $this->mediaBox->getWidth();
    }

    /**
     * Get page height from MediaBox.
     */
    public function getHeight(): float
    {
        return $this->mediaBox->getHeight();
    }

    /**
     * Get rotation (0, 90, 180, or 270).
     */
    public function getRotation(): int
    {
        return $this->rotation;
    }

    /**
     * Set rotation (must be multiple of 90).
     */
    public function setRotation(int $degrees): self
    {
        $degrees = $degrees % 360;
        if ($degrees < 0) {
            $degrees += 360;
        }
        if ($degrees % 90 !== 0) {
            throw new \InvalidArgumentException('Rotation must be a multiple of 90 degrees');
        }
        $this->rotation = $degrees;
        return $this;
    }

    /**
     * Rotate page by 90 degrees clockwise.
     */
    public function rotateClockwise(): self
    {
        return $this->setRotation($this->rotation + 90);
    }

    /**
     * Rotate page by 90 degrees counter-clockwise.
     */
    public function rotateCounterClockwise(): self
    {
        return $this->setRotation($this->rotation - 90);
    }

    /**
     * Get resources dictionary.
     */
    public function getResources(): ?PdfDictionary
    {
        return $this->resources;
    }

    /**
     * Set resources dictionary.
     */
    public function setResources(PdfDictionary $resources): self
    {
        $this->resources = $resources;
        return $this;
    }

    /**
     * Add a content stream.
     */
    public function addContentStream(PdfStream $stream): self
    {
        $this->contentStreams[] = $stream;
        return $this;
    }

    /**
     * Get content streams.
     *
     * @return array<int, PdfStream>
     */
    public function getContentStreams(): array
    {
        return $this->contentStreams;
    }

    /**
     * Set parent reference.
     */
    public function setParentRef(PdfReference $ref): self
    {
        $this->parentRef = $ref;
        return $this;
    }

    /**
     * Get parent reference.
     */
    public function getParentRef(): ?PdfReference
    {
        return $this->parentRef;
    }

    /**
     * Convert to PDF dictionary.
     *
     * @param array<string, PdfReference> $contentRefs References to content streams
     */
    public function toDictionary(array $contentRefs = []): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('Type', PdfName::create('Page'));
        $dict->set('MediaBox', $this->mediaBox->toPdfArray());

        if ($this->cropBox !== null) {
            $dict->set('CropBox', $this->cropBox->toPdfArray());
        }

        if ($this->bleedBox !== null) {
            $dict->set('BleedBox', $this->bleedBox->toPdfArray());
        }

        if ($this->trimBox !== null) {
            $dict->set('TrimBox', $this->trimBox->toPdfArray());
        }

        if ($this->artBox !== null) {
            $dict->set('ArtBox', $this->artBox->toPdfArray());
        }

        if ($this->rotation !== 0) {
            $dict->set('Rotate', PdfNumber::int($this->rotation));
        }

        if ($this->resources !== null) {
            $dict->set('Resources', $this->resources);
        }

        if ($this->parentRef !== null) {
            $dict->set('Parent', $this->parentRef);
        }

        if (!empty($contentRefs)) {
            if (count($contentRefs) === 1) {
                $dict->set('Contents', reset($contentRefs));
            } else {
                $dict->set('Contents', new PdfArray($contentRefs));
            }
        }

        return $dict;
    }

    /**
     * Check if page is portrait orientation.
     */
    public function isPortrait(): bool
    {
        $effectiveRotation = $this->rotation % 180;
        if ($effectiveRotation === 0) {
            return $this->getHeight() >= $this->getWidth();
        }
        return $this->getWidth() >= $this->getHeight();
    }

    /**
     * Check if page is landscape orientation.
     */
    public function isLandscape(): bool
    {
        return !$this->isPortrait();
    }

    /**
     * Get effective width (considering rotation).
     */
    public function getEffectiveWidth(): float
    {
        if ($this->rotation === 90 || $this->rotation === 270) {
            return $this->getHeight();
        }
        return $this->getWidth();
    }

    /**
     * Get effective height (considering rotation).
     */
    public function getEffectiveHeight(): float
    {
        if ($this->rotation === 90 || $this->rotation === 270) {
            return $this->getWidth();
        }
        return $this->getHeight();
    }

    // ===== Content Rendering Methods =====

    /**
     * Get the content stream, creating one if needed.
     */
    public function getContentStream(): ContentStream
    {
        if ($this->contentStream === null) {
            $this->contentStream = new ContentStream();
        }
        return $this->contentStream;
    }

    /**
     * Set the current font for text operations.
     */
    public function setFont(Font $font, float $size): self
    {
        $this->currentFont = $font;
        $this->currentFontSize = $size;
        return $this;
    }

    /**
     * Set the fill color.
     */
    public function setFillColor(Color $color): self
    {
        $this->currentFillColor = $color;
        return $this;
    }

    /**
     * Set the stroke color.
     */
    public function setStrokeColor(Color $color): self
    {
        $this->currentStrokeColor = $color;
        return $this;
    }

    /**
     * Set the line width for strokes.
     */
    public function setLineWidth(float $width): self
    {
        $this->currentLineWidth = $width;
        return $this;
    }

    /**
     * Add text at a position.
     *
     * @param string $text The text to display
     * @param float $x X coordinate (from left)
     * @param float $y Y coordinate (from bottom in PDF coordinates)
     * @param TextStyle|array<string, mixed>|null $style TextStyle object or options array
     */
    public function addText(string $text, float $x, float $y, TextStyle|array|null $style = null): self
    {
        if (is_array($style)) {
            $style = $this->createTextStyleFromArray($style);
        }
        $style = $style ?? $this->createTextStyle();
        $textBlock = new TextBlock($text, $style);
        $textBlock->setPosition($x, $y);
        $textBlock->render($this->getContentStream());

        // Track fonts
        $this->fonts[$style->getFont()->getName()] = $style->getFont();

        return $this;
    }

    /**
     * Add a paragraph with word wrapping.
     */
    public function addParagraph(
        string $text,
        float $x,
        float $y,
        float $maxWidth,
        ?TextStyle $style = null
    ): self {
        $style = $style ?? $this->createTextStyle();
        $paragraph = new Paragraph($text, $style);
        $paragraph->setPosition($x, $y);
        $paragraph->setMaxWidth($maxWidth);
        $paragraph->render($this->getContentStream());

        // Track fonts
        $this->fonts[$style->getFont()->getName()] = $style->getFont();

        return $this;
    }

    /**
     * Draw a line.
     */
    public function drawLine(float $x1, float $y1, float $x2, float $y2): self
    {
        $stream = $this->getContentStream();
        $stream->saveState();

        if ($this->currentStrokeColor !== null) {
            $stream->setStrokeColor($this->currentStrokeColor);
        }
        $stream->setLineWidth($this->currentLineWidth);
        $stream->line($x1, $y1, $x2, $y2);

        $stream->restoreState();
        return $this;
    }

    /**
     * Add a line with options (convenience method).
     *
     * @param array<string, mixed> $options Options like lineWidth, color
     */
    public function addLine(float $x1, float $y1, float $x2, float $y2, array $options = []): self
    {
        $stream = $this->getContentStream();
        $stream->saveState();

        // Set line width
        $lineWidth = $options['lineWidth'] ?? $options['width'] ?? $this->currentLineWidth;
        $stream->setLineWidth($lineWidth);

        // Set color
        if (isset($options['color'])) {
            $color = $this->parseColorOption($options['color']);
            $stream->setStrokeColor($color);
        } elseif ($this->currentStrokeColor !== null) {
            $stream->setStrokeColor($this->currentStrokeColor);
        }

        $stream->line($x1, $y1, $x2, $y2);
        $stream->restoreState();
        return $this;
    }

    /**
     * Add a rectangle with options (convenience method).
     *
     * @param array<string, mixed> $options Options like fill, fillColor, stroke, strokeColor
     */
    public function addRectangle(float $x, float $y, float $width, float $height, array $options = []): self
    {
        $stream = $this->getContentStream();
        $stream->saveState();

        $fill = $options['fill'] ?? false;
        $stroke = $options['stroke'] ?? !$fill;

        // Set fill color
        if ($fill && isset($options['fillColor'])) {
            $fillColor = $this->parseColorOption($options['fillColor']);
            $stream->setFillColor($fillColor);
        }

        // Set stroke color and width
        if ($stroke) {
            $lineWidth = $options['lineWidth'] ?? $options['borderWidth'] ?? $this->currentLineWidth;
            $stream->setLineWidth($lineWidth);

            if (isset($options['strokeColor'])) {
                $strokeColor = $this->parseColorOption($options['strokeColor']);
                $stream->setStrokeColor($strokeColor);
            } elseif ($this->currentStrokeColor !== null) {
                $stream->setStrokeColor($this->currentStrokeColor);
            }
        }

        // Draw rectangle
        $stream->rectangle($x, $y, $width, $height);

        if ($fill && $stroke) {
            $stream->fillAndStroke();
        } elseif ($fill) {
            $stream->fill();
        } else {
            $stream->stroke();
        }

        $stream->restoreState();
        return $this;
    }

    /**
     * Parse a color option into a Color object.
     *
     * @param mixed $color Color specification (array, string, or Color)
     */
    private function parseColorOption(mixed $color): Color
    {
        if ($color instanceof Color) {
            return $color;
        }

        if (is_array($color) && count($color) === 3) {
            return new RgbColor($color[0], $color[1], $color[2]);
        }

        if (is_string($color)) {
            return RgbColor::fromHex($color);
        }

        return RgbColor::black();
    }

    /**
     * Draw a rectangle.
     */
    public function drawRectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        bool $fill = false,
        bool $stroke = true
    ): self {
        $shape = Shape::rectangle($x, $y, $width, $height);

        if ($fill && $this->currentFillColor !== null) {
            $shape->fill($this->currentFillColor);
        }
        if ($stroke && $this->currentStrokeColor !== null) {
            $shape->stroke($this->currentStrokeColor, $this->currentLineWidth);
        }

        $shape->render($this->getContentStream());
        return $this;
    }

    /**
     * Draw a circle.
     */
    public function drawCircle(
        float $cx,
        float $cy,
        float $radius,
        bool $fill = false,
        bool $stroke = true
    ): self {
        $shape = Shape::circle($cx, $cy, $radius);

        if ($fill && $this->currentFillColor !== null) {
            $shape->fill($this->currentFillColor);
        }
        if ($stroke && $this->currentStrokeColor !== null) {
            $shape->stroke($this->currentStrokeColor, $this->currentLineWidth);
        }

        $shape->render($this->getContentStream());
        return $this;
    }

    /**
     * Draw an ellipse.
     */
    public function drawEllipse(
        float $cx,
        float $cy,
        float $rx,
        float $ry,
        bool $fill = false,
        bool $stroke = true
    ): self {
        $shape = Shape::ellipse($cx, $cy, $rx, $ry);

        if ($fill && $this->currentFillColor !== null) {
            $shape->fill($this->currentFillColor);
        }
        if ($stroke && $this->currentStrokeColor !== null) {
            $shape->stroke($this->currentStrokeColor, $this->currentLineWidth);
        }

        $shape->render($this->getContentStream());
        return $this;
    }

    /**
     * Draw a shape.
     */
    public function drawShape(Shape $shape): self
    {
        $shape->render($this->getContentStream());
        return $this;
    }

    /**
     * Draw an image.
     *
     * @param Image|string $image Image object or path
     * @param float $x X coordinate
     * @param float $y Y coordinate
     * @param float|null $width Width (null = original)
     * @param float|null $height Height (null = original or proportional)
     */
    public function drawImage(
        Image|string $image,
        float $x,
        float $y,
        ?float $width = null,
        ?float $height = null
    ): self {
        if (is_string($image)) {
            $image = \PdfLib\Content\Image\ImageFactory::fromFile($image);
        }

        // Register image
        $imageName = 'Im' . (count($this->images) + 1);
        $this->images[$imageName] = $image;

        // Calculate dimensions
        $originalWidth = $image->getWidth();
        $originalHeight = $image->getHeight();

        if ($width === null && $height === null) {
            $width = $originalWidth;
            $height = $originalHeight;
        } elseif ($width === null) {
            $width = $height * ($originalWidth / $originalHeight);
        } elseif ($height === null) {
            $height = $width * ($originalHeight / $originalWidth);
        }

        // Draw image
        $this->getContentStream()->drawImage($imageName, $x, $y, $width, $height);

        return $this;
    }

    /**
     * Get a canvas for more complex drawing operations.
     */
    public function getCanvas(): Canvas
    {
        return new Canvas($this->getWidth(), $this->getHeight());
    }

    /**
     * Apply a canvas to this page.
     */
    public function applyCanvas(Canvas $canvas): self
    {
        // Merge canvas content into page
        $canvasContent = $canvas->getContentStream();

        foreach ($canvasContent->getFonts() as $name => $font) {
            $this->fonts[$name] = $font;
        }

        $this->getContentStream()->raw($canvasContent->toString());

        return $this;
    }

    /**
     * Get registered fonts.
     *
     * @return array<string, Font>
     */
    public function getFonts(): array
    {
        return array_merge($this->fonts, $this->getContentStream()->getFonts());
    }

    /**
     * Get registered images.
     *
     * @return array<string, Image>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * Check if page has content.
     */
    public function hasContent(): bool
    {
        return $this->contentStream !== null || count($this->contentStreams) > 0;
    }

    /**
     * Create a text style from current settings.
     */
    private function createTextStyle(): TextStyle
    {
        $font = $this->currentFont ?? Type1Font::helvetica();
        $style = new TextStyle($font, $this->currentFontSize);

        if ($this->currentFillColor !== null) {
            $style = $style->withColor($this->currentFillColor);
        }

        return $style;
    }

    /**
     * Create a text style from an options array.
     *
     * @param array<string, mixed> $options
     */
    private function createTextStyleFromArray(array $options): TextStyle
    {
        $font = $this->currentFont ?? Type1Font::helvetica();
        $fontSize = $options['fontSize'] ?? $options['size'] ?? $this->currentFontSize;
        $style = new TextStyle($font, $fontSize);

        // Handle color
        if (isset($options['color'])) {
            $color = $options['color'];
            if (is_array($color) && count($color) === 3) {
                $style = $style->withColor(new RgbColor($color[0], $color[1], $color[2]));
            } elseif (is_string($color)) {
                $style = $style->withColor(RgbColor::fromHex($color));
            } elseif ($color instanceof Color) {
                $style = $style->withColor($color);
            }
        } elseif ($this->currentFillColor !== null) {
            $style = $style->withColor($this->currentFillColor);
        }

        return $style;
    }

    /**
     * Get the rendered content as bytes.
     */
    public function getRenderedContent(): ?string
    {
        if ($this->contentStream === null) {
            return null;
        }
        return $this->contentStream->getBytes();
    }
}
