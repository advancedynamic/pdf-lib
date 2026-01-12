<?php

declare(strict_types=1);

namespace PdfLib\Document;

use PdfLib\Form\AcroForm;
use PdfLib\Form\CheckboxField;
use PdfLib\Form\DropdownField;
use PdfLib\Form\FormWriter;
use PdfLib\Form\ListBoxField;
use PdfLib\Form\RadioButtonGroup;
use PdfLib\Form\TextField;
use PdfLib\Page\Page;
use PdfLib\Page\PageCollection;
use PdfLib\Page\PageSize;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfStream;
use PdfLib\Parser\PdfParser;
use PdfLib\Writer\PdfWriter;

/**
 * Main PDF document class - the primary interface for creating and manipulating PDFs.
 *
 * Supports both fluent API for simple use cases and explicit object API for complex scenarios.
 *
 * @example Simple fluent API:
 * ```php
 * $pdf = PdfDocument::create()
 *     ->setTitle('My Document')
 *     ->addPage(PageSize::a4())
 *     ->save('output.pdf');
 * ```
 *
 * @example Loading existing PDF:
 * ```php
 * $pdf = PdfDocument::load('input.pdf');
 * echo $pdf->getPageCount();
 * $pdf->getPage(0)->setRotation(90);
 * $pdf->save('rotated.pdf');
 * ```
 */
final class PdfDocument
{
    private Metadata $metadata;
    private PageCollection $pages;
    private ?PdfParser $parser = null;
    private string $version = '1.7';
    private ?AcroForm $acroForm = null;

    /** @var array<string, mixed> */
    private array $options = [
        'compress' => true,
        'compressionLevel' => 6,
    ];

    public function __construct()
    {
        $this->metadata = new Metadata();
        $this->pages = new PageCollection();
    }

