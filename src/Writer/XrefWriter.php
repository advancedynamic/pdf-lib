<?php

declare(strict_types=1);

namespace PdfLib\Writer;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfStream;

/**
 * Writes PDF cross-reference tables and streams.
 */
final class XrefWriter
{
    /**
     * Use traditional xref table format.
     */
    public const FORMAT_TABLE = 'table';

    /**
     * Use cross-reference stream format (PDF 1.5+).
     */
    public const FORMAT_STREAM = 'stream';

    private string $format = self::FORMAT_TABLE;

    /**
     * Set the xref format to use.
     */
    public function setFormat(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Write cross-reference section and trailer.
     *
     * @param array<int, array{offset: int, generation: int, inUse: bool}> $entries
     */
    public function write(
        array $entries,
        PdfDictionary $trailer,
        int $startxref
    ): string {
        if ($this->format === self::FORMAT_STREAM) {
            return $this->writeXrefStream($entries, $trailer, $startxref);
        }

        return $this->writeXrefTable($entries, $trailer);
    }

    /**
     * Write traditional xref table format.
     *
     * @param array<int, array{offset: int, generation: int, inUse: bool}> $entries
     */
    private function writeXrefTable(array $entries, PdfDictionary $trailer): string
    {
        // Sort entries by object number
        ksort($entries);

        // Group into contiguous subsections
        $subsections = $this->groupIntoSubsections($entries);

        $output = "xref\n";

        foreach ($subsections as $subsection) {
            $firstObjNum = $subsection['first'];
            $count = count($subsection['entries']);
            $output .= "$firstObjNum $count\n";

            foreach ($subsection['entries'] as $entry) {
                $offset = str_pad((string) $entry['offset'], 10, '0', STR_PAD_LEFT);
                $generation = str_pad((string) $entry['generation'], 5, '0', STR_PAD_LEFT);
                $flag = $entry['inUse'] ? 'n' : 'f';
                $output .= "$offset $generation $flag \n"; // Note: 20 bytes including trailing space and newline
            }
        }

        // Write trailer
        $output .= "trailer\n";
        $output .= $trailer->toPdfString() . "\n";

        return $output;
    }

    /**
     * Write xref stream format (PDF 1.5+).
     *
     * @param array<int, array{offset: int, generation: int, inUse: bool}> $entries
     */
    private function writeXrefStream(
        array $entries,
        PdfDictionary $trailer,
        int $startxref
    ): string {
        // Sort entries by object number
        ksort($entries);

        // Calculate field widths
        $maxOffset = 0;
        foreach ($entries as $entry) {
            $maxOffset = max($maxOffset, $entry['offset']);
        }

        $w1 = 1; // Type field (always 1 byte)
        $w2 = $this->bytesNeeded($maxOffset);
        $w3 = 2; // Generation number (usually 2 bytes is enough)

        // Group into subsections
        $subsections = $this->groupIntoSubsections($entries);

        // Build index array
        $index = [];
        foreach ($subsections as $subsection) {
            $index[] = $subsection['first'];
            $index[] = count($subsection['entries']);
        }

        // Build stream data
        $streamData = '';
        foreach ($subsections as $subsection) {
            foreach ($subsection['entries'] as $entry) {
                // Type: 0 = free, 1 = in use, 2 = compressed
                $type = $entry['inUse'] ? 1 : 0;
                $streamData .= chr($type);

                // Field 2: offset (for type 1) or next free object (for type 0)
                $streamData .= $this->encodeInt($entry['offset'], $w2);

                // Field 3: generation number
                $streamData .= $this->encodeInt($entry['generation'], $w3);
            }
        }

        // Compress stream data
        $compressedData = gzcompress($streamData, 9);

        // Build xref stream dictionary
        $xrefDict = new PdfDictionary();
        $xrefDict->set('Type', PdfName::create('XRef'));
        $xrefDict->set('Size', PdfNumber::int(max(array_keys($entries)) + 1));
        $xrefDict->set('W', PdfArray::fromValues([$w1, $w2, $w3]));
        $xrefDict->set('Index', PdfArray::fromValues($index));
        $xrefDict->set('Filter', PdfName::create('FlateDecode'));
        $xrefDict->set('Length', PdfNumber::int(strlen($compressedData)));

        // Merge trailer entries
        foreach ($trailer->getValue() as $key => $value) {
            if (!in_array($key, ['Size', 'Prev'], true)) {
                $xrefDict->set($key, $value);
            }
        }

        // Handle /Prev pointer
        if ($trailer->has('Prev')) {
            $xrefDict->set('Prev', $trailer->get('Prev'));
        }

        // Build xref stream object
        // The xref stream is also an indirect object
        $nextObjNum = max(array_keys($entries)) + 1;

        $output = "$nextObjNum 0 obj\n";
        $output .= $xrefDict->toPdfString() . "\n";
        $output .= "stream\n";
        $output .= $compressedData;
        $output .= "\nendstream\n";
        $output .= "endobj\n";

        return $output;
    }

    /**
     * Group entries into contiguous subsections.
     *
     * @param array<int, array{offset: int, generation: int, inUse: bool}> $entries
     * @return array<int, array{first: int, entries: array<int, array{offset: int, generation: int, inUse: bool}>}>
     */
    private function groupIntoSubsections(array $entries): array
    {
        if (empty($entries)) {
            return [];
        }

        $subsections = [];
        $objNums = array_keys($entries);
        sort($objNums);

        $currentFirst = $objNums[0];
        $currentEntries = [];
        $lastObjNum = $currentFirst - 1;

        foreach ($objNums as $objNum) {
            if ($objNum !== $lastObjNum + 1) {
                // Start new subsection
                if (!empty($currentEntries)) {
                    $subsections[] = [
                        'first' => $currentFirst,
                        'entries' => $currentEntries,
                    ];
                }
                $currentFirst = $objNum;
                $currentEntries = [];
            }

            $currentEntries[] = $entries[$objNum];
            $lastObjNum = $objNum;
        }

        // Add final subsection
        if (!empty($currentEntries)) {
            $subsections[] = [
                'first' => $currentFirst,
                'entries' => $currentEntries,
            ];
        }

        return $subsections;
    }

    /**
     * Calculate bytes needed to represent a value.
     */
    private function bytesNeeded(int $value): int
    {
        if ($value <= 0xFF) {
            return 1;
        }
        if ($value <= 0xFFFF) {
            return 2;
        }
        if ($value <= 0xFFFFFF) {
            return 3;
        }
        return 4;
    }

    /**
     * Encode an integer as big-endian bytes.
     */
    private function encodeInt(int $value, int $bytes): string
    {
        $result = '';
        for ($i = $bytes - 1; $i >= 0; $i--) {
            $result .= chr(($value >> ($i * 8)) & 0xFF);
        }
        return $result;
    }

    /**
     * Create a basic trailer dictionary.
     */
    public function createTrailer(
        int $size,
        PdfReference $root,
        ?PdfReference $info = null,
        ?string $id = null,
        ?PdfReference $encrypt = null
    ): PdfDictionary {
        $trailer = new PdfDictionary();
        $trailer->set('Size', PdfNumber::int($size));
        $trailer->set('Root', $root);

        if ($info !== null) {
            $trailer->set('Info', $info);
        }

        if ($id !== null) {
            // ID is an array of two identical strings for new documents
            $idArray = PdfArray::fromValues([
                \PdfLib\Parser\Object\PdfString::hex($id),
                \PdfLib\Parser\Object\PdfString::hex($id),
            ]);
            $trailer->set('ID', $idArray);
        }

        if ($encrypt !== null) {
            $trailer->set('Encrypt', $encrypt);
        }

        return $trailer;
    }

    /**
     * Generate a document ID.
     */
    public function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
