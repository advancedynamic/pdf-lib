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
 * PDF Rotator - Rotate PDF pages.
 *
 * Properly preserves page content, resources, fonts, and images.
 *
 * @example
 * ```php
 * $rotator = new Rotator('document.pdf');
 * $rotator->rotatePages(90)  // Rotate all pages 90° clockwise
 *         ->save('rotated.pdf');
 *
 * // Or rotate specific pages
 * $rotator->rotatePage(1, 90)
 *         ->rotatePage(3, 180)
 *         ->save('rotated.pdf');
 * ```
 */
final class Rotator
{
    private ?PdfParser $parser = null;
    private string $content = '';
    private string $version = '1.7';

    /** @var array<int, int> Page number => rotation degrees */
    private array $rotations = [];

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
        $this->rotations = [];

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
     * Rotate a single page.
     *
     * @param int $pageNum 1-indexed page number
     * @param int $degrees Rotation in degrees (0, 90, 180, 270)
     */
    public function rotatePage(int $pageNum, int $degrees): self
    {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        if ($pageNum < 1 || $pageNum > $pageCount) {
            throw new \InvalidArgumentException("Invalid page number: $pageNum");
        }

        $this->rotations[$pageNum] = $this->normalizeRotation($degrees);
        return $this;
    }

    /**
     * Rotate multiple pages.
     *
     * @param int $degrees Rotation in degrees
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function rotatePages(int $degrees, array|string $pages = 'all'): self
    {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        $pageNumbers = $this->resolvePages($pages, $pageCount);
        $normalizedDegrees = $this->normalizeRotation($degrees);

        foreach ($pageNumbers as $pageNum) {
            $this->rotations[$pageNum] = $normalizedDegrees;
        }

        return $this;
    }

    /**
     * Rotate all pages 90° clockwise.
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function rotateClockwise(array|string $pages = 'all'): self
    {
        return $this->rotatePages(90, $pages);
    }

    /**
     * Rotate all pages 90° counter-clockwise (270°).
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function rotateCounterClockwise(array|string $pages = 'all'): self
    {
        return $this->rotatePages(270, $pages);
    }

    /**
     * Rotate all pages 180° (upside down).
     *
     * @param array<int>|string $pages Page numbers or 'all'
     */
    public function rotateUpsideDown(array|string $pages = 'all'): self
    {
        return $this->rotatePages(180, $pages);
    }

    /**
     * Rotate odd pages only.
     */
    public function rotateOddPages(int $degrees): self
    {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        $normalizedDegrees = $this->normalizeRotation($degrees);

        for ($i = 1; $i <= $pageCount; $i += 2) {
            $this->rotations[$i] = $normalizedDegrees;
        }

        return $this;
    }

    /**
     * Rotate even pages only.
     */
    public function rotateEvenPages(int $degrees): self
    {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        $normalizedDegrees = $this->normalizeRotation($degrees);

        for ($i = 2; $i <= $pageCount; $i += 2) {
            $this->rotations[$i] = $normalizedDegrees;
        }

        return $this;
    }

    /**
     * Reset rotation for a specific page.
     */
    public function resetPageRotation(int $pageNum): self
    {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        if ($pageNum < 1 || $pageNum > $pageCount) {
            throw new \InvalidArgumentException("Invalid page number: $pageNum");
        }

        $this->rotations[$pageNum] = 0;
        return $this;
    }

    /**
     * Reset all rotations to 0.
     */
    public function resetAllRotations(): self
    {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        for ($i = 1; $i <= $pageCount; $i++) {
            $this->rotations[$i] = 0;
        }

        return $this;
    }

    /**
     * Get rotation for a specific page.
     *
     * @param int $pageNum 1-indexed page number
     * @return int Rotation in degrees
     */
    public function getPageRotation(int $pageNum): int
    {
        $this->ensureLoaded();

        $pageCount = $this->getPageCount();
        if ($pageNum < 1 || $pageNum > $pageCount) {
            throw new \InvalidArgumentException("Invalid page number: $pageNum");
        }

        // Check if we have a pending rotation
        if (isset($this->rotations[$pageNum])) {
            return $this->rotations[$pageNum];
        }

        // Get existing rotation from PDF
        $pageDict = $this->parser->getPage($pageNum - 1);

        if ($pageDict !== null) {
            $rotation = $pageDict->get('Rotate');
            if ($rotation instanceof PdfNumber) {
                return (int) $rotation->getValue();
            }
        }

        return 0;
    }

    /**
     * Get all pending rotations.
     *
     * @return array<int, int> Page number => degrees
     */
    public function getAllRotations(): array
    {
        return $this->rotations;
    }

    /**
     * Clear pending rotations.
     */
    public function clearRotations(): self
    {
        $this->rotations = [];
        return $this;
    }

    /**
     * Apply rotations and return result.
     */
    public function apply(): SplitResult
    {
        $this->ensureLoaded();
        return new SplitResult($this->buildRotatedPdf());
    }

    /**
     * Apply rotations and save to file.
     */
    public function save(string $outputPath): bool
    {
        return $this->apply()->save($outputPath);
    }

    /**
     * Apply rotations and save to file (alias for save).
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
     * Normalize rotation to valid PDF values (0, 90, 180, 270).
     */
    private function normalizeRotation(int $degrees): int
    {
        // Normalize to 0-359
        $degrees = $degrees % 360;
        if ($degrees < 0) {
            $degrees += 360;
        }

        // Round to nearest 90
        if ($degrees < 45) {
            return 0;
        }
        if ($degrees < 135) {
            return 90;
        }
        if ($degrees < 225) {
            return 180;
        }
        if ($degrees < 315) {
            return 270;
        }

        return 0;
    }

    /**
     * Build rotated PDF with proper content preservation.
     */
    private function buildRotatedPdf(): string
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
                $objects[$newId] = [
                    'offset' => strlen($output),
                ];
                $content = $this->writeObject($newId, $obj, $objectMap);
                $output .= $content;
            }

            // Calculate final rotation
            $existingRotation = 0;
            $rotationObj = $pageDict->get('Rotate');
            if ($rotationObj instanceof PdfNumber) {
                $existingRotation = (int) $rotationObj->getValue();
            }

            $finalRotation = $existingRotation;
            if (isset($this->rotations[$pageNum])) {
                $finalRotation = ($existingRotation + $this->rotations[$pageNum]) % 360;
            }

            // Write page object with rotation and correct Parent
            $pageObjNum = $objectNum++;
            $pageRefs[] = $pageObjNum;
            $objects[$pageObjNum] = [
                'offset' => strlen($output),
            ];
            $content = $this->writePageObject($pageObjNum, $pageDict, $objectMap, $finalRotation, $pagesObjNum);
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
        $infoContent = "{$infoObjNum} 0 obj\n<<\n/Producer (PdfLib Rotator)\n/CreationDate ({$date})\n>>\nendobj\n";
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
     * Write a page object with rotation and correct Parent reference.
     *
     * @param array<int, int> $objectMap
     */
    private function writePageObject(int $objNum, PdfDictionary $pageDict, array $objectMap, int $rotation, int $parentObjNum): string
    {
        $content = "{$objNum} 0 obj\n<<\n";
        $content .= "/Type /Page\n";

        // Write rotation if non-zero
        if ($rotation !== 0) {
            $content .= "/Rotate {$rotation}\n";
        }

        $skipKeys = ['Type', 'Parent', 'Rotate'];

        // Write remaining page dictionary entries
        foreach ($pageDict->getValue() as $key => $value) {
            if (in_array($key, $skipKeys, true)) {
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
