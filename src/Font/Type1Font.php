<?php

declare(strict_types=1);

namespace PdfLib\Font;

use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;

/**
 * Type 1 font (PostScript fonts).
 *
 * Includes support for the standard 14 PDF fonts that are guaranteed
 * to be available in all PDF viewers without embedding.
 */
final class Type1Font implements Font
{
    private string $name;
    private string $baseFont;
    private FontMetrics $metrics;
    private string $encoding = 'WinAnsiEncoding';
    private bool $embedded = false;

    /**
     * Standard 14 font names.
     */
    public const COURIER = 'Courier';
    public const COURIER_BOLD = 'Courier-Bold';
    public const COURIER_OBLIQUE = 'Courier-Oblique';
    public const COURIER_BOLD_OBLIQUE = 'Courier-BoldOblique';
    public const HELVETICA = 'Helvetica';
    public const HELVETICA_BOLD = 'Helvetica-Bold';
    public const HELVETICA_OBLIQUE = 'Helvetica-Oblique';
    public const HELVETICA_BOLD_OBLIQUE = 'Helvetica-BoldOblique';
    public const TIMES_ROMAN = 'Times-Roman';
    public const TIMES_BOLD = 'Times-Bold';
    public const TIMES_ITALIC = 'Times-Italic';
    public const TIMES_BOLD_ITALIC = 'Times-BoldItalic';
    public const SYMBOL = 'Symbol';
    public const ZAPF_DINGBATS = 'ZapfDingbats';

    private function __construct(string $name, FontMetrics $metrics)
    {
        $this->name = $name;
        $this->baseFont = $name;
        $this->metrics = $metrics;
    }

    /**
     * Create a standard 14 font by name.
     */
    public static function create(string $name): self
    {
        $metrics = self::getStandardMetrics($name);
        if ($metrics === null) {
            throw new \InvalidArgumentException("Unknown Type 1 font: $name");
        }
        return new self($name, $metrics);
    }

    /**
     * Check if a font name is a standard 14 font.
     */
    public static function isStandard(string $name): bool
    {
        return in_array($name, self::getStandardFontNames(), true);
    }

    /**
     * Get list of standard 14 font names.
     *
     * @return array<int, string>
     */
    public static function getStandardFontNames(): array
    {
        return [
            self::COURIER,
            self::COURIER_BOLD,
            self::COURIER_OBLIQUE,
            self::COURIER_BOLD_OBLIQUE,
            self::HELVETICA,
            self::HELVETICA_BOLD,
            self::HELVETICA_OBLIQUE,
            self::HELVETICA_BOLD_OBLIQUE,
            self::TIMES_ROMAN,
            self::TIMES_BOLD,
            self::TIMES_ITALIC,
            self::TIMES_BOLD_ITALIC,
            self::SYMBOL,
            self::ZAPF_DINGBATS,
        ];
    }

    // Convenience factory methods
    public static function courier(): self
    {
        return self::create(self::COURIER);
    }

    public static function courierBold(): self
    {
        return self::create(self::COURIER_BOLD);
    }

    public static function courierOblique(): self
    {
        return self::create(self::COURIER_OBLIQUE);
    }

    public static function courierBoldOblique(): self
    {
        return self::create(self::COURIER_BOLD_OBLIQUE);
    }

    public static function helvetica(): self
    {
        return self::create(self::HELVETICA);
    }

    public static function helveticaBold(): self
    {
        return self::create(self::HELVETICA_BOLD);
    }

    public static function helveticaOblique(): self
    {
        return self::create(self::HELVETICA_OBLIQUE);
    }

    public static function helveticaBoldOblique(): self
    {
        return self::create(self::HELVETICA_BOLD_OBLIQUE);
    }

    public static function timesRoman(): self
    {
        return self::create(self::TIMES_ROMAN);
    }

    public static function timesBold(): self
    {
        return self::create(self::TIMES_BOLD);
    }

    public static function timesItalic(): self
    {
        return self::create(self::TIMES_ITALIC);
    }

    public static function timesBoldItalic(): self
    {
        return self::create(self::TIMES_BOLD_ITALIC);
    }

    public static function symbol(): self
    {
        return self::create(self::SYMBOL);
    }

    public static function zapfDingbats(): self
    {
        return self::create(self::ZAPF_DINGBATS);
    }

    // Font interface implementation
    public function getName(): string
    {
        return $this->name;
    }

