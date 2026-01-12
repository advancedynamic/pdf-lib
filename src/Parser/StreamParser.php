<?php

declare(strict_types=1);

namespace PdfLib\Parser;

use PdfLib\Exception\ParseException;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfStream;

/**
 * Decodes PDF stream data using various filters.
 *
 * Supported filters:
 * - FlateDecode (zlib compression)
 * - ASCIIHexDecode (hexadecimal encoding)
 * - ASCII85Decode (base-85 encoding)
 * - LZWDecode (LZW compression)
 * - RunLengthDecode (run-length encoding)
 *
 * Filters can be chained (applied in sequence).
 */
final class StreamParser
{
    /**
     * Decode a stream's data using its filter(s).
     */
    public function decode(PdfStream $stream): string
    {
        $data = $stream->getData();

        if ($stream->isDecoded()) {
            return $data;
        }

        $filters = $stream->getFilters();
        $decodeParams = $this->getDecodeParams($stream);

        foreach ($filters as $index => $filter) {
            $params = $decodeParams[$index] ?? null;
            $data = $this->applyFilter($filter, $data, $params);
        }

        return $data;
    }

    /**
     * Encode data for a stream using specified filter(s).
     *
     * @param string|array<int, string> $filters
     */
    public function encode(string $data, string|array $filters): string
    {
        if (is_string($filters)) {
            $filters = [$filters];
        }

        // Apply filters in reverse order for encoding
        foreach (array_reverse($filters) as $filter) {
            $data = $this->applyEncode($filter, $data);
        }

        return $data;
    }

    /**
     * Apply a single decode filter.
     */
    private function applyFilter(string $filter, string $data, ?PdfDictionary $params): string
    {
        return match ($filter) {
            'FlateDecode', 'Fl' => $this->decodeFlateDecode($data, $params),
            'ASCIIHexDecode', 'AHx' => $this->decodeASCIIHex($data),
            'ASCII85Decode', 'A85' => $this->decodeASCII85($data),
            'LZWDecode', 'LZW' => $this->decodeLZW($data, $params),
            'RunLengthDecode', 'RL' => $this->decodeRunLength($data),
            'DCTDecode', 'DCT' => $data, // JPEG - pass through
            'JPXDecode' => $data, // JPEG2000 - pass through
            'CCITTFaxDecode', 'CCF' => $data, // CCITT fax - pass through for now
            'JBIG2Decode' => $data, // JBIG2 - pass through for now
            'Crypt' => $data, // Handled by encryption layer
            default => throw ParseException::unsupportedFeature("Filter: $filter"),
        };
    }

    /**
     * Apply a single encode filter.
     */
    private function applyEncode(string $filter, string $data): string
    {
        return match ($filter) {
            'FlateDecode', 'Fl' => $this->encodeFlateDecode($data),
            'ASCIIHexDecode', 'AHx' => $this->encodeASCIIHex($data),
            'ASCII85Decode', 'A85' => $this->encodeASCII85($data),
            default => throw ParseException::unsupportedFeature("Encode filter: $filter"),
        };
    }

    /**
     * Get decode parameters for each filter.
     *
     * @return array<int, PdfDictionary|null>
     */
    private function getDecodeParams(PdfStream $stream): array
    {
        $params = $stream->get('DecodeParms');

        if ($params === null) {
            return [];
        }

        if ($params instanceof PdfDictionary) {
            return [$params];
        }

        if ($params instanceof PdfArray) {
            $result = [];
            foreach ($params as $item) {
                $result[] = $item instanceof PdfDictionary ? $item : null;
            }
            return $result;
        }

        return [];
    }

