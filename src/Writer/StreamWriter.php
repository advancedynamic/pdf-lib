<?php

declare(strict_types=1);

namespace PdfLib\Writer;

use PdfLib\Exception\WriteException;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfStream;

/**
 * Handles stream compression and encoding for PDF output.
 */
final class StreamWriter
{
    public const FILTER_NONE = null;
    public const FILTER_FLATE = 'FlateDecode';
    public const FILTER_ASCII_HEX = 'ASCIIHexDecode';
    public const FILTER_ASCII85 = 'ASCII85Decode';
    public const FILTER_LZW = 'LZWDecode';

    /**
     * Default compression level for zlib (0-9).
     */
    private int $compressionLevel = 6;

    /**
     * Default filter to apply to streams.
     */
    private ?string $defaultFilter = self::FILTER_FLATE;

    /**
     * Set the compression level (0-9).
     */
    public function setCompressionLevel(int $level): self
    {
        $this->compressionLevel = max(0, min(9, $level));
        return $this;
    }

    /**
     * Set the default filter for streams.
     */
    public function setDefaultFilter(?string $filter): self
    {
        $this->defaultFilter = $filter;
        return $this;
    }

    /**
     * Create a stream from data with optional compression.
     */
    public function createStream(
        string $data,
        ?PdfDictionary $dictionary = null,
        ?string $filter = null
    ): PdfStream {
        $dict = $dictionary ?? new PdfDictionary();
        $filter = $filter ?? $this->defaultFilter;

        if ($filter !== null) {
            $data = $this->encode($data, $filter);
            $dict->set(PdfName::FILTER, PdfName::create($filter));
        }

        $dict->set(PdfName::LENGTH, PdfNumber::int(strlen($data)));

        return PdfStream::fromData($data, $dict);
    }

    /**
     * Encode data with a filter.
     */
    public function encode(string $data, string $filter): string
    {
        return match ($filter) {
            self::FILTER_FLATE => $this->encodeFlate($data),
            self::FILTER_ASCII_HEX => $this->encodeAsciiHex($data),
            self::FILTER_ASCII85 => $this->encodeAscii85($data),
            default => throw WriteException::streamError("Unsupported encoding filter: $filter"),
        };
    }

    /**
     * Decode data with a filter.
     */
    public function decode(string $data, string $filter): string
    {
        return match ($filter) {
            self::FILTER_FLATE => $this->decodeFlate($data),
            self::FILTER_ASCII_HEX => $this->decodeAsciiHex($data),
            self::FILTER_ASCII85 => $this->decodeAscii85($data),
            default => throw WriteException::streamError("Unsupported decoding filter: $filter"),
        };
    }

    /**
     * Encode with FlateDecode (zlib compression).
     */
    private function encodeFlate(string $data): string
    {
        $compressed = gzcompress($data, $this->compressionLevel);
        if ($compressed === false) {
            throw WriteException::compressionError('FlateDecode compression failed');
        }
        return $compressed;
    }

    /**
     * Decode FlateDecode data.
     */
    private function decodeFlate(string $data): string
    {
        $decompressed = @gzuncompress($data);
        if ($decompressed === false) {
            $decompressed = @gzinflate($data);
        }
        if ($decompressed === false) {
            throw WriteException::compressionError('FlateDecode decompression failed');
        }
        return $decompressed;
    }

    /**
     * Encode with ASCIIHexDecode.
     */
    private function encodeAsciiHex(string $data): string
    {
        return strtoupper(bin2hex($data)) . '>';
    }

    /**
     * Decode ASCIIHexDecode data.
     */
    private function decodeAsciiHex(string $data): string
    {
        // Remove whitespace and end marker
        $data = preg_replace('/\s/', '', $data);
        $data = rtrim($data, '>');

        // Pad odd-length data
        if (strlen($data) % 2 !== 0) {
            $data .= '0';
        }

        $decoded = hex2bin($data);
        if ($decoded === false) {
            throw WriteException::streamError('Invalid ASCIIHexDecode data');
        }
        return $decoded;
    }

    /**
     * Encode with ASCII85Decode.
     */
    private function encodeAscii85(string $data): string
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
            $result .= substr($encoded, 0, $chunkLen + 1);
        }

        return $result . '~>';
    }

    /**
     * Decode ASCII85Decode data.
     */
    private function decodeAscii85(string $data): string
    {
        // Remove whitespace
        $data = preg_replace('/\s/', '', $data);

        // Remove markers
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
            $result .= substr($bytes, 0, $count - 1);
        }

        return $result;
    }

    /**
     * Create a content stream from PDF drawing operators.
     */
    public function createContentStream(string $content, bool $compress = true): PdfStream
    {
        $filter = $compress ? self::FILTER_FLATE : null;
        return $this->createStream($content, null, $filter);
    }

    /**
     * Get the size reduction from compression.
     *
     * @return array{original: int, compressed: int, ratio: float}
     */
    public function getCompressionStats(string $original, string $compressed): array
    {
        $originalSize = strlen($original);
        $compressedSize = strlen($compressed);
        $ratio = $originalSize > 0 ? (1 - $compressedSize / $originalSize) * 100 : 0;

        return [
            'original' => $originalSize,
            'compressed' => $compressedSize,
            'ratio' => round($ratio, 2),
        ];
    }
}
