<?php

declare(strict_types=1);

namespace PdfLib\Parser;

use PdfLib\Exception\ParseException;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfStream;

/**
 * Main PDF parser - reads and parses PDF documents.
 *
 * Supports PDF 1.4 through 2.0, including:
 * - Traditional xref tables
 * - Cross-reference streams (PDF 1.5+)
 * - Object streams (PDF 1.5+)
 * - Linearized PDFs
 * - Incremental updates
 */
final class PdfParser
{
    private Lexer $lexer;
    private ObjectParser $objectParser;
    private XrefParser $xrefParser;
    private StreamParser $streamParser;

    /**
     * Cross-reference entries mapping object numbers to positions/info.
     *
     * @var array<int, array{type: string, offset: int, generation: int, streamObject?: int, index?: int}>
     */
    private array $xref = [];

    /**
     * Trailer dictionary.
     */
    private ?PdfDictionary $trailer = null;

    /**
     * Cache of parsed objects.
     *
     * @var array<string, PdfObject>
     */
    private array $objectCache = [];

    /**
     * Parsed object streams cache.
     *
     * @var array<int, array<int, PdfObject>>
     */
    private array $objectStreamCache = [];

    /**
     * PDF version.
     */
    private string $version = '1.4';

    public function __construct()
    {
        $this->streamParser = new StreamParser();
    }

