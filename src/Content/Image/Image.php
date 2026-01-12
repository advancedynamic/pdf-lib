<?php

declare(strict_types=1);

namespace PdfLib\Content\Image;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfStream;

/**
 * Represents an image that can be embedded in a PDF.
 */
final class Image
{
    private int $width;
    private int $height;
    private int $bitsPerComponent;
    private string $colorSpace;
    private string $data;
    private string $filter;
    private ?string $softMask = null;
    private ?array $decode = null;

    public const COLORSPACE_GRAY = 'DeviceGray';
    public const COLORSPACE_RGB = 'DeviceRGB';
    public const COLORSPACE_CMYK = 'DeviceCMYK';

    public const FILTER_NONE = '';
    public const FILTER_FLATE = 'FlateDecode';
    public const FILTER_DCT = 'DCTDecode';
    public const FILTER_ASCII85 = 'ASCII85Decode';

    private function __construct(
        int $width,
        int $height,
        int $bitsPerComponent,
        string $colorSpace,
        string $data,
        string $filter = ''
    ) {
        $this->width = $width;
        $this->height = $height;
        $this->bitsPerComponent = $bitsPerComponent;
        $this->colorSpace = $colorSpace;
        $this->data = $data;
        $this->filter = $filter;
    }

    /**
     * Create an image from raw pixel data.
     */
    public static function fromRawData(
        string $data,
        int $width,
        int $height,
        string $colorSpace = self::COLORSPACE_RGB,
        int $bitsPerComponent = 8
    ): self {
        return new self($width, $height, $bitsPerComponent, $colorSpace, $data, self::FILTER_NONE);
    }

    /**
     * Create an image from JPEG data (already compressed).
     */
    public static function fromJpegData(string $jpegData): self
    {
        $info = self::parseJpegHeader($jpegData);

        $image = new self(
            $info['width'],
            $info['height'],
            $info['bitsPerComponent'],
            $info['colorSpace'],
            $jpegData,
            self::FILTER_DCT
        );

        // CMYK JPEGs may need decode array
        if ($info['colorSpace'] === self::COLORSPACE_CMYK) {
            $image->decode = [1, 0, 1, 0, 1, 0, 1, 0];
        }

        return $image;
    }

    /**
     * Create an image from PNG data.
     */
    public static function fromPngData(string $pngData): self
    {
        $info = self::parsePngHeader($pngData);

        $image = new self(
            $info['width'],
            $info['height'],
            $info['bitsPerComponent'],
            $info['colorSpace'],
            $info['data'],
            self::FILTER_FLATE
        );

        if (isset($info['softMask'])) {
            $image->softMask = $info['softMask'];
        }

        return $image;
    }

    // Getters

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getBitsPerComponent(): int
    {
        return $this->bitsPerComponent;
    }

