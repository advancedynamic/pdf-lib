<?php

declare(strict_types=1);

namespace PdfLib\Form;

use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfString;

/**
 * Text field for PDF forms.
 *
 * Supports single-line, multi-line, password, and comb text fields.
 *
 * @example
 * ```php
 * $field = TextField::create('username')
 *     ->setPosition(100, 700)
 *     ->setSize(200, 24)
 *     ->setRequired()
 *     ->setMaxLength(50)
 *     ->setTooltip('Enter your username');
 * ```
 */
final class TextField extends FormField
{
    // Text field specific flags (Table 229 in PDF spec)
    public const FLAG_MULTILINE = 4096;             // Bit 13
    public const FLAG_PASSWORD = 8192;              // Bit 14
    public const FLAG_FILE_SELECT = 1048576;        // Bit 21
    public const FLAG_DO_NOT_SPELL_CHECK = 4194304; // Bit 23
    public const FLAG_DO_NOT_SCROLL = 8388608;      // Bit 24
    public const FLAG_COMB = 16777216;              // Bit 25 (requires MaxLen)
    public const FLAG_RICH_TEXT = 33554432;         // Bit 26

    // Text alignment
    public const ALIGN_LEFT = 0;
    public const ALIGN_CENTER = 1;
    public const ALIGN_RIGHT = 2;

    private string $value = '';
    private string $defaultValue = '';
    private ?int $maxLength = null;
    private bool $multiline = false;
    private bool $password = false;
    private bool $comb = false;
    private bool $doNotSpellCheck = false;
    private bool $doNotScroll = false;
    private int $alignment = self::ALIGN_LEFT;

    // Text appearance
    private string $fontName = 'Helvetica';
    private float $fontSize = 12;
    /** @var array{0: float, 1: float, 2: float}|null */
    private ?array $textColor = null;

    /**
     * Create a new text field.
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    public function getFieldType(): string
    {
        return 'Tx';
    }

    protected function getTypeSpecificFlags(): int
    {
        $flags = 0;

        if ($this->multiline) {
            $flags |= self::FLAG_MULTILINE;
        }

        if ($this->password) {
            $flags |= self::FLAG_PASSWORD;
        }

        if ($this->comb && $this->maxLength !== null) {
            $flags |= self::FLAG_COMB;
        }

        if ($this->doNotSpellCheck) {
            $flags |= self::FLAG_DO_NOT_SPELL_CHECK;
        }

        if ($this->doNotScroll) {
            $flags |= self::FLAG_DO_NOT_SCROLL;
        }

        return $flags;
    }

    protected function buildFieldSpecificEntries(PdfDictionary $dict): void
    {
        if ($this->maxLength !== null) {
            $dict->set('MaxLen', PdfNumber::int($this->maxLength));
        }

        // Default appearance string (/DA)
        $dict->set('DA', PdfString::literal($this->buildDefaultAppearance()));

        // Quadding (alignment)
        if ($this->alignment !== self::ALIGN_LEFT) {
            $dict->set('Q', PdfNumber::int($this->alignment));
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(mixed $value): static
    {
        $this->value = (string) $value;
        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue !== '' ? $this->defaultValue : null;
    }

    /**
     * Set default value.
     */
    public function setDefaultValue(string $defaultValue): static
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    /**
     * Get maximum length.
     */
    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    /**
     * Set maximum length.
     */
    public function setMaxLength(int $maxLength): static
    {
        $this->maxLength = max(0, $maxLength);
        return $this;
    }

    /**
     * Check if field is multiline.
     */
    public function isMultiline(): bool
    {
        return $this->multiline;
    }

    /**
     * Set multiline mode.
     */
    public function setMultiline(bool $multiline = true): static
    {
        $this->multiline = $multiline;
        return $this;
    }

    /**
     * Check if field is password.
     */
    public function isPassword(): bool
    {
        return $this->password;
    }

    /**
     * Set password mode.
     */
    public function setPassword(bool $password = true): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Check if field uses comb mode.
     */
    public function isComb(): bool
    {
        return $this->comb;
    }

    /**
     * Set comb mode (fixed-width character cells).
     * Requires maxLength to be set.
     */
    public function setComb(bool $comb = true): static
    {
        $this->comb = $comb;
        return $this;
    }

    /**
     * Set do not spell check flag.
     */
    public function setDoNotSpellCheck(bool $doNotSpellCheck = true): static
    {
        $this->doNotSpellCheck = $doNotSpellCheck;
        return $this;
    }

    /**
     * Set do not scroll flag.
     */
    public function setDoNotScroll(bool $doNotScroll = true): static
    {
        $this->doNotScroll = $doNotScroll;
        return $this;
    }

    /**
     * Get text alignment.
     */
    public function getAlignment(): int
    {
        return $this->alignment;
    }

    /**
     * Set text alignment.
     */
    public function setAlignment(int $alignment): static
    {
        $this->alignment = $alignment;
        return $this;
    }

    /**
     * Align text left.
     */
    public function alignLeft(): static
    {
        return $this->setAlignment(self::ALIGN_LEFT);
    }