    public function getPostScriptName(): string
    {
        return $this->baseFont;
    }

    public function getType(): string
    {
        return 'Type1';
    }

    public function getEncoding(): string
    {
        return $this->encoding;
    }

    public function isEmbedded(): bool
    {
        return $this->embedded;
    }

    public function hasCharacter(string $char): bool
    {
        $code = ord($char);
        return $code >= 32 && $code <= 255;
    }

    public function getTextWidth(string $text, float $fontSize): float
    {
        $width = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $width += $this->getCharWidth($text[$i]);
        }
        return ($width * $fontSize) / 1000;
    }

    public function getCharWidth(string $char): int
    {
        $code = ord($char);
        return $this->metrics->getWidth($code);
    }

    public function getMetrics(): FontMetrics
    {
        return $this->metrics;
    }

    public function getAscender(): int
    {
        return $this->metrics->getAscender();
    }

    public function getDescender(): int
    {
        return $this->metrics->getDescender();
    }

    public function getLineHeight(): int
    {
        return $this->metrics->getLineHeight();
    }

    public function getCapHeight(): int
    {
        return $this->metrics->getCapHeight();
    }

    public function getXHeight(): int
    {
        return $this->metrics->getXHeight();
    }

    public function getUnderlinePosition(): int
    {
        return $this->metrics->getUnderlinePosition();
    }

    public function getUnderlineThickness(): int
    {
        return $this->metrics->getUnderlineThickness();
    }

    public function toDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('Type', PdfName::create('Font'));
        $dict->set('Subtype', PdfName::create('Type1'));
        $dict->set('BaseFont', PdfName::create($this->baseFont));

        // Standard 14 fonts don't need encoding except for Symbol and ZapfDingbats
        if ($this->baseFont !== self::SYMBOL && $this->baseFont !== self::ZAPF_DINGBATS) {
            $dict->set('Encoding', PdfName::create($this->encoding));
        }

        return $dict;
    }

    /**
     * Get standard metrics for a font.
     */
    private static function getStandardMetrics(string $name): ?FontMetrics
    {
        // Standard font metrics (simplified - common values for ASCII range)
        $metrics = match ($name) {
            self::COURIER,
            self::COURIER_BOLD,
            self::COURIER_OBLIQUE,
            self::COURIER_BOLD_OBLIQUE => self::getCourierMetrics($name),
            self::HELVETICA => self::getHelveticaMetrics(),
            self::HELVETICA_BOLD => self::getHelveticaBoldMetrics(),
            self::HELVETICA_OBLIQUE => self::getHelveticaObliqueMetrics(),
            self::HELVETICA_BOLD_OBLIQUE => self::getHelveticaBoldObliqueMetrics(),
            self::TIMES_ROMAN => self::getTimesRomanMetrics(),
            self::TIMES_BOLD => self::getTimesBoldMetrics(),
            self::TIMES_ITALIC => self::getTimesItalicMetrics(),
            self::TIMES_BOLD_ITALIC => self::getTimesBoldItalicMetrics(),
            self::SYMBOL => self::getSymbolMetrics(),
            self::ZAPF_DINGBATS => self::getZapfDingbatsMetrics(),
            default => null,
        };

        return $metrics;
    }

    private static function getCourierMetrics(string $name): FontMetrics
    {
        // Courier is monospace - all chars are 600 units wide
        $widths = array_fill(32, 224, 600);

        $flags = 0x0021; // FixedPitch, NonSymbolic
        if (str_contains($name, 'Bold')) {
            $flags |= 0x40000; // ForceBold
        }
        if (str_contains($name, 'Oblique')) {
            $flags |= 0x0040; // Italic
        }

        return new FontMetrics(
            ascender: 629,
            descender: -157,
            lineGap: 0,
            capHeight: 562,
            xHeight: 426,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: str_contains($name, 'Bold') ? 106 : 51,
            stemH: str_contains($name, 'Bold') ? 84 : 51,
            italicAngle: str_contains($name, 'Oblique') ? -12 : 0,
            flags: $flags,
            defaultWidth: 600,
            widths: $widths,
            bbox: [-23, -250, 715, 805],
        );
    }

    private static function getHelveticaMetrics(): FontMetrics
    {
        $widths = self::getHelveticaWidths();

        return new FontMetrics(
            ascender: 718,
            descender: -207,
            lineGap: 0,
            capHeight: 718,
            xHeight: 523,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: 88,
            stemH: 76,
            italicAngle: 0,
            flags: 0x0020, // NonSymbolic
            defaultWidth: 278,
            widths: $widths,
            bbox: [-166, -225, 1000, 931],
        );
    }

    private static function getHelveticaBoldMetrics(): FontMetrics
    {
        $widths = self::getHelveticaBoldWidths();

        return new FontMetrics(
            ascender: 718,
            descender: -207,
            lineGap: 0,
            capHeight: 718,
            xHeight: 532,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: 140,
            stemH: 118,
            italicAngle: 0,
            flags: 0x40020, // NonSymbolic, ForceBold
            defaultWidth: 278,
            widths: $widths,
            bbox: [-170, -228, 1003, 962],
        );
    }

    private static function getHelveticaObliqueMetrics(): FontMetrics
    {
        $widths = self::getHelveticaWidths();

        return new FontMetrics(
            ascender: 718,
            descender: -207,
            lineGap: 0,
            capHeight: 718,
            xHeight: 523,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: 88,
            stemH: 76,
            italicAngle: -12,
            flags: 0x0060, // NonSymbolic, Italic
            defaultWidth: 278,
            widths: $widths,
            bbox: [-170, -225, 1116, 931],
        );
    }

    private static function getHelveticaBoldObliqueMetrics(): FontMetrics
    {
        $widths = self::getHelveticaBoldWidths();

        return new FontMetrics(
            ascender: 718,
            descender: -207,
            lineGap: 0,
            capHeight: 718,
            xHeight: 532,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: 140,
            stemH: 118,
            italicAngle: -12,
            flags: 0x40060, // NonSymbolic, Italic, ForceBold
            defaultWidth: 278,
            widths: $widths,
            bbox: [-174, -228, 1114, 962],
        );
    }

    private static function getTimesRomanMetrics(): FontMetrics
    {
        $widths = self::getTimesRomanWidths();

        return new FontMetrics(
            ascender: 683,
            descender: -217,
            lineGap: 0,
            capHeight: 662,
            xHeight: 450,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: 84,
            stemH: 28,
            italicAngle: 0,
            flags: 0x0022, // Serif, NonSymbolic
            defaultWidth: 250,
            widths: $widths,
            bbox: [-168, -218, 1000, 898],
        );
    }

    private static function getTimesBoldMetrics(): FontMetrics
    {
        $widths = self::getTimesBoldWidths();

        return new FontMetrics(
            ascender: 683,
            descender: -217,
            lineGap: 0,
            capHeight: 676,
            xHeight: 461,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: 139,
            stemH: 44,
            italicAngle: 0,
            flags: 0x40022, // Serif, NonSymbolic, ForceBold
            defaultWidth: 250,
            widths: $widths,
            bbox: [-168, -218, 1000, 935],
        );
    }

    private static function getTimesItalicMetrics(): FontMetrics
    {
        $widths = self::getTimesItalicWidths();

        return new FontMetrics(
            ascender: 683,
            descender: -217,
            lineGap: 0,
            capHeight: 653,
            xHeight: 441,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: 76,
            stemH: 32,
            italicAngle: -15,
            flags: 0x0062, // Serif, NonSymbolic, Italic
            defaultWidth: 250,
            widths: $widths,
            bbox: [-169, -217, 1010, 883],
        );
    }

    private static function getTimesBoldItalicMetrics(): FontMetrics
    {
        $widths = self::getTimesBoldItalicWidths();

        return new FontMetrics(
            ascender: 683,
            descender: -217,
            lineGap: 0,
            capHeight: 669,
            xHeight: 462,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: 121,
            stemH: 42,
            italicAngle: -15,
            flags: 0x40062, // Serif, NonSymbolic, Italic, ForceBold
            defaultWidth: 250,
            widths: $widths,
            bbox: [-200, -218, 996, 921],
        );
    }

    private static function getSymbolMetrics(): FontMetrics
    {
        $widths = array_fill(32, 224, 500);

        return new FontMetrics(
            ascender: 1010,
            descender: -293,
            lineGap: 0,
            capHeight: 0,
            xHeight: 0,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: 85,
            stemH: 92,
            italicAngle: 0,
            flags: 0x0004, // Symbolic
            defaultWidth: 250,
            widths: $widths,
            bbox: [-180, -293, 1090, 1010],
        );
    }

    private static function getZapfDingbatsMetrics(): FontMetrics
    {
        $widths = array_fill(32, 224, 500);

        return new FontMetrics(
            ascender: 820,
            descender: -143,
            lineGap: 0,
            capHeight: 0,
            xHeight: 0,
            unitsPerEm: 1000,
            underlinePosition: -100,
            underlineThickness: 50,
            stemV: 90,
            stemH: 28,
            italicAngle: 0,
            flags: 0x0004, // Symbolic
            defaultWidth: 278,
            widths: $widths,
            bbox: [-1, -143, 981, 820],
        );
    }

    /**
     * @return array<int, int>
     */
    private static function getHelveticaWidths(): array
    {
        // Helvetica character widths for ASCII 32-255
        return [
            32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
            40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
            48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
            56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
            64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
            72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
            80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
            88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
            96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
            104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556,
            112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556, 118 => 500, 119 => 722,
            120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260, 125 => 334, 126 => 584,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function getHelveticaBoldWidths(): array
    {
        return [
            32 => 278, 33 => 333, 34 => 474, 35 => 556, 36 => 556, 37 => 889, 38 => 722, 39 => 238,
            40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
            48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
            56 => 556, 57 => 556, 58 => 333, 59 => 333, 60 => 584, 61 => 584, 62 => 584, 63 => 611,
            64 => 975, 65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
            72 => 722, 73 => 278, 74 => 556, 75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778,
            80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
            88 => 667, 89 => 667, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 584, 95 => 556,
            96 => 333, 97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611,
            104 => 611, 105 => 278, 106 => 278, 107 => 556, 108 => 278, 109 => 889, 110 => 611, 111 => 611,
            112 => 611, 113 => 611, 114 => 389, 115 => 556, 116 => 333, 117 => 611, 118 => 556, 119 => 778,
            120 => 556, 121 => 556, 122 => 500, 123 => 389, 124 => 280, 125 => 389, 126 => 584,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function getTimesRomanWidths(): array
    {
        return [
            32 => 250, 33 => 333, 34 => 408, 35 => 500, 36 => 500, 37 => 833, 38 => 778, 39 => 180,
            40 => 333, 41 => 333, 42 => 500, 43 => 564, 44 => 250, 45 => 333, 46 => 250, 47 => 278,
            48 => 500, 49 => 500, 50 => 500, 51 => 500, 52 => 500, 53 => 500, 54 => 500, 55 => 500,
            56 => 500, 57 => 500, 58 => 278, 59 => 278, 60 => 564, 61 => 564, 62 => 564, 63 => 444,
            64 => 921, 65 => 722, 66 => 667, 67 => 667, 68 => 722, 69 => 611, 70 => 556, 71 => 722,
            72 => 722, 73 => 333, 74 => 389, 75 => 722, 76 => 611, 77 => 889, 78 => 722, 79 => 722,
            80 => 556, 81 => 722, 82 => 667, 83 => 556, 84 => 611, 85 => 722, 86 => 722, 87 => 944,
            88 => 722, 89 => 722, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 469, 95 => 500,
            96 => 333, 97 => 444, 98 => 500, 99 => 444, 100 => 500, 101 => 444, 102 => 333, 103 => 500,
            104 => 500, 105 => 278, 106 => 278, 107 => 500, 108 => 278, 109 => 778, 110 => 500, 111 => 500,
            112 => 500, 113 => 500, 114 => 333, 115 => 389, 116 => 278, 117 => 500, 118 => 500, 119 => 722,
            120 => 500, 121 => 500, 122 => 444, 123 => 480, 124 => 200, 125 => 480, 126 => 541,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function getTimesBoldWidths(): array
    {
        return [
            32 => 250, 33 => 333, 34 => 555, 35 => 500, 36 => 500, 37 => 1000, 38 => 833, 39 => 278,
            40 => 333, 41 => 333, 42 => 500, 43 => 570, 44 => 250, 45 => 333, 46 => 250, 47 => 278,
            48 => 500, 49 => 500, 50 => 500, 51 => 500, 52 => 500, 53 => 500, 54 => 500, 55 => 500,
            56 => 500, 57 => 500, 58 => 333, 59 => 333, 60 => 570, 61 => 570, 62 => 570, 63 => 500,
            64 => 930, 65 => 722, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
            72 => 778, 73 => 389, 74 => 500, 75 => 778, 76 => 667, 77 => 944, 78 => 722, 79 => 778,
            80 => 611, 81 => 778, 82 => 722, 83 => 556, 84 => 667, 85 => 722, 86 => 722, 87 => 1000,
            88 => 722, 89 => 722, 90 => 667, 91 => 333, 92 => 278, 93 => 333, 94 => 581, 95 => 500,
            96 => 333, 97 => 500, 98 => 556, 99 => 444, 100 => 556, 101 => 444, 102 => 333, 103 => 500,
            104 => 556, 105 => 278, 106 => 333, 107 => 556, 108 => 278, 109 => 833, 110 => 556, 111 => 500,
            112 => 556, 113 => 556, 114 => 444, 115 => 389, 116 => 333, 117 => 556, 118 => 500, 119 => 722,
            120 => 500, 121 => 500, 122 => 444, 123 => 394, 124 => 220, 125 => 394, 126 => 520,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function getTimesItalicWidths(): array
    {
        return [
            32 => 250, 33 => 333, 34 => 420, 35 => 500, 36 => 500, 37 => 833, 38 => 778, 39 => 214,
            40 => 333, 41 => 333, 42 => 500, 43 => 675, 44 => 250, 45 => 333, 46 => 250, 47 => 278,
            48 => 500, 49 => 500, 50 => 500, 51 => 500, 52 => 500, 53 => 500, 54 => 500, 55 => 500,
            56 => 500, 57 => 500, 58 => 333, 59 => 333, 60 => 675, 61 => 675, 62 => 675, 63 => 500,
            64 => 920, 65 => 611, 66 => 611, 67 => 667, 68 => 722, 69 => 611, 70 => 611, 71 => 722,
            72 => 722, 73 => 333, 74 => 444, 75 => 667, 76 => 556, 77 => 833, 78 => 667, 79 => 722,
            80 => 611, 81 => 722, 82 => 611, 83 => 500, 84 => 556, 85 => 722, 86 => 611, 87 => 833,
            88 => 611, 89 => 556, 90 => 556, 91 => 389, 92 => 278, 93 => 389, 94 => 422, 95 => 500,
            96 => 333, 97 => 500, 98 => 500, 99 => 444, 100 => 500, 101 => 444, 102 => 278, 103 => 500,
            104 => 500, 105 => 278, 106 => 278, 107 => 444, 108 => 278, 109 => 722, 110 => 500, 111 => 500,
            112 => 500, 113 => 500, 114 => 389, 115 => 389, 116 => 278, 117 => 500, 118 => 444, 119 => 667,
            120 => 444, 121 => 444, 122 => 389, 123 => 400, 124 => 275, 125 => 400, 126 => 541,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function getTimesBoldItalicWidths(): array
    {
        return [
            32 => 250, 33 => 389, 34 => 555, 35 => 500, 36 => 500, 37 => 833, 38 => 778, 39 => 278,
            40 => 333, 41 => 333, 42 => 500, 43 => 570, 44 => 250, 45 => 333, 46 => 250, 47 => 278,
            48 => 500, 49 => 500, 50 => 500, 51 => 500, 52 => 500, 53 => 500, 54 => 500, 55 => 500,
            56 => 500, 57 => 500, 58 => 333, 59 => 333, 60 => 570, 61 => 570, 62 => 570, 63 => 500,
            64 => 832, 65 => 667, 66 => 667, 67 => 667, 68 => 722, 69 => 667, 70 => 667, 71 => 722,
            72 => 778, 73 => 389, 74 => 500, 75 => 667, 76 => 611, 77 => 889, 78 => 722, 79 => 722,
            80 => 611, 81 => 722, 82 => 667, 83 => 556, 84 => 611, 85 => 722, 86 => 667, 87 => 889,
            88 => 667, 89 => 611, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 570, 95 => 500,
            96 => 333, 97 => 500, 98 => 500, 99 => 444, 100 => 500, 101 => 444, 102 => 333, 103 => 500,
            104 => 556, 105 => 278, 106 => 278, 107 => 500, 108 => 278, 109 => 778, 110 => 556, 111 => 500,
            112 => 500, 113 => 500, 114 => 389, 115 => 389, 116 => 278, 117 => 556, 118 => 444, 119 => 667,
            120 => 500, 121 => 444, 122 => 389, 123 => 348, 124 => 220, 125 => 348, 126 => 570,
        ];
    }
}