    /**
     * Decode FlateDecode (zlib/deflate) compressed data.
     */
    private function decodeFlateDecode(string $data, ?PdfDictionary $params): string
    {
        // Try gzuncompress first (zlib format)
        $decoded = @gzuncompress($data);

        if ($decoded === false) {
            // Try raw deflate
            $decoded = @gzinflate($data);
        }

        if ($decoded === false) {
            // Try with different window sizes
            for ($wbits = 15; $wbits >= 8; $wbits--) {
                $decoded = @zlib_decode($data);
                if ($decoded !== false) {
                    break;
                }
            }
        }

        if ($decoded === false) {
            throw ParseException::corruptedFile('Failed to decode FlateDecode stream');
        }

        // Apply predictor if specified
        if ($params !== null) {
            $decoded = $this->applyPredictor($decoded, $params);
        }

        return $decoded;
    }

    /**
     * Encode data with FlateDecode (zlib).
     */
    private function encodeFlateDecode(string $data): string
    {
        $encoded = gzcompress($data, 9);
        if ($encoded === false) {
            throw ParseException::corruptedFile('Failed to encode FlateDecode stream');
        }
        return $encoded;
    }

    /**
     * Decode ASCIIHexDecode data.
     */
    private function decodeASCIIHex(string $data): string
    {
        // Remove whitespace and find end marker
        $data = preg_replace('/\s/', '', $data);
        $endPos = strpos($data, '>');
        if ($endPos !== false) {
            $data = substr($data, 0, $endPos);
        }

        // Pad odd-length data
        if (strlen($data) % 2 !== 0) {
            $data .= '0';
        }

        $decoded = hex2bin($data);
        if ($decoded === false) {
            throw ParseException::corruptedFile('Invalid ASCIIHexDecode data');
        }

        return $decoded;
    }

    /**
     * Encode data with ASCIIHexDecode.
     */
    private function encodeASCIIHex(string $data): string
    {
        return strtoupper(bin2hex($data)) . '>';
    }

    /**
     * Decode ASCII85Decode data.
     */
    private function decodeASCII85(string $data): string
    {
        // Remove whitespace
        $data = preg_replace('/\s/', '', $data);

        // Remove start/end markers
        if (str_starts_with($data, '<~')) {
            $data = substr($data, 2);
        }
        if (str_ends_with($data, '~>')) {
            $data = substr($data, 0, -2);
        }

        $result = '';
        $len = strlen($data);
        $i = 0;

        while ($i < $len) {
            // Handle 'z' shortcut for all zeros
            if ($data[$i] === 'z') {
                $result .= "\x00\x00\x00\x00";
                $i++;
                continue;
            }

            // Read up to 5 characters
            $chunk = '';
            $count = 0;
            while ($count < 5 && $i < $len && $data[$i] !== 'z') {
                $chunk .= $data[$i];
                $i++;
                $count++;
            }

            if ($count === 0) {
                break;
            }

            // Pad with 'u' if necessary
            $padded = str_pad($chunk, 5, 'u');

            // Decode 5 ASCII85 characters to 4 bytes
            $value = 0;
            for ($j = 0; $j < 5; $j++) {
                $value = $value * 85 + (ord($padded[$j]) - 33);
            }

            // Convert to bytes
            $bytes = pack('N', $value);

            // Only output the appropriate number of bytes
            $outputLen = $count - 1;
            if ($outputLen > 0) {
                $result .= substr($bytes, 0, $outputLen);
            }
        }

        return $result;
    }

    /**
     * Encode data with ASCII85Decode.
     */
    private function encodeASCII85(string $data): string
    {
        $result = '<~';
        $len = strlen($data);

        for ($i = 0; $i < $len; $i += 4) {
            $chunk = substr($data, $i, 4);
            $chunkLen = strlen($chunk);

            // Pad to 4 bytes
            $chunk = str_pad($chunk, 4, "\x00");

            // Convert to 32-bit value
            $value = unpack('N', $chunk)[1];

            // Handle all zeros
            if ($value === 0 && $chunkLen === 4) {
                $result .= 'z';
                continue;
            }

            // Encode to 5 ASCII85 characters
            $encoded = '';
            for ($j = 0; $j < 5; $j++) {
                $encoded = chr(($value % 85) + 33) . $encoded;
                $value = (int) ($value / 85);
            }

            // Only output the appropriate number of characters
            $outputLen = $chunkLen + 1;
            $result .= substr($encoded, 0, $outputLen);
        }

        return $result . '~>';
    }

