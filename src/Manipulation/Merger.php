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
 * PDF Merger - Combine multiple PDFs into one.
 *
 * Properly copies page content streams, resources, fonts, and images.
 *
 * @example
 * ```php
 * $merger = new Merger();
 * $merger->addFile('doc1.pdf')
 *        ->addFile('doc2.pdf', pages: [1, 3, 5])
 *        ->addFile('doc3.pdf', pages: '1-10')
 *        ->save('merged.pdf');
 * ```
 */
final class Merger
{
    /** @var array<int, array{content: string, pages: array<int>|string}> */
    private array $sources = [];

    private string $version = '1.7';

    /**
     * Add a PDF file to merge.
     *
     * @param string $filePath Path to PDF file
     * @param array<int>|string $pages Page numbers (1-indexed) or 'all'
     */
    public function addFile(string $filePath, array|string $pages = 'all'): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("PDF file not found: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Could not read PDF file: $filePath");
        }

        return $this->addContent($content, $pages);
    }

    /**
     * Add PDF content to merge.
     *
     * @param string $content Raw PDF content
     * @param array<int>|string $pages Page numbers (1-indexed) or 'all'
     */
    public function addContent(string $content, array|string $pages = 'all'): self
    {
        if (!str_starts_with($content, '%PDF-')) {
            throw new \InvalidArgumentException('Invalid PDF content');
        }

        $this->sources[] = [
            'content' => $content,
            'pages' => $pages,
        ];

        return $this;
    }

    /**
     * Add a PdfDocument to merge.
     *
     * @param array<int>|string $pages Page numbers (1-indexed) or 'all'
     */
    public function addDocument(\PdfLib\Document\PdfDocument $document, array|string $pages = 'all'): self
    {
        return $this->addContent($document->render(), $pages);
    }

    /**
     * Set output PDF version.
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Merge all sources and return PDF content.
     */
    public function merge(): string
    {
        if (empty($this->sources)) {
            throw new \RuntimeException('No PDF sources to merge');
        }

        // Collect all pages with their parsers
        $allPages = [];

        foreach ($this->sources as $source) {
            $parser = PdfParser::parseString($source['content']);
            $pageCount = $parser->getPageCount();
            $pageNumbers = $this->resolvePages($source['pages'], $pageCount);

            foreach ($pageNumbers as $pageNum) {
                $index = $pageNum - 1; // Convert to 0-indexed
                $pageDict = $parser->getPage($index);
                if ($pageDict !== null) {
                    $allPages[] = [
                        'dict' => $pageDict,
                        'parser' => $parser,
                        'index' => $index,
                    ];
                }
            }
        }

        return $this->buildMergedPdf($allPages);
    }

    /**
     * Merge and save to file.
     */
    public function save(string $outputPath): bool
    {
        $content = $this->merge();
        return file_put_contents($outputPath, $content) !== false;
    }

    /**
     * Merge and save to file (alias for save).
     */
    public function mergeToFile(string $outputPath): bool
    {
        return $this->save($outputPath);
    }

    /**
     * Get source count.
     */
    public function getSourceCount(): int
    {
        return count($this->sources);
    }

    /**
     * Clear all sources.
     */
    public function clear(): self
    {
        $this->sources = [];
        return $this;
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

        // Parse range string like "1-5", "1,3,5", "1-3,5,7-9"
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

    /**
     * Build merged PDF from collected pages.
     *
     * @param array<int, array{dict: PdfDictionary, parser: PdfParser, index: int}> $pages
     */
    private function buildMergedPdf(array $pages): string
    {
        // First pass: count objects to pre-calculate Pages object number
        $totalObjects = 0;
        $pageObjectMaps = [];

        foreach ($pages as $pageIndex => $pageData) {
            $parser = $pageData['parser'];
            $pageDict = $pageData['dict'];
            $referencedObjects = $this->collectReferencedObjects($pageDict, $parser);
            $pageObjectMaps[$pageIndex] = [
                'referenced' => $referencedObjects,
                'parser' => $parser,
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
        foreach ($pageObjectMaps as $pageData) {
            $parser = $pageData['parser'];
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

            // Write page object with correct Parent
            $pageObjNum = $objectNum++;
            $pageRefs[] = $pageObjNum;
            $objects[$pageObjNum] = [
                'offset' => strlen($output),
            ];
            $content = $this->writePageObjectWithParent($pageObjNum, $pageDict, $objectMap, $pagesObjNum);
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
        $infoContent = "{$infoObjNum} 0 obj\n<<\n/Producer (PdfLib Merger)\n/CreationDate ({$date})\n>>\nendobj\n";
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
    private function collectReferencedObjects(PdfDictionary $pageDict, PdfParser $parser): array
    {
        $objects = [];
        $visited = [];

        $this->collectReferences($pageDict, $parser, $objects, $visited);

        return $objects;
    }

    /**
     * Recursively collect referenced objects.
     *
     * @param array<int, PdfObject> $objects
     * @param array<int, bool> $visited
     */
    private function collectReferences(PdfObject $obj, PdfParser $parser, array &$objects, array &$visited): void
    {
        if ($obj instanceof PdfReference) {
            $id = $obj->getObjectNumber();
            if (isset($visited[$id])) {
                return;
            }
            $visited[$id] = true;

            $resolved = $parser->resolveReference($obj);
            if ($resolved !== null) {
                $objects[$id] = $resolved;
                $this->collectReferences($resolved, $parser, $objects, $visited);
            }
        } elseif ($obj instanceof PdfDictionary) {
            foreach ($obj->getValue() as $key => $value) {
                // Skip Parent reference to avoid circular references
                if ($key === 'Parent') {
                    continue;
                }
                $this->collectReferences($value, $parser, $objects, $visited);
            }
        } elseif ($obj instanceof PdfArray) {
            foreach ($obj->getValue() as $item) {
                $this->collectReferences($item, $parser, $objects, $visited);
            }
        } elseif ($obj instanceof PdfStream) {
            $this->collectReferences($obj->getDictionary(), $parser, $objects, $visited);
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
     * Write a page object with correct Parent reference.
     *
     * @param array<int, int> $objectMap
     */
    private function writePageObjectWithParent(int $objNum, PdfDictionary $pageDict, array $objectMap, int $parentObjNum): string
    {
        $content = "{$objNum} 0 obj\n<<\n";
        $content .= "/Type /Page\n";

        foreach ($pageDict->getValue() as $key => $value) {
            // Skip Parent (we set it explicitly) and Type (already written)
            if ($key === 'Parent' || $key === 'Type') {
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

}
