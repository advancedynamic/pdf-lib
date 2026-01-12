<?php

declare(strict_types=1);

namespace PdfLib\Content\Image;

/**
 * Factory for creating Image objects from various sources.
 */
final class ImageFactory
{
    /**
     * Load an image from a file.
     */
    public static function fromFile(string $path): Image
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Image file not found: $path");
        }

        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException("Could not read image file: $path");
        }

        return self::fromString($data, $path);
    }

    /**
     * Load an image from binary string data.
     */
    public static function fromString(string $data, ?string $filename = null): Image
    {
        $type = self::detectImageType($data);

        return match ($type) {
            'jpeg' => Image::fromJpegData($data),
            'png' => Image::fromPngData($data),
            default => throw new \InvalidArgumentException(
                $filename
                    ? "Unsupported image format in file: $filename"
                    : "Unsupported image format"
            ),
        };
    }

    /**
     * Load a JPEG image from file.
     */
    public static function jpeg(string $path): Image
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException("Could not read JPEG file: $path");
        }
        return Image::fromJpegData($data);
    }

    /**
     * Load a PNG image from file.
     */
    public static function png(string $path): Image
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException("Could not read PNG file: $path");
        }
        return Image::fromPngData($data);
    }

    /**
     * Create an image from a GD resource.
     *
     * @param \GdImage $image GD image resource
     */
    public static function fromGd(\GdImage $image, string $format = 'png'): Image
    {
        // Validate format before starting output buffer
        if (!in_array($format, ['jpeg', 'jpg', 'png'], true)) {
            throw new \InvalidArgumentException("Unsupported GD format: $format");
        }

        ob_start();

        $success = match ($format) {
            'jpeg', 'jpg' => imagejpeg($image, null, 90),
            'png' => imagepng($image),
        };

        $data = ob_get_clean();

        if (!$success || $data === false) {
            throw new \RuntimeException("Failed to encode GD image");
        }

        return match ($format) {
            'jpeg', 'jpg' => Image::fromJpegData($data),
            'png' => Image::fromPngData($data),
        };
    }

    /**
     * Create an image from Imagick object.
     *
     * @param \Imagick $imagick Imagick object
     */
    public static function fromImagick(\Imagick $imagick, string $format = 'png'): Image
    {
        $imagick->setImageFormat($format);
        $data = $imagick->getImageBlob();

        return match ($format) {
            'jpeg', 'jpg' => Image::fromJpegData($data),
            'png' => Image::fromPngData($data),
            default => throw new \InvalidArgumentException("Unsupported format: $format"),
        };
    }

    /**
     * Create an image from a URL.
     */
    public static function fromUrl(string $url): Image
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'PdfLib/1.0',
            ],
        ]);

        $data = @file_get_contents($url, false, $context);
        if ($data === false) {
            throw new \RuntimeException("Could not fetch image from URL: $url");
        }

        return self::fromString($data, $url);
    }

    /**
     * Create a placeholder image (solid color).
     */
    public static function placeholder(
        int $width,
        int $height,
        int $red = 200,
        int $green = 200,
        int $blue = 200
    ): Image {
        // Create raw RGB data
        $pixel = chr($red) . chr($green) . chr($blue);
        $row = str_repeat($pixel, $width);
        $data = str_repeat($row, $height);

        return Image::fromRawData($data, $width, $height, Image::COLORSPACE_RGB, 8);
    }

    /**
     * Detect image type from binary data.
     */
    public static function detectImageType(string $data): string
    {
        if (strlen($data) < 8) {
            return 'unknown';
        }

        // JPEG: FF D8 FF
        if (substr($data, 0, 3) === "\xFF\xD8\xFF") {
            return 'jpeg';
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (substr($data, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'png';
        }

        // GIF: GIF87a or GIF89a
        if (substr($data, 0, 6) === 'GIF87a' || substr($data, 0, 6) === 'GIF89a') {
            return 'gif';
        }

        // BMP: BM
        if (substr($data, 0, 2) === 'BM') {
            return 'bmp';
        }

        // TIFF: II (little-endian) or MM (big-endian)
        $tiffHeader = substr($data, 0, 4);
        if ($tiffHeader === "II\x2A\x00" || $tiffHeader === "MM\x00\x2A") {
            return 'tiff';
        }

        // WebP: RIFF....WEBP
        if (substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return 'unknown';
    }

    /**
     * Check if a file is a supported image format.
     */
    public static function isSupported(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $data = file_get_contents($path, false, null, 0, 16);
        if ($data === false) {
            return false;
        }

        $type = self::detectImageType($data);
        return in_array($type, ['jpeg', 'png'], true);
    }

    /**
     * Get supported image formats.
     *
     * @return array<int, string>
     */
    public static function getSupportedFormats(): array
    {
        return ['jpeg', 'jpg', 'png'];
    }
}
