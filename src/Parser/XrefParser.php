<?php

declare(strict_types=1);

namespace PdfLib\Parser;

use PdfLib\Exception\ParseException;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfStream;

/**
 * Parses PDF cross-reference tables and streams.
 *
 * The cross-reference table maps object numbers to their byte offsets in the file.
 * PDF 1.5+ supports cross-reference streams as an alternative to traditional xref tables.
 */
final class XrefParser
{
    /**
     * Cross-reference entry for an object in use.
     */
    public const ENTRY_IN_USE = 'n';

    /**
     * Cross-reference entry for a free object.
     */
    public const ENTRY_FREE = 'f';

    /**
     * Cross-reference entry for a compressed object (in object stream).
     */
    public const ENTRY_COMPRESSED = 'c';

    public function __construct(
        private readonly Lexer $lexer,
        private readonly ObjectParser $objectParser
    ) {
    }

    /**
     * Parse cross-reference table or stream at current position.
     *
     * @return array{entries: array<int, array{type: string, offset: int, generation: int, streamObject?: int, index?: int}>, trailer: PdfDictionary}
     */
    public function parse(): array
    {
        $this->lexer->skipWhitespace();

        // Check if this is a traditional xref table or an xref stream
        if ($this->lexer->matchKeyword('xref')) {
            return $this->parseTraditionalXref();
        }

        // Must be an xref stream (PDF 1.5+)
        return $this->parseXrefStream();
    }

    /**
     * Parse traditional xref table format.
     *
     * @return array{entries: array<int, array{type: string, offset: int, generation: int}>, trailer: PdfDictionary}
     */
    private function parseTraditionalXref(): array
    {
        $this->lexer->skipKeyword('xref');
        $this->lexer->skipWhitespace();

        $entries = [];

        // Parse subsections
        while (true) {
            $this->lexer->skipWhitespace();

            // Check for trailer keyword
            if ($this->lexer->matchKeyword('trailer')) {
                break;
            }

            // Read first object number and count
            $firstObject = $this->lexer->readNumber();
            if (!is_int($firstObject)) {
                break;
            }

            $this->lexer->skipWhitespace();
            $count = $this->lexer->readNumber();
            if (!is_int($count)) {
                throw ParseException::invalidXref('Invalid entry count');
            }

            // Parse entries
            for ($i = 0; $i < $count; $i++) {
                $this->lexer->skipWhitespace();

                // Read 10-digit offset
                $offsetStr = $this->lexer->readBytes(10);
                $offset = (int) $offsetStr;

                $this->lexer->read(); // Space

                // Read 5-digit generation
                $genStr = $this->lexer->readBytes(5);
                $generation = (int) $genStr;

                $this->lexer->read(); // Space

                // Read type (n or f)
                $type = $this->lexer->read();

                // Skip end of line
                $this->lexer->skipWhitespace();

                $objectNumber = $firstObject + $i;
                $entries[$objectNumber] = [
                    'type' => $type === 'n' ? self::ENTRY_IN_USE : self::ENTRY_FREE,
                    'offset' => $offset,
                    'generation' => $generation,
                ];
            }
        }

        // Parse trailer dictionary
        $this->lexer->skipKeyword('trailer');
        $this->lexer->skipWhitespace();

        $trailer = $this->objectParser->parseDictionary();

        return [
            'entries' => $entries,
            'trailer' => $trailer,
        ];
    }

    /**
     * Parse cross-reference stream (PDF 1.5+).
     *
     * @return array{entries: array<int, array{type: string, offset: int, generation: int, streamObject?: int, index?: int}>, trailer: PdfDictionary}
     */
    private function parseXrefStream(): array
    {
        // Parse the stream object
        $result = $this->objectParser->parseIndirectObject();
        $stream = $result['object'];

        if (!$stream instanceof PdfStream) {
            throw ParseException::invalidXref('Expected xref stream');
        }

        $dict = $stream->getDictionary();

        // Verify type
        $type = $dict->getType();
        if ($type !== 'XRef') {
            throw ParseException::invalidXref('Invalid xref stream type');
        }

        // Get W array (field widths)
        $wObj = $dict->get('W');
        if (!$wObj instanceof \PdfLib\Parser\Object\PdfArray) {
            throw ParseException::invalidXref('Missing W array in xref stream');
        }
        $w = $wObj->toArray();

        // Get Index array (optional)
        $indexObj = $dict->get('Index');
        $subsections = [];
        if ($indexObj instanceof \PdfLib\Parser\Object\PdfArray) {
            $indexArr = $indexObj->toArray();
            for ($i = 0; $i < count($indexArr); $i += 2) {
                $subsections[] = [
                    'first' => (int) $indexArr[$i],
                    'count' => (int) $indexArr[$i + 1],
                ];
            }
        } else {
            // Default: single subsection starting at 0
            $sizeObj = $dict->get('Size');
            $size = $sizeObj instanceof PdfNumber ? $sizeObj->toInt() : 0;
            $subsections[] = [
                'first' => 0,
                'count' => $size,
            ];
        }

        // Decode stream data
        $data = $this->decodeStreamData($stream);
        $entries = $this->parseXrefStreamEntries($data, $w, $subsections);

        return [
            'entries' => $entries,
            'trailer' => $dict,
        ];
    }