    /**
     * Decode LZWDecode data.
     */
    private function decodeLZW(string $data, ?PdfDictionary $params): string
    {
        $earlyChange = 1;
        if ($params !== null) {
            $ec = $params->get('EarlyChange');
            if ($ec instanceof PdfNumber) {
                $earlyChange = $ec->toInt();
            }
        }

        $decoded = $this->lzwDecode($data, $earlyChange);

        if ($params !== null) {
            $decoded = $this->applyPredictor($decoded, $params);
        }

        return $decoded;
    }

    /**
     * LZW decompression implementation.
     */
    private function lzwDecode(string $data, int $earlyChange): string
    {
        $result = '';
        $bitPos = 0;
        $codeLen = 9;
        $clearCode = 256;
        $eodCode = 257;
        $nextCode = 258;

        // Initialize dictionary with single-byte codes
        $dictionary = [];
        for ($i = 0; $i < 256; $i++) {
            $dictionary[$i] = chr($i);
        }

        $prevCode = -1;

        while (true) {
            // Read next code
            $code = $this->readBits($data, $bitPos, $codeLen);
            $bitPos += $codeLen;

            if ($code === $eodCode) {
                break;
            }

            if ($code === $clearCode) {
                // Reset dictionary
                $codeLen = 9;
                $nextCode = 258;
                $dictionary = [];
                for ($i = 0; $i < 256; $i++) {
                    $dictionary[$i] = chr($i);
                }
                $prevCode = -1;
                continue;
            }

            $entry = '';
            if (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($code === $nextCode && $prevCode >= 0) {
                $entry = $dictionary[$prevCode] . $dictionary[$prevCode][0];
            } else {
                throw ParseException::corruptedFile('Invalid LZW code');
            }

            $result .= $entry;

            // Add new entry to dictionary
            if ($prevCode >= 0 && $nextCode < 4096) {
                $dictionary[$nextCode] = $dictionary[$prevCode] . $entry[0];
                $nextCode++;

                // Increase code length if needed
                $threshold = (1 << $codeLen) - $earlyChange;
                if ($nextCode >= $threshold && $codeLen < 12) {
                    $codeLen++;
                }
            }

            $prevCode = $code;
        }

        return $result;
    }

    /**
     * Read bits from a byte string.
     */
    private function readBits(string $data, int $bitPos, int $numBits): int
    {
        $bytePos = (int) ($bitPos / 8);
        $bitOffset = $bitPos % 8;

        $value = 0;
        $bitsRead = 0;

        while ($bitsRead < $numBits && $bytePos < strlen($data)) {
            $byte = ord($data[$bytePos]);
            $bitsAvailable = 8 - $bitOffset;
            $bitsToRead = min($bitsAvailable, $numBits - $bitsRead);

            $mask = (1 << $bitsToRead) - 1;
            $shift = $bitsAvailable - $bitsToRead;
            $bits = ($byte >> $shift) & $mask;

            $value = ($value << $bitsToRead) | $bits;
            $bitsRead += $bitsToRead;
            $bitOffset = 0;
            $bytePos++;
        }

        return $value;
    }

    /**
     * Decode RunLengthDecode data.
     */
    private function decodeRunLength(string $data): string
    {
        $result = '';
        $len = strlen($data);
        $i = 0;

        while ($i < $len) {
            $length = ord($data[$i]);
            $i++;

            if ($length === 128) {
                // End of data
                break;
            } elseif ($length < 128) {
                // Copy next (length + 1) bytes literally
                $count = $length + 1;
                $result .= substr($data, $i, $count);
                $i += $count;
            } else {
                // Repeat next byte (257 - length) times
                $count = 257 - $length;
                if ($i < $len) {
                    $result .= str_repeat($data[$i], $count);
                    $i++;
                }
            }
        }

        return $result;
    }

    /**
     * Apply predictor to decoded data (PNG/TIFF prediction).
     */
    private function applyPredictor(string $data, PdfDictionary $params): string
    {
        $predictor = 1;
        $colors = 1;
        $bitsPerComponent = 8;
        $columns = 1;

        $pred = $params->get('Predictor');
        if ($pred instanceof PdfNumber) {
            $predictor = $pred->toInt();
        }

        if ($predictor === 1) {
            return $data; // No prediction
        }

        $col = $params->get('Columns');
        if ($col instanceof PdfNumber) {
            $columns = $col->toInt();
        }

        $clr = $params->get('Colors');
        if ($clr instanceof PdfNumber) {
            $colors = $clr->toInt();
        }

        $bpc = $params->get('BitsPerComponent');
        if ($bpc instanceof PdfNumber) {
            $bitsPerComponent = $bpc->toInt();
        }

        if ($predictor === 2) {
            // TIFF predictor
            return $this->applyTiffPredictor($data, $columns, $colors, $bitsPerComponent);
        }

        if ($predictor >= 10 && $predictor <= 15) {
            // PNG predictor
            return $this->applyPngPredictor($data, $columns, $colors, $bitsPerComponent);
        }

        return $data;
    }

    /**
     * Apply TIFF predictor.
     */
    private function applyTiffPredictor(string $data, int $columns, int $colors, int $bitsPerComponent): string
    {
        $bytesPerPixel = (int) ceil($colors * $bitsPerComponent / 8);
        $rowLength = $columns * $bytesPerPixel;
        $result = '';

        for ($row = 0; $row < strlen($data); $row += $rowLength) {
            $rowData = substr($data, $row, $rowLength);
            $prev = array_fill(0, $colors, 0);

            for ($col = 0; $col < strlen($rowData); $col += $bytesPerPixel) {
                for ($c = 0; $c < $colors && $col + $c < strlen($rowData); $c++) {
                    $value = ord($rowData[$col + $c]);
                    $value = ($value + $prev[$c]) & 0xFF;
                    $result .= chr($value);
                    $prev[$c] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Apply PNG predictor.
     */
    private function applyPngPredictor(string $data, int $columns, int $colors, int $bitsPerComponent): string
    {
        $bytesPerPixel = (int) ceil($colors * $bitsPerComponent / 8);
        $rowLength = $columns * $bytesPerPixel + 1; // +1 for filter byte
        $result = '';
        $prevRow = str_repeat("\x00", $columns * $bytesPerPixel);

        for ($row = 0; $row < strlen($data); $row += $rowLength) {
            $filterByte = ord($data[$row]);
            $rowData = substr($data, $row + 1, $rowLength - 1);

            $decoded = $this->applyPngFilter($filterByte, $rowData, $prevRow, $bytesPerPixel);
            $result .= $decoded;
            $prevRow = $decoded;
        }

        return $result;
    }

    /**
     * Apply PNG filter to a row.
     */
    private function applyPngFilter(int $filter, string $row, string $prevRow, int $bpp): string
    {
        $len = strlen($row);
        $result = '';

        for ($i = 0; $i < $len; $i++) {
            $x = ord($row[$i]);
            $a = $i >= $bpp ? ord($result[$i - $bpp]) : 0;
            $b = ord($prevRow[$i] ?? "\x00");
            $c = $i >= $bpp ? ord($prevRow[$i - $bpp] ?? "\x00") : 0;

            $value = match ($filter) {
                0 => $x, // None
                1 => ($x + $a) & 0xFF, // Sub
                2 => ($x + $b) & 0xFF, // Up
                3 => ($x + (int) (($a + $b) / 2)) & 0xFF, // Average
                4 => ($x + $this->paethPredictor($a, $b, $c)) & 0xFF, // Paeth
                default => $x,
            };

            $result .= chr($value);
        }

        return $result;
    }

    /**
     * Paeth predictor function.
     */
    private function paethPredictor(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        } elseif ($pb <= $pc) {
            return $b;
        }
        return $c;
    }
}