    /**
     * Create a new empty document.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Load a PDF from file.
     */
    public static function load(string $path): self
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Could not read file: $path");
        }
        return self::loadFromString($content);
    }

    /**
     * Load a PDF from string content.
     */
    public static function loadFromString(string $content): self
    {
        $document = new self();
        $document->parser = PdfParser::parseString($content);
        $document->version = $document->parser->getVersion();

        // Load metadata
        $infoDict = $document->parser->getInfo();
        if ($infoDict !== null) {
            $document->metadata = Metadata::fromDictionary($infoDict);
        }

        // Load pages
        $pageDicts = $document->parser->getPages();
        foreach ($pageDicts as $pageDict) {
            $page = Page::fromDictionary($pageDict);
            $document->pages->add($page);
        }

        return $document;
    }

    // Metadata methods

    /**
     * Get document metadata.
     */
    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    /**
     * Set document metadata.
     */
    public function setMetadata(Metadata $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Set document title.
     */
    public function setTitle(string $title): self
    {
        $this->metadata->setTitle($title);
        return $this;
    }

    /**
     * Get document title.
     */
    public function getTitle(): ?string
    {
        return $this->metadata->getTitle();
    }

    /**
     * Set document author.
     */
    public function setAuthor(string $author): self
    {
        $this->metadata->setAuthor($author);
        return $this;
    }

    /**
     * Get document author.
     */
    public function getAuthor(): ?string
    {
        return $this->metadata->getAuthor();
    }

    /**
     * Set document subject.
     */
    public function setSubject(string $subject): self
    {
        $this->metadata->setSubject($subject);
        return $this;
    }

    /**
     * Set document keywords.
     */
    public function setKeywords(string $keywords): self
    {
        $this->metadata->setKeywords($keywords);
        return $this;
    }

    /**
     * Set document creator.
     */
    public function setCreator(string $creator): self
    {
        $this->metadata->setCreator($creator);
        return $this;
    }

    // Page methods

    /**
     * Get all pages.
     */
    public function getPages(): PageCollection
    {
        return $this->pages;
    }

    /**
     * Get page count.
     */
    public function getPageCount(): int
    {
        return count($this->pages);
    }

    /**
     * Get a specific page (0-indexed).
     */
    public function getPage(int $index): ?Page
    {
        return $this->pages->get($index);
    }

    /**
     * Add a new page.
     */
    public function addPage(?PageSize $size = null): self
    {
        $page = new Page($size ?? PageSize::a4());
        $this->pages->add($page);
        return $this;
    }

    /**
     * Add an existing page object.
     */
    public function addPageObject(Page $page): self
    {
        $this->pages->add($page);
        return $this;
    }

    /**
     * Insert a page at a specific position.
     */
    public function insertPage(int $index, ?PageSize $size = null): self
    {
        $page = new Page($size ?? PageSize::a4());
        $this->pages->insertAt($index, $page);
        return $this;
    }

    /**
     * Remove a page at a specific position.
     */
    public function removePage(int $index): self
    {
        $this->pages->removeAt($index);
        return $this;
    }

    /**
     * Move a page from one position to another.
     */
    public function movePage(int $from, int $to): self
    {
        $this->pages->move($from, $to);
        return $this;
    }

    // Form methods

    /**
     * Get the AcroForm, creating it if it doesn't exist.
     */
    public function getAcroForm(): AcroForm
    {
        if ($this->acroForm === null) {
            $this->acroForm = AcroForm::create();
        }
        return $this->acroForm;
    }

    /**
     * Set the AcroForm.
     */
    public function setAcroForm(AcroForm $acroForm): self
    {
        $this->acroForm = $acroForm;
        return $this;
    }

    /**
     * Check if document has form fields.
     */
    public function hasForm(): bool
    {
        return $this->acroForm !== null && !$this->acroForm->isEmpty();
    }

    /**
     * Add a text field to the document.
     *
     * @param string $name Field name
     * @param int $page Page number (1-indexed)
     */
    public function addTextField(string $name, int $page = 1): TextField
    {
        $field = $this->getAcroForm()->createTextField($name);
        $field->setPage($page);
        return $field;
    }

    /**
     * Add a checkbox to the document.
     *
     * @param string $name Field name
     * @param int $page Page number (1-indexed)
     */
    public function addCheckbox(string $name, int $page = 1): CheckboxField
    {
        $field = $this->getAcroForm()->createCheckbox($name);
        $field->setPage($page);
        return $field;
    }

    /**
     * Add a dropdown (combo box) to the document.
     *
     * @param string $name Field name
     * @param int $page Page number (1-indexed)
     */
    public function addDropdown(string $name, int $page = 1): DropdownField
    {
        $field = $this->getAcroForm()->createDropdown($name);
        $field->setPage($page);
        return $field;
    }

    /**
     * Add a list box to the document.
     *
     * @param string $name Field name
     * @param int $page Page number (1-indexed)
     */
    public function addListBox(string $name, int $page = 1): ListBoxField
    {
        $field = $this->getAcroForm()->createListBox($name);
        $field->setPage($page);
        return $field;
    }

    /**
     * Add a radio button group to the document.
     *
     * @param string $name Group name
     * @param int $page Page number (1-indexed)
     */
    public function addRadioGroup(string $name, int $page = 1): RadioButtonGroup
    {
        $group = $this->getAcroForm()->createRadioGroup($name);
        $group->setPage($page);
        return $group;
    }

    // Version methods

    /**
     * Get PDF version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Set PDF version.
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    // Options methods

    /**
     * Enable or disable compression.
     */
    public function setCompression(bool $enabled): self
    {
        $this->options['compress'] = $enabled;
        return $this;
    }

    /**
     * Set compression level (0-9).
     */
    public function setCompressionLevel(int $level): self
    {
        $this->options['compressionLevel'] = max(0, min(9, $level));
        return $this;
    }

    // Output methods

    /**
     * Save the document to a file.
     */
    public function save(string $path): self
    {
        $content = $this->render();
        $result = file_put_contents($path, $content);
        if ($result === false) {
            throw new \RuntimeException("Could not write to file: $path");
        }
        return $this;
    }

    /**
     * Render the document to a string.
     */
    public function render(): string
    {
        $writer = new PdfWriter();
        $writer->setVersion($this->version);
        $writer->setCompression($this->options['compress']);
        $writer->setCompressionLevel($this->options['compressionLevel']);

        // Build form fields first (we need annotations before creating page dicts)
        $formWriter = null;
        $acroFormRef = null;
        if ($this->hasForm()) {
            $formWriter = new FormWriter($writer);
            $formWriter->addCollection($this->acroForm->getFields());
            foreach ($this->acroForm->getRadioGroups() as $group) {
                $formWriter->addRadioGroup($group);
            }
            $acroFormRef = $formWriter->buildAcroForm();
        }

        // Add pages with their content
        $pageRefs = [];
        $pageIndex = 0;
        foreach ($this->pages as $page) {
            // Build resources for this page
            $resources = new PdfDictionary();

            // Handle fonts
            $fonts = $page->getFonts();
            if (!empty($fonts)) {
                $fontDict = new PdfDictionary();
                $fontIndex = 1;
                foreach ($fonts as $font) {
                    $fontRef = $writer->addObject($font->toDictionary());
                    $fontDict->set('F' . $fontIndex, $fontRef);
                    $fontIndex++;
                }
                $resources->set('Font', $fontDict);
            }

            // Handle images
            $images = $page->getImages();
            if (!empty($images)) {
                $xObjectDict = new PdfDictionary();
                foreach ($images as $imageName => $image) {
                    $imageStream = $image->toPdfStream();
                    $imageRef = $writer->addObject($imageStream);

                    // Handle soft mask (alpha channel)
                    if ($image->hasSoftMask()) {
                        $softMaskStream = $image->createSoftMaskStream();
                        if ($softMaskStream !== null) {
                            $softMaskRef = $writer->addObject($softMaskStream);
                            $imageStream->getDictionary()->set('SMask', $softMaskRef);
                        }
                    }

                    $xObjectDict->set($imageName, $imageRef);
                }
                $resources->set('XObject', $xObjectDict);
            }

            // Handle content stream
            $contentRef = null;
            $content = $page->getRenderedContent();
            if ($content !== null && $content !== '') {
                $streamWriter = $writer->getStreamWriter();
                $contentStream = $streamWriter->createContentStream($content, $this->options['compress']);
                $contentRef = $writer->addObject($contentStream);
            }

            // Build page dictionary with content reference
            $pageDict = $page->toDictionary($contentRef !== null ? [$contentRef] : []);

            // Add resources to page
            if ($resources->count() > 0) {
                $pageDict->set('Resources', $resources);
            }

            // Add form field annotations for this page (1-indexed page number)
            if ($formWriter !== null) {
                $pageAnnots = $formWriter->getAnnotationsForPage($pageIndex + 1);
                if (!empty($pageAnnots)) {
                    $pageDict->set('Annots', new PdfArray($pageAnnots));
                }
            }

            $pageRef = $writer->addObject($pageDict);
            $pageRefs[] = $pageRef;

            // Update page reference in annotations
            if ($formWriter !== null) {
                $pageAnnots = $formWriter->getAnnotationsForPage($pageIndex + 1);
                foreach ($pageAnnots as $annotRef) {
                    $annotObj = $writer->getObject($annotRef->getObjectNumber());
                    if ($annotObj instanceof PdfDictionary) {
                        $annotObj->set('P', $pageRef);
                    }
                }
            }

            $pageIndex++;
        }

        // Create Pages dictionary
        $pagesDict = $this->pages->toPagesDict($pageRefs);
        $pagesRef = $writer->addObject($pagesDict);

        // Update page parent references
        foreach ($pageRefs as $pageRef) {
            $pageObj = $writer->getObject($pageRef->getObjectNumber());
            if ($pageObj instanceof PdfDictionary) {
                $pageObj->set('Parent', $pagesRef);
            }
        }

        // Create catalog
        $catalog = new PdfDictionary();
        $catalog->set('Type', PdfName::create('Catalog'));
        $catalog->set('Pages', $pagesRef);

        // Add AcroForm to catalog if we have form fields
        if ($acroFormRef !== null) {
            $catalog->set('AcroForm', $acroFormRef);
        }

        $writer->setCatalog($catalog);

        // Set document info
        $writer->setInfo($this->metadata->toDictionary());

        return $writer->write();
    }

    /**
     * Output the document directly to browser with appropriate headers.
     */
    public function output(string $filename = 'document.pdf', bool $download = false): void
    {
        $content = $this->render();

        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($content));

        if ($download) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        }

        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $content;
    }

    // Utility methods

    /**
     * Clone the document.
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Check if document was loaded from an existing PDF.
     */
    public function isLoaded(): bool
    {
        return $this->parser !== null;
    }

    /**
     * Get the underlying parser (if document was loaded).
     */
    public function getParser(): ?PdfParser
    {
        return $this->parser;
    }

    /**
     * Check if document is encrypted.
     */
    public function isEncrypted(): bool
    {
        return $this->parser?->isEncrypted() ?? false;
    }

    /**
     * Merge another document into this one.
     */
    public function merge(self $other): self
    {
        $this->pages->append($other->pages);
        return $this;
    }

    /**
     * Extract specific pages into a new document.
     *
     * @param array<int, int> $pageNumbers 0-indexed page numbers
     */
    public function extractPages(array $pageNumbers): self
    {
        $document = new self();
        $document->version = $this->version;

        $extracted = $this->pages->extract($pageNumbers);
        foreach ($extracted as $page) {
            $document->pages->add($page);
        }

        return $document;
    }

    /**
     * Split document into chunks.
     *
     * @param int $pagesPerChunk Number of pages per chunk
     * @return array<int, self>
     */
    public function split(int $pagesPerChunk): array
    {
        $chunks = [];
        $pageCount = $this->getPageCount();

        for ($i = 0; $i < $pageCount; $i += $pagesPerChunk) {
            $chunk = new self();
            $chunk->version = $this->version;

            for ($j = $i; $j < min($i + $pagesPerChunk, $pageCount); $j++) {
                $page = $this->pages->get($j);
                if ($page !== null) {
                    $chunk->pages->add($page);
                }
            }

            $chunks[] = $chunk;
        }

        return $chunks;
    }
}