    public function getColorSpace(): string
    {
        return $this->colorSpace;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getFilter(): string
    {
        return $this->filter;
    }

    public function hasSoftMask(): bool
    {
        return $this->softMask !== null;
    }

    public function getSoftMaskData(): ?string
    {
        return $this->softMask;
    }

    /**
     * Get aspect ratio (width / height).
     */
    public function getAspectRatio(): float
    {
        return $this->width / $this->height;
    }

    /**
     * Convert to PDF stream object.
     */
    public function toPdfStream(): PdfStream
    {
        $dict = new PdfDictionary();
        $dict->set('Type', PdfName::create('XObject'));
        $dict->set('Subtype', PdfName::create('Image'));
        $dict->set('Width', PdfNumber::int($this->width));
        $dict->set('Height', PdfNumber::int($this->height));
        $dict->set('BitsPerComponent', PdfNumber::int($this->bitsPerComponent));
        $dict->set('ColorSpace', PdfName::create($this->colorSpace));

        $data = $this->data;
        $filter = $this->filter;

        // For raw data (no filter), compress with FlateDecode for compatibility
        // Most PDF viewers handle compressed data better than raw uncompressed data
        if ($filter === self::FILTER_NONE) {
            $compressed = @gzcompress($data, 6);
            if ($compressed !== false) {
                $data = $compressed;
                $filter = self::FILTER_FLATE;
            }
        }

        if ($filter !== '') {
            $dict->set('Filter', PdfName::create($filter));
        }

        if ($this->decode !== null) {
            $decodeArray = new PdfArray(array_map(
                fn($v) => PdfNumber::int($v),
                $this->decode
            ));
            $dict->set('Decode', $decodeArray);
        }

        return new PdfStream($dict, $data);
    }

    /**
     * Create soft mask stream.
     */
    public function createSoftMaskStream(): ?PdfStream
    {
        if ($this->softMask === null) {
            return null;
        }

        $dict = new PdfDictionary();
        $dict->set('Type', PdfName::create('XObject'));
        $dict->set('Subtype', PdfName::create('Image'));
        $dict->set('Width', PdfNumber::int($this->width));
        $dict->set('Height', PdfNumber::int($this->height));
        $dict->set('BitsPerComponent', PdfNumber::int($this->bitsPerComponent));
        $dict->set('ColorSpace', PdfName::create('DeviceGray'));
        $dict->set('Filter', PdfName::create(self::FILTER_FLATE));

        return new PdfStream($dict, $this->softMask);
    }

    /**
     * Parse JPEG header to extract image information.
     *
     * @return array{width: int, height: int, bitsPerComponent: int, colorSpace: string}
     */
    private static function parseJpegHeader(string $data): array
    {
        if (substr($data, 0, 2) !== "\xFF\xD8") {
            throw new \InvalidArgumentException('Invalid JPEG data');
        }

        $pos = 2;
        $len = strlen($data);

        while ($pos < $len) {
            if (ord($data[$pos]) !== 0xFF) {
                throw new \InvalidArgumentException('Invalid JPEG marker');
            }

            $marker = ord($data[$pos + 1]);
            $pos += 2;

            // Start of Frame markers (SOF0-SOF15, except SOF4, SOF8, SOF12)
            if (($marker >= 0xC0 && $marker <= 0xC3) || ($marker >= 0xC5 && $marker <= 0xC7) ||
                ($marker >= 0xC9 && $marker <= 0xCB) || ($marker >= 0xCD && $marker <= 0xCF)) {

                $bitsPerComponent = ord($data[$pos + 2]);
                $height = (ord($data[$pos + 3]) << 8) | ord($data[$pos + 4]);
                $width = (ord($data[$pos + 5]) << 8) | ord($data[$pos + 6]);
                $components = ord($data[$pos + 7]);

                $colorSpace = match ($components) {
                    1 => self::COLORSPACE_GRAY,
                    3 => self::COLORSPACE_RGB,
                    4 => self::COLORSPACE_CMYK,
                    default => throw new \InvalidArgumentException("Unsupported JPEG components: $components"),
                };

                return [
                    'width' => $width,
                    'height' => $height,
                    'bitsPerComponent' => $bitsPerComponent,
                    'colorSpace' => $colorSpace,
                ];
            }

            // Skip marker
            if ($marker === 0xD8 || $marker === 0xD9 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                continue;
            }

            // Read segment length
            $segmentLength = (ord($data[$pos]) << 8) | ord($data[$pos + 1]);
            $pos += $segmentLength;
        }

        throw new \InvalidArgumentException('Could not parse JPEG header');
    }

    /**
     * Parse PNG header and extract image data.
     *
     * @return array{width: int, height: int, bitsPerComponent: int, colorSpace: string, data: string, softMask?: string}
     */
    private static function parsePngHeader(string $data): array
    {
        // Check PNG signature
        if (substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new \InvalidArgumentException('Invalid PNG data');
        }

        $pos = 8;
        $len = strlen($data);

        $width = 0;
        $height = 0;
        $bitDepth = 0;
        $colorType = 0;
        $compression = 0;
        $filter = 0;
        $interlace = 0;
        $idatChunks = [];
        $palette = null;
        $transparency = null;

        while ($pos < $len) {
            $chunkLength = unpack('N', substr($data, $pos, 4))[1];
            $chunkType = substr($data, $pos + 4, 4);
            $chunkData = substr($data, $pos + 8, $chunkLength);
            $pos += 12 + $chunkLength; // length + type + data + CRC

            switch ($chunkType) {
                case 'IHDR':
                    $width = unpack('N', substr($chunkData, 0, 4))[1];
                    $height = unpack('N', substr($chunkData, 4, 4))[1];
                    $bitDepth = ord($chunkData[8]);
                    $colorType = ord($chunkData[9]);
                    $compression = ord($chunkData[10]);
                    $filter = ord($chunkData[11]);
                    $interlace = ord($chunkData[12]);
                    break;

                case 'PLTE':
                    $palette = $chunkData;
                    break;

                case 'tRNS':
                    $transparency = $chunkData;
                    break;

                case 'IDAT':
                    $idatChunks[] = $chunkData;
                    break;

                case 'IEND':
                    break 2;
            }
        }

        if ($interlace !== 0) {
            throw new \InvalidArgumentException('Interlaced PNGs are not supported');
        }

        // Decompress image data
        $compressedData = implode('', $idatChunks);
        $rawData = @gzuncompress($compressedData);

        if ($rawData === false) {
            // Try inflate for raw deflate data
            $rawData = @gzinflate($compressedData);
            if ($rawData === false) {
                throw new \InvalidArgumentException('Could not decompress PNG data');
            }
        }

        // Decode filters and convert to PDF format
        $result = self::decodePngData(
            $rawData,
            $width,
            $height,
            $bitDepth,
            $colorType,
            $palette,
            $transparency
        );

        return $result;
    }

    /**
     * Decode PNG filtered data.
     *
     * @return array{width: int, height: int, bitsPerComponent: int, colorSpace: string, data: string, softMask?: string}
     */
    private static function decodePngData(
        string $rawData,
        int $width,
        int $height,
        int $bitDepth,
        int $colorType,
        ?string $palette,
        ?string $transparency
    ): array {
        // Calculate bytes per pixel and row
        $channels = match ($colorType) {
            0 => 1, // Grayscale
            2 => 3, // RGB
            3 => 1, // Indexed
            4 => 2, // Grayscale + Alpha
            6 => 4, // RGBA
            default => throw new \InvalidArgumentException("Unsupported PNG color type: $colorType"),
        };

        $bitsPerPixel = $channels * $bitDepth;
        $bytesPerPixel = (int) ceil($bitsPerPixel / 8);
        $bytesPerRow = (int) ceil($width * $bitsPerPixel / 8);

        // Remove filter bytes and decode
        $decodedData = '';
        $alphaData = '';
        $prevRow = str_repeat("\x00", $bytesPerRow);
        $pos = 0;

        for ($y = 0; $y < $height; $y++) {
            $filterType = ord($rawData[$pos]);
            $row = substr($rawData, $pos + 1, $bytesPerRow);
            $pos += 1 + $bytesPerRow;

            // Apply filter
            $row = self::applyPngFilter($filterType, $row, $prevRow, $bytesPerPixel);
            $prevRow = $row;

            // Extract pixel data based on color type
            switch ($colorType) {
                case 0: // Grayscale
                case 2: // RGB
                case 3: // Indexed
                    $decodedData .= $row;
                    break;

                case 4: // Grayscale + Alpha
                    for ($x = 0; $x < $width; $x++) {
                        $offset = $x * 2;
                        $decodedData .= $row[$offset];
                        $alphaData .= $row[$offset + 1];
                    }
                    break;

                case 6: // RGBA
                    for ($x = 0; $x < $width; $x++) {
                        $offset = $x * 4;
                        $decodedData .= substr($row, $offset, 3);
                        $alphaData .= $row[$offset + 3];
                    }
                    break;
            }
        }

        // Determine color space
        $colorSpace = match ($colorType) {
            0, 4 => self::COLORSPACE_GRAY,
            2, 6 => self::COLORSPACE_RGB,
            3 => self::COLORSPACE_RGB, // Indexed will be expanded
            default => self::COLORSPACE_RGB,
        };

        // Expand indexed color
        if ($colorType === 3 && $palette !== null) {
            $expandedData = '';
            $len = strlen($decodedData);
            for ($i = 0; $i < $len; $i++) {
                $index = ord($decodedData[$i]) * 3;
                $expandedData .= substr($palette, $index, 3);
            }
            $decodedData = $expandedData;
        }

        // Compress for PDF
        $compressedData = gzcompress($decodedData, 6);
        if ($compressedData === false) {
            $compressedData = $decodedData;
        }

        $result = [
            'width' => $width,
            'height' => $height,
            'bitsPerComponent' => $bitDepth,
            'colorSpace' => $colorSpace,
            'data' => $compressedData,
        ];

        // Add soft mask if alpha channel exists
        if ($alphaData !== '') {
            $compressedAlpha = gzcompress($alphaData, 6);
            if ($compressedAlpha !== false) {
                $result['softMask'] = $compressedAlpha;
            }
        }

        return $result;
    }

    /**
     * Apply PNG filter to a row.
     */
    private static function applyPngFilter(
        int $filterType,
        string $row,
        string $prevRow,
        int $bytesPerPixel
    ): string {
        $len = strlen($row);
        $result = '';

        for ($i = 0; $i < $len; $i++) {
            $x = ord($row[$i]);
            $a = $i >= $bytesPerPixel ? ord($result[$i - $bytesPerPixel]) : 0;
            $b = ord($prevRow[$i]);
            $c = $i >= $bytesPerPixel ? ord($prevRow[$i - $bytesPerPixel]) : 0;

            $value = match ($filterType) {
                0 => $x, // None
                1 => $x + $a, // Sub
                2 => $x + $b, // Up
                3 => $x + (int) floor(($a + $b) / 2), // Average
                4 => $x + self::paethPredictor($a, $b, $c), // Paeth
                default => $x,
            };

            $result .= chr($value & 0xFF);
        }

        return $result;
    }

    /**
     * Paeth predictor function for PNG filtering.
     */
    private static function paethPredictor(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }
        if ($pb <= $pc) {
            return $b;
        }
        return $c;
    }
}
