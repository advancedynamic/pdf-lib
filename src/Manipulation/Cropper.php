<?php

declare(strict_types=1);

namespace PdfLib\Manipulation;

use PdfLib\Parser\PdfParser;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfStream;
use PdfLib\Parser\Object\PdfString;
use PdfLib\Parser\Object\PdfBoolean;

/**
 * PDF Cropper - Modify page boxes (MediaBox, CropBox, BleedBox, TrimBox, ArtBox).
 *
 * Properly preserves page content, resources, fonts, and images.
 *
 * @example
 * ```php
 * $cropper = new Cropper('document.pdf');
 * $cropper->setCropBox(50, 50, 545, 742)  // Add 50pt margins
 *         ->save('cropped.pdf');
 *
 * // Or resize to standard size
 * $cropper->resizeTo('A4')
 *         ->save('resized.pdf');
 * ```
 */
final class Cropper
{
    // Standard page sizes in points (72 points = 1 inch)
    public const SIZE_A0 = [2384, 3370];
    public const SIZE_A1 = [1684, 2384];
    public const SIZE_A2 = [1191, 1684];
    public const SIZE_A3 = [842, 1191];
    public const SIZE_A4 = [595, 842];
    public const SIZE_A5 = [420, 595];
    public const SIZE_A6 = [298, 420];
    public const SIZE_LETTER = [612, 792];
    public const SIZE_LEGAL = [612, 1008];
    public const SIZE_TABLOID = [792, 1224];

    // Position constants for cropping alignment
    public const POSITION_CENTER = 'center';
    public const POSITION_TOP_LEFT = 'top-left';
    public const POSITION_TOP_RIGHT = 'top-right';
    public const POSITION_BOTTOM_LEFT = 'bottom-left';
    public const POSITION_BOTTOM_RIGHT = 'bottom-right';

    private ?PdfParser $parser = null;
    private string $content = '';
    private string $version = '1.7';

    /** @var array<int, array<string, array{float, float, float, float}>> Page number => box type => [llx, lly, urx, ury] */
    private array $pageBoxes = [];