    /**
     * Parse entries from xref stream data.
     *
     * @param string $data Decoded stream data
     * @param array<int, int> $w Field widths
     * @param array<int, array{first: int, count: int}> $subsections
     * @return array<int, array{type: string, offset: int, generation: int, streamObject?: int, index?: int}>
     */
    private function parseXrefStreamEntries(string $data, array $w, array $subsections): array
    {
        $entries = [];
        $offset = 0;
        $entrySize = array_sum($w);

        foreach ($subsections as $subsection) {
            $firstObject = $subsection['first'];
            $count = $subsection['count'];

            for ($i = 0; $i < $count; $i++) {
                $objectNumber = $firstObject + $i;

                // Read fields
                $field1 = $this->readField($data, $offset, $w[0]);
                $offset += $w[0];

                $field2 = $this->readField($data, $offset, $w[1]);
                $offset += $w[1];

                $field3 = $this->readField($data, $offset, $w[2]);
                $offset += $w[2];

                // Default type is 1 (in use) if W[0] is 0
                if ($w[0] === 0) {
                    $field1 = 1;
                }

                // Interpret fields based on type
                $entry = match ($field1) {
                    0 => [
                        'type' => self::ENTRY_FREE,
                        'offset' => $field2, // Next free object number
                        'generation' => $field3, // Generation for next use
                    ],
                    1 => [
                        'type' => self::ENTRY_IN_USE,
                        'offset' => $field2, // Byte offset
                        'generation' => $field3, // Generation number
                    ],
                    2 => [
                        'type' => self::ENTRY_COMPRESSED,
                        'offset' => 0,
                        'generation' => 0,
                        'streamObject' => $field2, // Object stream number
                        'index' => $field3, // Index within stream
                    ],
                    default => throw ParseException::invalidXref("Unknown xref entry type: $field1"),
                };

                $entries[$objectNumber] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Read a field value from stream data.
     */
    private function readField(string $data, int $offset, int $width): int
    {
        if ($width === 0) {
            return 0;
        }

        $value = 0;
        for ($i = 0; $i < $width; $i++) {
            $value = ($value << 8) | ord($data[$offset + $i]);
        }

        return $value;
    }

    /**
     * Decode stream data (handle filters).
     */
    private function decodeStreamData(PdfStream $stream): string
    {
        $data = $stream->getData();
        $filters = $stream->getFilters();

        // For now, only handle FlateDecode
        foreach ($filters as $filter) {
            $data = match ($filter) {
                'FlateDecode' => $this->decodeFlateDecode($data),
                default => $data, // Skip unknown filters for now
            };
        }

        return $data;
    }

    /**
     * Decode FlateDecode (zlib) compressed data.
     */
    private function decodeFlateDecode(string $data): string
    {
        $decoded = @gzuncompress($data);
        if ($decoded === false) {
            // Try with zlib header
            $decoded = @gzinflate($data);
        }
        if ($decoded === false) {
            throw ParseException::corruptedFile('Failed to decompress FlateDecode stream');
        }
        return $decoded;
    }

    /**
     * Find the startxref position in the file.
     */
    public function findStartXref(): int
    {
        // Search backwards from end of file for 'startxref'
        $searchLength = min(1024, $this->lexer->getLength());
        $endPos = $this->lexer->getLength();
        $startPos = $endPos - $searchLength;

        $pos = $this->lexer->searchBackward('startxref', $endPos);
        if ($pos === -1) {
            throw ParseException::corruptedFile('Could not find startxref marker');
        }

        // Position after 'startxref' keyword
        $this->lexer->setPosition($pos + 9);
        $this->lexer->skipWhitespace();

        // Read the xref offset
        $xrefOffset = $this->lexer->readNumber();
        if (!is_int($xrefOffset)) {
            throw ParseException::corruptedFile('Invalid startxref value');
        }

        return $xrefOffset;
    }
}