    /**
     * Align text center.
     */
    public function alignCenter(): static
    {
        return $this->setAlignment(self::ALIGN_CENTER);
    }

    /**
     * Align text right.
     */
    public function alignRight(): static
    {
        return $this->setAlignment(self::ALIGN_RIGHT);
    }

    /**
     * Set font.
     */
    public function setFont(string $fontName, float $fontSize): static
    {
        $this->fontName = $fontName;
        $this->fontSize = $fontSize;
        return $this;
    }

    /**
     * Get font name.
     */
    public function getFontName(): string
    {
        return $this->fontName;
    }

    /**
     * Get font size.
     */
    public function getFontSize(): float
    {
        return $this->fontSize;
    }

    /**
     * Set text color (RGB values 0-1).
     */
    public function setTextColor(float $r, float $g, float $b): static
    {
        $this->textColor = [$r, $g, $b];
        return $this;
    }

    /**
     * Build default appearance string.
     */
    private function buildDefaultAppearance(): string
    {
        $da = '';

        // Text color (default black)
        if ($this->textColor !== null) {
            $da .= sprintf(
                '%.3f %.3f %.3f rg ',
                $this->textColor[0],
                $this->textColor[1],
                $this->textColor[2]
            );
        } else {
            $da .= '0 g ';
        }

        // Font and size
        $da .= sprintf('/%s %.1f Tf', $this->fontName, $this->fontSize);

        return $da;
    }

    public function generateAppearance(): string
    {
        $content = '';
        $width = $this->width;
        $height = $this->height;

        // Background
        if ($this->backgroundColor !== null) {
            $content .= sprintf(
                '%.3f %.3f %.3f rg ',
                $this->backgroundColor[0],
                $this->backgroundColor[1],
                $this->backgroundColor[2]
            );
            $content .= sprintf('0 0 %.4f %.4f re f ', $width, $height);
        }

        // Border
        if ($this->borderColor !== null) {
            $content .= sprintf(
                '%.3f %.3f %.3f RG ',
                $this->borderColor[0],
                $this->borderColor[1],
                $this->borderColor[2]
            );
            $content .= sprintf('%.4f w ', $this->borderWidth);
            $offset = $this->borderWidth / 2;
            $content .= sprintf(
                '%.4f %.4f %.4f %.4f re S ',
                $offset,
                $offset,
                $width - $this->borderWidth,
                $height - $this->borderWidth
            );
        }

        // Text content (if value is set)
        if ($this->value !== '') {
            $displayValue = $this->password
                ? str_repeat('*', mb_strlen($this->value))
                : $this->value;

            // Text color
            if ($this->textColor !== null) {
                $content .= sprintf(
                    '%.3f %.3f %.3f rg ',
                    $this->textColor[0],
                    $this->textColor[1],
                    $this->textColor[2]
                );
            } else {
                $content .= '0 g ';
            }

            $content .= 'BT ';
            $content .= sprintf('/%s %.1f Tf ', $this->fontName, $this->fontSize);

            // Position text with padding
            $padding = 2;
            $textY = ($height - $this->fontSize) / 2 + 2;
            $content .= sprintf('%.4f %.4f Td ', $padding, $textY);
            $content .= sprintf('(%s) Tj ', $this->escapeText($displayValue));
            $content .= 'ET ';
        }

        return trim($content);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'value' => $this->value,
            'defaultValue' => $this->defaultValue,
            'maxLength' => $this->maxLength,
            'multiline' => $this->multiline,
            'password' => $this->password,
            'comb' => $this->comb,
            'doNotSpellCheck' => $this->doNotSpellCheck,
            'doNotScroll' => $this->doNotScroll,
            'alignment' => $this->alignment,
            'fontName' => $this->fontName,
            'fontSize' => $this->fontSize,
            'textColor' => $this->textColor,
        ]);
    }

    /**
     * Create from array representation.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $field = new self($data['name'] ?? 'TextField');
        $field->fromArrayBase($data);

        if (isset($data['value'])) {
            $field->value = (string) $data['value'];
        }
        if (isset($data['defaultValue'])) {
            $field->defaultValue = (string) $data['defaultValue'];
        }
        if (isset($data['maxLength'])) {
            $field->maxLength = (int) $data['maxLength'];
        }
        if (isset($data['multiline'])) {
            $field->multiline = (bool) $data['multiline'];
        }
        if (isset($data['password'])) {
            $field->password = (bool) $data['password'];
        }
        if (isset($data['comb'])) {
            $field->comb = (bool) $data['comb'];
        }
        if (isset($data['doNotSpellCheck'])) {
            $field->doNotSpellCheck = (bool) $data['doNotSpellCheck'];
        }
        if (isset($data['doNotScroll'])) {
            $field->doNotScroll = (bool) $data['doNotScroll'];
        }
        if (isset($data['alignment'])) {
            $field->alignment = (int) $data['alignment'];
        }
        if (isset($data['fontName'])) {
            $field->fontName = (string) $data['fontName'];
        }
        if (isset($data['fontSize'])) {
            $field->fontSize = (float) $data['fontSize'];
        }
        if (isset($data['textColor'])) {
            $field->textColor = $data['textColor'];
        }

        return $field;
    }
}