    /** @var array<string, array{float, float}> Named sizes */
    private static array $sizes = [
        'A0' => [2384, 3370],
        'A1' => [1684, 2384],
        'A2' => [1191, 1684],
        'A3' => [842, 1191],
        'A4' => [595, 842],
        'A5' => [420, 595],
        'A6' => [298, 420],
        'LETTER' => [612, 792],
        'LEGAL' => [612, 1008],
        'TABLOID' => [792, 1224],
    ];

    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            $this->loadFile($filePath);
        }
    }

    /**
     * Load PDF from file.
     */
    public function loadFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("PDF file not found: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Could not read PDF file: $filePath");
        }

        return $this->loadContent($content);
    }

    /**
     * Load PDF from string content.
     */
    public function loadContent(string $content): self
    {
        if (!str_starts_with($content, '%PDF-')) {
            throw new \InvalidArgumentException('Invalid PDF content');
        }

        $this->content = $content;
        $this->parser = PdfParser::parseString($content);
        $this->version = $this->parser->getVersion();
        $this->pageBoxes = [];

        return $this;
    }

    /**
     * Get total page count.
     */
    public function getPageCount(): int
    {
        $this->ensureLoaded();
        return $this->parser->getPageCount();
    }

    /**
     * Set MediaBox for pages.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function setMediaBox(
        float $llx,
        float $lly,
        float $urx,
        float $ury,
        array|string $pages = 'all'
    ): self {
        return $this->setBox('MediaBox', $llx, $lly, $urx, $ury, $pages);
    }

    /**
     * Set CropBox for pages.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function setCropBox(
        float $llx,
        float $lly,
        float $urx,
        float $ury,
        array|string $pages = 'all'
    ): self {
        return $this->setBox('CropBox', $llx, $lly, $urx, $ury, $pages);
    }

    /**
     * Set BleedBox for pages.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function setBleedBox(
        float $llx,
        float $lly,
        float $urx,
        float $ury,
        array|string $pages = 'all'
    ): self {
        return $this->setBox('BleedBox', $llx, $lly, $urx, $ury, $pages);
    }

    /**
     * Set TrimBox for pages.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function setTrimBox(
        float $llx,
        float $lly,
        float $urx,
        float $ury,
        array|string $pages = 'all'
    ): self {
        return $this->setBox('TrimBox', $llx, $lly, $urx, $ury, $pages);
    }

    /**
     * Set ArtBox for pages.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function setArtBox(
        float $llx,
        float $lly,
        float $urx,
        float $ury,
        array|string $pages = 'all'
    ): self {
        return $this->setBox('ArtBox', $llx, $lly, $urx, $ury, $pages);
    }

    /**
     * Crop to specific dimensions.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function cropTo(
        float $width,
        float $height,
        array|string $pages = 'all',
        string $position = self::POSITION_CENTER
    ): self {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        $pageNumbers = $this->resolvePages($pages, $pageCount);

        foreach ($pageNumbers as $pageNum) {
            $pageDict = $this->parser->getPage($pageNum - 1);
            if ($pageDict === null) {
                continue;
            }

            // Get current MediaBox
            $mediaBox = $pageDict->get('MediaBox');
            $currentWidth = 612.0;
            $currentHeight = 792.0;

            if ($mediaBox instanceof PdfArray) {
                $values = $mediaBox->getValue();
                $currentWidth = (float) ($values[2]->getValue() ?? 612);
                $currentHeight = (float) ($values[3]->getValue() ?? 792);
            }

            // Calculate new box position
            [$llx, $lly] = $this->calculateCropPosition(
                $position,
                $currentWidth,
                $currentHeight,
                $width,
                $height
            );

            $this->setBox('CropBox', $llx, $lly, $llx + $width, $lly + $height, [$pageNum]);
        }

        return $this;
    }

    /**
     * Crop to standard size.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function cropToSize(
        string $size,
        array|string $pages = 'all',
        string $position = self::POSITION_CENTER
    ): self {
        $dimensions = $this->getPageSizeDimensions($size);
        if ($dimensions === null) {
            throw new \InvalidArgumentException("Unknown page size: $size");
        }

        return $this->cropTo($dimensions[0], $dimensions[1], $pages, $position);
    }

    /**
     * Resize pages to standard size (changes MediaBox).
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function resizeTo(string $size, array|string $pages = 'all'): self
    {
        $dimensions = $this->getPageSizeDimensions($size);
        if ($dimensions === null) {
            throw new \InvalidArgumentException("Unknown page size: $size");
        }

        return $this->resizeToCustom($dimensions[0], $dimensions[1], $pages);
    }

    /**
     * Resize pages to custom dimensions (changes MediaBox).
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function resizeToCustom(float $width, float $height, array|string $pages = 'all'): self
    {
        return $this->setMediaBox(0, 0, $width, $height, $pages);
    }

    /**
     * Add uniform margin to all sides.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function addMargin(float $margin, array|string $pages = 'all'): self
    {
        return $this->addMargins($margin, $margin, $margin, $margin, $pages);
    }

    /**
     * Add different margins to each side.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function addMargins(
        float $top,
        float $right,
        float $bottom,
        float $left,
        array|string $pages = 'all'
    ): self {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        $pageNumbers = $this->resolvePages($pages, $pageCount);

        foreach ($pageNumbers as $pageNum) {
            $pageDict = $this->parser->getPage($pageNum - 1);
            if ($pageDict === null) {
                continue;
            }

            // Get current MediaBox
            $mediaBox = $pageDict->get('MediaBox');
            $width = 612.0;
            $height = 792.0;

            if ($mediaBox instanceof PdfArray) {
                $values = $mediaBox->getValue();
                $width = (float) ($values[2]->getValue() ?? 612);
                $height = (float) ($values[3]->getValue() ?? 792);
            }

            // Set CropBox with margins
            $this->setBox(
                'CropBox',
                $left,
                $bottom,
                $width - $right,
                $height - $top,
                [$pageNum]
            );
        }

        return $this;
    }

    /**
     * Remove margins by expanding CropBox to MediaBox.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function removeMargins(array|string $pages = 'all'): self
    {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        $pageNumbers = $this->resolvePages($pages, $pageCount);

        foreach ($pageNumbers as $pageNum) {
            $pageDict = $this->parser->getPage($pageNum - 1);
            if ($pageDict === null) {
                continue;
            }

            // Get current MediaBox
            $mediaBox = $pageDict->get('MediaBox');
            if ($mediaBox instanceof PdfArray) {
                $values = $mediaBox->getValue();
                $this->setBox(
                    'CropBox',
                    (float) ($values[0]->getValue() ?? 0),
                    (float) ($values[1]->getValue() ?? 0),
                    (float) ($values[2]->getValue() ?? 612),
                    (float) ($values[3]->getValue() ?? 792),
                    [$pageNum]
                );
            }
        }

        return $this;
    }

    /**
     * Get MediaBox for a specific page.
     *
     * @return array{float, float, float, float}
     */
    public function getMediaBox(int $pageNum): array
    {
        return $this->getBox('MediaBox', $pageNum);
    }

    /**
     * Get all boxes for a specific page.
     *
     * @return array<string, array{float, float, float, float}>
     */
    public function getAllBoxes(int $pageNum): array
    {
        $this->ensureLoaded();

        $boxes = [];
        foreach (['MediaBox', 'CropBox', 'BleedBox', 'TrimBox', 'ArtBox'] as $boxType) {
            $box = $this->getBox($boxType, $pageNum);
            if (!empty($box)) {
                $boxes[$boxType] = $box;
            }
        }

        return $boxes;
    }

    /**
     * Get supported standard sizes.
     *
     * @return array<string, array{float, float}>
     */
    public static function getSupportedSizes(): array
    {
        return self::$sizes;
    }

    /**
     * Get dimensions for a standard size.
     *
     * @return array{float, float}|null
     */
    public static function getPageSizeDimensions(string $size): ?array
    {
        $size = strtoupper($size);
        return self::$sizes[$size] ?? null;
    }

    /**
     * Clear all pending box modifications.
     */
    public function clearBoxes(): self
    {
        $this->pageBoxes = [];
        return $this;
    }

    /**
     * Apply box modifications and return result.
     */
    public function apply(): SplitResult
    {
        $this->ensureLoaded();
        return new SplitResult($this->buildCroppedPdf());
    }

    /**
     * Apply box modifications and save to file.
     */
    public function save(string $outputPath): bool
    {
        return $this->apply()->save($outputPath);
    }

    /**
     * Apply box modifications and save to file (alias for save).
     */
    public function applyToFile(string $outputPath): bool
    {
        return $this->save($outputPath);
    }

    /**
     * Ensure PDF is loaded.
     */
    private function ensureLoaded(): void
    {
        if ($this->parser === null) {
            throw new \RuntimeException('No PDF loaded. Call loadFile() or loadContent() first.');
        }
    }

    /**
     * Set a specific box type.
     *
     * @param array<int>|string $pages
     */
    private function setBox(
        string $boxType,
        float $llx,
        float $lly,
        float $urx,
        float $ury,
        array|string $pages
    ): self {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        $pageNumbers = $this->resolvePages($pages, $pageCount);

        foreach ($pageNumbers as $pageNum) {
            if (!isset($this->pageBoxes[$pageNum])) {
                $this->pageBoxes[$pageNum] = [];
            }
            $this->pageBoxes[$pageNum][$boxType] = [$llx, $lly, $urx, $ury];
        }

        return $this;
    }

    /**
     * Get a specific box for a page.
     *
     * @return array{float, float, float, float}
     */
    private function getBox(string $boxType, int $pageNum): array
    {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        if ($pageNum < 1 || $pageNum > $pageCount) {
            throw new \InvalidArgumentException("Invalid page number: $pageNum");
        }

        // Check pending modifications first
        if (isset($this->pageBoxes[$pageNum][$boxType])) {
            return $this->pageBoxes[$pageNum][$boxType];
        }

        // Get from parsed PDF
        $pageDict = $this->parser->getPage($pageNum - 1);

        if ($pageDict !== null) {
            $box = $pageDict->get($boxType);
            if ($box instanceof PdfArray) {
                $values = $box->getValue();
                return [
                    (float) ($values[0]->getValue() ?? 0),
                    (float) ($values[1]->getValue() ?? 0),
                    (float) ($values[2]->getValue() ?? 612),
                    (float) ($values[3]->getValue() ?? 792),
                ];
            }
        }

        // Default MediaBox if nothing found
        if ($boxType === 'MediaBox') {
            return [0.0, 0.0, 612.0, 792.0];
        }

        return [];
    }

    /**
     * Calculate crop position based on alignment.
     *
     * @return array{float, float}
     */
    private function calculateCropPosition(
        string $position,
        float $currentWidth,
        float $currentHeight,
        float $newWidth,
        float $newHeight
    ): array {
        return match ($position) {
            self::POSITION_TOP_LEFT => [0, $currentHeight - $newHeight],
            self::POSITION_TOP_RIGHT => [$currentWidth - $newWidth, $currentHeight - $newHeight],
            self::POSITION_BOTTOM_LEFT => [0, 0],
            self::POSITION_BOTTOM_RIGHT => [$currentWidth - $newWidth, 0],
            default => [ // center
                ($currentWidth - $newWidth) / 2,
                ($currentHeight - $newHeight) / 2,
            ],
        };
    }

    /**
     * Build cropped PDF with proper content preservation.
     */
    private function buildCroppedPdf(): string
    {
        $pageCount = $this->parser->getPageCount();

        // First pass: count objects to pre-calculate Pages object number
        $totalObjects = 0;
        $pageObjectMaps = [];

        for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
            $pageDict = $this->parser->getPage($pageIndex);
            if ($pageDict === null) {
                continue;
            }
            $referencedObjects = $this->collectReferencedObjects($pageDict);
            $pageObjectMaps[$pageIndex] = [
                'referenced' => $referencedObjects,
                'dict' => $pageDict,
            ];
            $totalObjects += count($referencedObjects) + 1; // +1 for page object
        }

        // Pages object will be at position: totalObjects + 1
        $pagesObjNum = $totalObjects + 1;

        $output = "%PDF-{$this->version}\n";
        $output .= "%\xE2\xE3\xCF\xD3\n"; // Binary marker

        $objects = [];
        $objectNum = 1;
        $pageRefs = [];

        // Second pass: write objects with correct Parent reference
        foreach ($pageObjectMaps as $pageIndex => $pageData) {
            $pageNum = $pageIndex + 1;
            $pageDict = $pageData['dict'];
            $referencedObjects = $pageData['referenced'];

            // Object mapping: old object ID -> new object ID
            $objectMap = [];

            // Assign new object numbers
            foreach ($referencedObjects as $oldId => $obj) {
                $objectMap[$oldId] = $objectNum++;
            }

            // Write objects with updated references
            foreach ($referencedObjects as $oldId => $obj) {
                $newId = $objectMap[$oldId];
                $objects[$newId] = ['offset' => strlen($output)];
                $content = $this->writeObject($newId, $obj, $objectMap);
                $output .= $content;
            }

            // Write page object with box modifications and correct Parent
            $pageObjNum = $objectNum++;
            $pageRefs[] = $pageObjNum;
            $boxMods = $this->pageBoxes[$pageNum] ?? [];
            $objects[$pageObjNum] = ['offset' => strlen($output)];
            $content = $this->writePageObject($pageObjNum, $pageDict, $objectMap, $boxMods, $pagesObjNum);
            $output .= $content;
        }

        // Write Pages object
        $objects[$pagesObjNum] = ['offset' => strlen($output)];
        $pagesContent = "{$pagesObjNum} 0 obj\n<<\n/Type /Pages\n/Kids [";
        foreach ($pageRefs as $ref) {
            $pagesContent .= "{$ref} 0 R ";
        }
        $pagesContent .= "]\n/Count " . count($pageRefs) . "\n>>\nendobj\n";
        $output .= $pagesContent;
        $objectNum = $pagesObjNum + 1;

        // Write Catalog
        $catalogObjNum = $objectNum++;
        $objects[$catalogObjNum] = ['offset' => strlen($output)];
        $catalogContent = "{$catalogObjNum} 0 obj\n<<\n/Type /Catalog\n/Pages {$pagesObjNum} 0 R\n>>\nendobj\n";
        $output .= $catalogContent;

        // Write Info
        $infoObjNum = $objectNum++;
        $objects[$infoObjNum] = ['offset' => strlen($output)];
        $date = 'D:' . date('YmdHis') . "+00'00'";
        $infoContent = "{$infoObjNum} 0 obj\n<<\n/Producer (PdfLib Cropper)\n/CreationDate ({$date})\n>>\nendobj\n";
        $output .= $infoContent;

        // Write xref
        $xrefOffset = strlen($output);
        $output .= "xref\n0 {$objectNum}\n";
        $output .= "0000000000 65535 f \n";

        // Sort objects by number
        ksort($objects);

        for ($i = 1; $i < $objectNum; $i++) {
            if (isset($objects[$i])) {
                $output .= sprintf("%010d 00000 n \n", $objects[$i]['offset']);
            } else {
                $output .= "0000000000 00000 f \n";
            }
        }

        // Write trailer
        $id = bin2hex(random_bytes(16));
        $output .= "trailer\n<<\n";
        $output .= "/Size {$objectNum}\n";
        $output .= "/Root {$catalogObjNum} 0 R\n";
        $output .= "/Info {$infoObjNum} 0 R\n";
        $output .= "/ID [<{$id}> <{$id}>]\n";
        $output .= ">>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $output;
    }

    /**
     * Collect all objects referenced by a page.
     *
     * @return array<int, PdfObject>
     */
    private function collectReferencedObjects(PdfDictionary $pageDict): array
    {
        $objects = [];
        $visited = [];

        $this->collectReferences($pageDict, $objects, $visited);

        return $objects;
    }

    /**
     * Recursively collect referenced objects.
     *
     * @param array<int, PdfObject> $objects
     * @param array<int, bool> $visited
     */
    private function collectReferences(PdfObject $obj, array &$objects, array &$visited): void
    {
        if ($obj instanceof PdfReference) {
            $id = $obj->getObjectNumber();
            if (isset($visited[$id])) {
                return;
            }
            $visited[$id] = true;

            $resolved = $this->parser->resolveReference($obj);
            if ($resolved !== null) {
                $objects[$id] = $resolved;
                $this->collectReferences($resolved, $objects, $visited);
            }
        } elseif ($obj instanceof PdfDictionary) {
            foreach ($obj->getValue() as $key => $value) {
                // Skip Parent reference to avoid circular references
                if ($key === 'Parent') {
                    continue;
                }
                $this->collectReferences($value, $objects, $visited);
            }
        } elseif ($obj instanceof PdfArray) {
            foreach ($obj->getValue() as $item) {
                $this->collectReferences($item, $objects, $visited);
            }
        } elseif ($obj instanceof PdfStream) {
            $this->collectReferences($obj->getDictionary(), $objects, $visited);
        }
    }

    /**
     * Write an object with updated references.
     *
     * @param array<int, int> $objectMap
     */
    private function writeObject(int $objNum, PdfObject $obj, array $objectMap): string
    {
        $content = "{$objNum} 0 obj\n";
        $content .= $this->serializeObject($obj, $objectMap);
        $content .= "\nendobj\n";
        return $content;
    }

    /**
     * Write a page object with box modifications and correct Parent reference.
     *
     * @param array<int, int> $objectMap
     * @param array<string, array{float, float, float, float}> $boxMods
     */
    private function writePageObject(int $objNum, PdfDictionary $pageDict, array $objectMap, array $boxMods, int $parentObjNum): string
    {
        $content = "{$objNum} 0 obj\n<<\n";
        $content .= "/Type /Page\n";

        $writtenKeys = ['Type', 'Parent'];

        // Write box modifications first
        foreach ($boxMods as $boxType => $box) {
            $content .= "/{$boxType} [{$box[0]} {$box[1]} {$box[2]} {$box[3]}]\n";
            $writtenKeys[] = $boxType;
        }

        // Write remaining page dictionary entries
        foreach ($pageDict->getValue() as $key => $value) {
            if (in_array($key, $writtenKeys, true)) {
                continue;
            }

            $content .= "/{$key} " . $this->serializeObject($value, $objectMap) . "\n";
        }

        $content .= "/Parent {$parentObjNum} 0 R\n";
        $content .= ">>\nendobj\n";

        return $content;
    }

    /**
     * Serialize a PDF object to string.
     *
     * @param array<int, int> $objectMap
     */
    private function serializeObject(PdfObject $obj, array $objectMap): string
    {
        if ($obj instanceof PdfReference) {
            $oldId = $obj->getObjectNumber();
            $newId = $objectMap[$oldId] ?? $oldId;
            return "{$newId} 0 R";
        }

        if ($obj instanceof PdfDictionary) {
            $result = "<<\n";
            foreach ($obj->getValue() as $key => $value) {
                $result .= "/{$key} " . $this->serializeObject($value, $objectMap) . "\n";
            }
            $result .= ">>";
            return $result;
        }

        if ($obj instanceof PdfArray) {
            $items = [];
            foreach ($obj->getValue() as $item) {
                $items[] = $this->serializeObject($item, $objectMap);
            }
            return "[" . implode(" ", $items) . "]";
        }

        if ($obj instanceof PdfStream) {
            $dict = $obj->getDictionary();
            $data = $obj->getData();

            $result = "<<\n";
            foreach ($dict->getValue() as $key => $value) {
                // Update Length to match actual data length
                if ($key === 'Length') {
                    $result .= "/Length " . strlen($data) . "\n";
                } else {
                    $result .= "/{$key} " . $this->serializeObject($value, $objectMap) . "\n";
                }
            }
            // Add Length if not present
            if (!$dict->has('Length')) {
                $result .= "/Length " . strlen($data) . "\n";
            }
            $result .= ">>\nstream\n";
            $result .= $data;
            $result .= "\nendstream";
            return $result;
        }

        if ($obj instanceof PdfName) {
            return "/" . $obj->getValue();
        }

        if ($obj instanceof PdfNumber) {
            $value = $obj->getValue();
            if (is_int($value) || floor($value) == $value) {
                return (string) (int) $value;
            }
            return (string) $value;
        }

        if ($obj instanceof PdfString) {
            return $obj->toPdfString();
        }

        if ($obj instanceof PdfBoolean) {
            return $obj->getValue() ? "true" : "false";
        }

        if ($obj instanceof \PdfLib\Parser\Object\PdfNull) {
            return "null";
        }

        return (string) $obj->getValue();
    }

    /**
     * Resolve page selection to array of page numbers.
     *
     * @param array<int>|string $pages
     * @return array<int>
     */
    private function resolvePages(array|string $pages, int $totalPages): array
    {
        if ($pages === 'all') {
            return range(1, $totalPages);
        }

        if (is_array($pages)) {
            return array_filter($pages, fn($p) => $p >= 1 && $p <= $totalPages);
        }

        // Parse range string
        $result = [];
        $parts = explode(',', $pages);

        foreach ($parts as $part) {
            $part = trim($part);
            if (str_contains($part, '-')) {
                [$start, $end] = explode('-', $part, 2);
                $start = (int) $start;
                $end = (int) $end;
                for ($i = $start; $i <= $end && $i <= $totalPages; $i++) {
                    if ($i >= 1) {
                        $result[] = $i;
                    }
                }
            } else {
                $num = (int) $part;
                if ($num >= 1 && $num <= $totalPages) {
                    $result[] = $num;
                }
            }
        }

        return array_unique($result);
    }
}