    /**
     * Parse a PDF file.
     */
    public static function parseFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new ParseException("File not found: $path");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new ParseException("Could not read file: $path");
        }

        return self::parseString($content);
    }

    /**
     * Parse a PDF from string content.
     */
    public static function parseString(string $content): self
    {
        $parser = new self();
        $parser->parse($content);
        return $parser;
    }

    /**
     * Parse the PDF content.
     */
    public function parse(string $content): void
    {
        $this->lexer = new Lexer($content);
        $this->objectParser = new ObjectParser($this->lexer);
        $this->xrefParser = new XrefParser($this->lexer, $this->objectParser);

        // Verify PDF header
        $this->parseHeader();

        // Find and parse xref
        $xrefOffset = $this->xrefParser->findStartXref();
        $this->parseXrefChain($xrefOffset);
    }

    /**
     * Parse and verify PDF header.
     */
    private function parseHeader(): void
    {
        $this->lexer->setPosition(0);

        // Read header line
        $line = $this->lexer->readLine();

        if (!preg_match('/^%PDF-(\d+\.\d+)/', $line, $matches)) {
            throw ParseException::corruptedFile('Invalid PDF header');
        }

        $this->version = $matches[1];
    }

    /**
     * Parse xref chain (handles incremental updates).
     */
    private function parseXrefChain(int $xrefOffset): void
    {
        $visited = [];

        while ($xrefOffset > 0 && !isset($visited[$xrefOffset])) {
            $visited[$xrefOffset] = true;

            $this->lexer->setPosition($xrefOffset);
            $result = $this->xrefParser->parse();

            // Merge xref entries (newer entries take precedence)
            foreach ($result['entries'] as $objNum => $entry) {
                if (!isset($this->xref[$objNum])) {
                    $this->xref[$objNum] = $entry;
                }
            }

            // Use first trailer as main trailer
            if ($this->trailer === null) {
                $this->trailer = $result['trailer'];
            }

            // Follow /Prev pointer for previous xref
            $prevObj = $result['trailer']->get('Prev');
            if ($prevObj instanceof PdfNumber) {
                $xrefOffset = $prevObj->toInt();
            } else {
                break;
            }
        }
    }

    /**
     * Get the PDF version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get the trailer dictionary.
     */
    public function getTrailer(): ?PdfDictionary
    {
        return $this->trailer;
    }

    /**
     * Get the document catalog.
     */
    public function getCatalog(): ?PdfDictionary
    {
        if ($this->trailer === null) {
            return null;
        }

        $root = $this->trailer->get('Root');
        if ($root instanceof PdfReference) {
            $catalog = $this->resolveReference($root);
            if ($catalog instanceof PdfDictionary) {
                return $catalog;
            }
        }

        return null;
    }

    /**
     * Get document info dictionary.
     */
    public function getInfo(): ?PdfDictionary
    {
        if ($this->trailer === null) {
            return null;
        }

        $info = $this->trailer->get('Info');
        if ($info instanceof PdfReference) {
            $infoDict = $this->resolveReference($info);
            if ($infoDict instanceof PdfDictionary) {
                return $infoDict;
            }
        } elseif ($info instanceof PdfDictionary) {
            return $info;
        }

        return null;
    }

    /**
     * Get total number of objects.
     */
    public function getObjectCount(): int
    {
        return count($this->xref);
    }

    /**
     * Get the page count.
     */
    public function getPageCount(): int
    {
        $catalog = $this->getCatalog();
        if ($catalog === null) {
            return 0;
        }

        $pages = $catalog->get('Pages');
        if ($pages instanceof PdfReference) {
            $pagesDict = $this->resolveReference($pages);
            if ($pagesDict instanceof PdfDictionary) {
                $count = $pagesDict->get('Count');
                if ($count instanceof PdfNumber) {
                    return $count->toInt();
                }
            }
        }

        return 0;
    }

    /**
     * Get a specific page dictionary.
     */
    public function getPage(int $pageNumber): ?PdfDictionary
    {
        $catalog = $this->getCatalog();
        if ($catalog === null) {
            return null;
        }

        $pages = $catalog->get('Pages');
        if ($pages instanceof PdfReference) {
            $pagesDict = $this->resolveReference($pages);
            if ($pagesDict instanceof PdfDictionary) {
                return $this->findPage($pagesDict, $pageNumber, 0);
            }
        }

        return null;
    }

    /**
     * Find a page in the page tree.
     */
    private function findPage(PdfDictionary $node, int $targetPage, int $currentPage): ?PdfDictionary
    {
        $type = $node->getType();

        if ($type === 'Page') {
            if ($currentPage === $targetPage) {
                return $node;
            }
            return null;
        }

        if ($type === 'Pages') {
            $kids = $node->get('Kids');
            if (!$kids instanceof PdfArray) {
                return null;
            }

            foreach ($kids as $kid) {
                if ($kid instanceof PdfReference) {
                    $kidDict = $this->resolveReference($kid);
                    if (!$kidDict instanceof PdfDictionary) {
                        continue;
                    }

                    $kidType = $kidDict->getType();

                    if ($kidType === 'Page') {
                        if ($currentPage === $targetPage) {
                            return $kidDict;
                        }
                        $currentPage++;
                    } elseif ($kidType === 'Pages') {
                        $count = $kidDict->get('Count');
                        $kidCount = $count instanceof PdfNumber ? $count->toInt() : 0;

                        if ($targetPage < $currentPage + $kidCount) {
                            return $this->findPage($kidDict, $targetPage, $currentPage);
                        }
                        $currentPage += $kidCount;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get all page dictionaries.
     *
     * @return array<int, PdfDictionary>
     */
    public function getPages(): array
    {
        $pages = [];
        $pageCount = $this->getPageCount();

        for ($i = 0; $i < $pageCount; $i++) {
            $page = $this->getPage($i);
            if ($page !== null) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * Get an object by object number.
     */
    public function getObject(int $objectNumber, int $generationNumber = 0): ?PdfObject
    {
        $key = "{$objectNumber}_{$generationNumber}";

        // Check cache
        if (isset($this->objectCache[$key])) {
            return $this->objectCache[$key];
        }

        // Check xref
        if (!isset($this->xref[$objectNumber])) {
            return null;
        }

        $entry = $this->xref[$objectNumber];

        // Skip free objects
        if ($entry['type'] === XrefParser::ENTRY_FREE) {
            return null;
        }

        // Handle compressed objects
        if ($entry['type'] === XrefParser::ENTRY_COMPRESSED) {
            return $this->getCompressedObject($objectNumber, $entry);
        }

        // Parse object at offset
        $this->lexer->setPosition($entry['offset']);
        $result = $this->objectParser->parseIndirectObject();

        $object = $result['object'];
        $this->objectCache[$key] = $object;

        return $object;
    }

    /**
     * Get a compressed object from an object stream.
     *
     * @param array{streamObject: int, index: int} $entry
     */
    private function getCompressedObject(int $objectNumber, array $entry): ?PdfObject
    {
        $streamObjNum = $entry['streamObject'];
        $index = $entry['index'];

        // Check if we've already parsed this object stream
        if (!isset($this->objectStreamCache[$streamObjNum])) {
            $this->parseObjectStream($streamObjNum);
        }

        return $this->objectStreamCache[$streamObjNum][$index] ?? null;
    }

    /**
     * Parse an object stream.
     */
    private function parseObjectStream(int $streamObjNum): void
    {
        $streamObj = $this->getObject($streamObjNum);
        if (!$streamObj instanceof PdfStream) {
            return;
        }

        $dict = $streamObj->getDictionary();

        // Get number of objects
        $nObj = $dict->get('N');
        $n = $nObj instanceof PdfNumber ? $nObj->toInt() : 0;

        // Get offset to first object
        $firstObj = $dict->get('First');
        $first = $firstObj instanceof PdfNumber ? $firstObj->toInt() : 0;

        // Decode stream
        $data = $this->streamParser->decode($streamObj);

        // Parse object numbers and offsets from header
        $headerLexer = new Lexer(substr($data, 0, $first));
        $objects = [];

        for ($i = 0; $i < $n; $i++) {
            $headerLexer->skipWhitespace();
            $objNum = $headerLexer->readNumber();
            $headerLexer->skipWhitespace();
            $offset = $headerLexer->readNumber();

            if (is_int($objNum) && is_int($offset)) {
                $objects[] = [
                    'objNum' => $objNum,
                    'offset' => $first + $offset,
                ];
            }
        }

        // Parse each object
        $objectLexer = new Lexer($data);
        $objectParser = new ObjectParser($objectLexer);

        $this->objectStreamCache[$streamObjNum] = [];

        for ($i = 0; $i < count($objects); $i++) {
            $objectLexer->setPosition($objects[$i]['offset']);
            $obj = $objectParser->parse();
            $obj->setIndirect($objects[$i]['objNum'], 0);
            $this->objectStreamCache[$streamObjNum][$i] = $obj;

            // Also cache by object number
            $key = "{$objects[$i]['objNum']}_0";
            $this->objectCache[$key] = $obj;
        }
    }

    /**
     * Resolve an indirect reference to its object.
     */
    public function resolveReference(PdfReference $ref): ?PdfObject
    {
        return $this->getObject($ref->getObjectNumber(), $ref->getGenerationNumber());
    }

    /**
     * Recursively resolve all references in an object.
     */
    public function resolveAll(PdfObject $object): PdfObject
    {
        if ($object instanceof PdfReference) {
            $resolved = $this->resolveReference($object);
            if ($resolved !== null) {
                return $this->resolveAll($resolved);
            }
            return $object;
        }

        if ($object instanceof PdfDictionary) {
            $entries = [];
            foreach ($object->getValue() as $key => $value) {
                $entries[$key] = $this->resolveAll($value);
            }
            return new PdfDictionary($entries);
        }

        if ($object instanceof PdfArray) {
            $items = [];
            foreach ($object->getValue() as $value) {
                $items[] = $this->resolveAll($value);
            }
            return new PdfArray($items);
        }

        return $object;
    }

    /**
     * Decode a content stream.
     */
    public function decodeStream(PdfStream $stream): string
    {
        return $this->streamParser->decode($stream);
    }

    /**
     * Get page content stream(s) decoded.
     */
    public function getPageContent(int $pageNumber): string
    {
        $page = $this->getPage($pageNumber);
        if ($page === null) {
            return '';
        }

        $contents = $page->get('Contents');
        if ($contents === null) {
            return '';
        }

        // Resolve reference
        if ($contents instanceof PdfReference) {
            $contents = $this->resolveReference($contents);
        }

        // Single stream
        if ($contents instanceof PdfStream) {
            return $this->streamParser->decode($contents);
        }

        // Array of streams
        if ($contents instanceof PdfArray) {
            $result = '';
            foreach ($contents as $item) {
                if ($item instanceof PdfReference) {
                    $stream = $this->resolveReference($item);
                    if ($stream instanceof PdfStream) {
                        $result .= $this->streamParser->decode($stream) . "\n";
                    }
                } elseif ($item instanceof PdfStream) {
                    $result .= $this->streamParser->decode($item) . "\n";
                }
            }
            return $result;
        }

        return '';
    }

    /**
     * Check if the PDF is encrypted.
     */
    public function isEncrypted(): bool
    {
        if ($this->trailer === null) {
            return false;
        }

        return $this->trailer->has('Encrypt');
    }

    /**
     * Get encryption dictionary.
     */
    public function getEncryptDict(): ?PdfDictionary
    {
        if ($this->trailer === null) {
            return null;
        }

        $encrypt = $this->trailer->get('Encrypt');
        if ($encrypt instanceof PdfReference) {
            $encryptDict = $this->resolveReference($encrypt);
            if ($encryptDict instanceof PdfDictionary) {
                return $encryptDict;
            }
        } elseif ($encrypt instanceof PdfDictionary) {
            return $encrypt;
        }

        return null;
    }

    /**
     * Get all cross-reference entries.
     *
     * @return array<int, array{type: string, offset: int, generation: int, streamObject?: int, index?: int}>
     */
    public function getXref(): array
    {
        return $this->xref;
    }

    /**
     * Check if PDF is linearized.
     */
    public function isLinearized(): bool
    {
        // Linearization dict should be the first object
        $this->lexer->setPosition(0);
        $this->lexer->readLine(); // Skip header
        $this->lexer->skipWhitespace();

        // Look for linearization dictionary
        if ($this->lexer->peek() === null || !ctype_digit($this->lexer->peek())) {
            return false;
        }

        try {
            $result = $this->objectParser->parseIndirectObject();
            $obj = $result['object'];

            if ($obj instanceof PdfDictionary && $obj->has('Linearized')) {
                return true;
            }
        } catch (ParseException) {
            // Not linearized
        }

        return false;
    }

    /**
     * Get next available object ID.
     */
    public function getNextObjectId(): int
    {
        if (empty($this->xref)) {
            return 1;
        }

        return max(array_keys($this->xref)) + 1;
    }

    /**
     * Get page reference by index (0-based).
     *
     * @return array{id: int, gen: int}|null
     */
    public function getPageReference(int $index): ?array
    {
        $pages = $this->getPages();

        if (!isset($pages[$index])) {
            return null;
        }

        // Search for page object in xref
        foreach ($this->xref as $id => $entry) {
            if ($entry['type'] !== 'n') {
                continue;
            }

            $obj = $this->getObject($id);
            if ($obj === $pages[$index]) {
                return ['id' => $id, 'gen' => $entry['generation'] ?? 0];
            }
        }

        // If direct search failed, try to find through page tree
        $catalog = $this->getCatalog();
        if ($catalog === null) {
            return null;
        }

        $pagesRef = $catalog->get('Pages');
        if ($pagesRef instanceof PdfReference) {
            return $this->findPageReference($pagesRef, $index, 0);
        }

        return null;
    }

    /**
     * Find page reference recursively in page tree.
     *
     * @return array{id: int, gen: int}|null
     */
    private function findPageReference(PdfReference $nodeRef, int $targetIndex, int $currentIndex): ?array
    {
        $node = $this->resolveReference($nodeRef);
        if (!$node instanceof PdfDictionary) {
            return null;
        }

        $type = $node->get('Type');
        $typeName = $type instanceof PdfName ? $type->getValue() : '';

        if ($typeName === 'Page') {
            if ($currentIndex === $targetIndex) {
                return ['id' => $nodeRef->getObjectNumber(), 'gen' => $nodeRef->getGenerationNumber()];
            }
            return null;
        }

        // Pages node
        $kids = $node->get('Kids');
        if (!$kids instanceof PdfArray) {
            return null;
        }

        $count = 0;
        foreach ($kids->getItems() as $kidRef) {
            if (!$kidRef instanceof PdfReference) {
                continue;
            }

            $kid = $this->resolveReference($kidRef);
            if (!$kid instanceof PdfDictionary) {
                continue;
            }

            $kidType = $kid->get('Type');
            $kidTypeName = $kidType instanceof PdfName ? $kidType->getValue() : '';

            if ($kidTypeName === 'Page') {
                if ($currentIndex + $count === $targetIndex) {
                    return ['id' => $kidRef->getObjectNumber(), 'gen' => $kidRef->getGenerationNumber()];
                }
                $count++;
            } else {
                // Pages node - check count
                $kidCount = $kid->get('Count');
                $numPages = $kidCount instanceof PdfNumber ? (int) $kidCount->getValue() : 0;

                if ($currentIndex + $count + $numPages > $targetIndex) {
                    // Target is in this subtree
                    $result = $this->findPageReference($kidRef, $targetIndex, $currentIndex + $count);
                    if ($result !== null) {
                        return $result;
                    }
                }
                $count += $numPages;
            }
        }

        return null;
    }

    /**
     * Get root catalog reference.
     *
     * @return array{id: int, gen: int}|null
     */
    public function getRootReference(): ?array
    {
        if ($this->trailer === null) {
            return null;
        }

        $root = $this->trailer->get('Root');
        if ($root instanceof PdfReference) {
            return ['id' => $root->getObjectNumber(), 'gen' => $root->getGenerationNumber()];
        }

        return null;
    }

    /**
     * Get document root catalog.
     */
    public function getRoot(): ?PdfDictionary
    {
        return $this->getCatalog();
    }
}
