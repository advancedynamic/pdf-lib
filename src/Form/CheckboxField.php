<?php

declare(strict_types=1);

namespace PdfLib\Form;

use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfString;

/**
 * Checkbox field for PDF forms.
 *
 * @example
 * ```php
 * $field = CheckboxField::create('terms_accepted')
 *     ->setPosition(100, 600)
 *     ->setSize(16, 16)
 *     ->setChecked(true)
 *     ->setExportValue('accepted');
 * ```
 */
final class CheckboxField extends FormField
{
    // Check mark styles
    public const STYLE_CHECK = 'check';
    public const STYLE_CIRCLE = 'circle';
    public const STYLE_CROSS = 'cross';
    public const STYLE_DIAMOND = 'diamond';
    public const STYLE_SQUARE = 'square';
    public const STYLE_STAR = 'star';

    private bool $checked = false;
    private bool $defaultChecked = false;
    private string $exportValue = 'Yes';
    private string $checkStyle = self::STYLE_CHECK;

    public function __construct(string $name)
    {
        parent::__construct($name);
        // Default checkbox size
        $this->width = 16;
        $this->height = 16;
    }

    /**
     * Create a new checkbox field.
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    public function getFieldType(): string
    {
        return 'Btn';
    }

    protected function getTypeSpecificFlags(): int
    {
        // Checkbox has no type-specific flags (not radio, not pushbutton)
        return 0;
    }

    protected function buildFieldSpecificEntries(PdfDictionary $dict): void
    {
        // Set appearance state
        $dict->set(
            'AS',
            $this->checked
                ? PdfName::create($this->exportValue)
                : PdfName::create('Off')
        );
    }

    /**
     * Override to add appearance characteristics.
     */
    public function toWidgetDictionary(?PdfReference $pageRef = null): PdfDictionary
    {
        $dict = parent::toWidgetDictionary($pageRef);

        // Add caption for checked state (ZapfDingbats font symbol)
        $mk = $dict->get('MK');
        if (!$mk instanceof PdfDictionary) {
            $mk = new PdfDictionary();
        }

        $mk->set('CA', PdfString::literal($this->getCheckSymbol()));
        $dict->set('MK', $mk);

        return $dict;
    }

    public function getValue(): bool
    {
        return $this->checked;
    }

    public function setValue(mixed $value): static
    {
        $this->checked = (bool) $value;
        return $this;
    }

    public function getDefaultValue(): ?bool
    {
        return $this->defaultChecked;
    }

    /**
     * Set default checked state.
     */
    public function setDefaultChecked(bool $checked = true): static
    {
        $this->defaultChecked = $checked;
        return $this;
    }

    /**
     * Override to return name for checkbox value.
     */
    protected function getValueForPdf(): ?PdfObject
    {
        return $this->checked
            ? PdfName::create($this->exportValue)
            : PdfName::create('Off');
    }

    /**
     * Override to return name for default value.
     */
    protected function getDefaultValueForPdf(): ?PdfObject
    {
        return $this->defaultChecked
            ? PdfName::create($this->exportValue)
            : PdfName::create('Off');
    }

    /**
     * Check if checkbox is checked.
     */
    public function isChecked(): bool
    {
        return $this->checked;
    }

    /**
     * Set checked state.
     */
    public function setChecked(bool $checked = true): static
    {
        $this->checked = $checked;
        return $this;
    }

    /**
     * Check the checkbox.
     */
    public function check(): static
    {
        return $this->setChecked(true);
    }

    /**
     * Uncheck the checkbox.
     */
    public function uncheck(): static
    {
        return $this->setChecked(false);
    }

    /**
     * Toggle the checked state.
     */
    public function toggle(): static
    {
        return $this->setChecked(!$this->checked);
    }

    /**
     * Get export value.
     */
    public function getExportValue(): string
    {
        return $this->exportValue;
    }

    /**
     * Set export value (value when checked).
     */
    public function setExportValue(string $exportValue): static
    {
        $this->exportValue = $exportValue;
        return $this;
    }

    /**
     * Get check style.
     */
    public function getCheckStyle(): string
    {
        return $this->checkStyle;
    }

    /**
     * Set check style.
     *
     * @param string $style One of: check, circle, cross, diamond, square, star
     */
    public function setCheckStyle(string $style): static
    {
        $validStyles = [
            self::STYLE_CHECK,
            self::STYLE_CIRCLE,
            self::STYLE_CROSS,
            self::STYLE_DIAMOND,
            self::STYLE_SQUARE,
            self::STYLE_STAR,
        ];
        if (in_array($style, $validStyles, true)) {
            $this->checkStyle = $style;
        }
        return $this;
    }

    /**
     * Get ZapfDingbats symbol for check style.
     */
    private function getCheckSymbol(): string
    {
        return match ($this->checkStyle) {
            self::STYLE_CHECK => '4',      // checkmark
            self::STYLE_CIRCLE => 'l',     // filled circle
            self::STYLE_CROSS => '8',      // cross/X
            self::STYLE_DIAMOND => 'u',    // filled diamond
            self::STYLE_SQUARE => 'n',     // filled square
            self::STYLE_STAR => 'H',       // star
            default => '4',
        };
    }

    public function generateAppearance(): string
    {
        return $this->generateAppearanceForState($this->checked);
    }

    /**
     * Generate appearance for checked state.
     */
    public function generateCheckedAppearance(): string
    {
        return $this->generateAppearanceForState(true);
    }

    /**
     * Generate appearance for unchecked state.
     */
    public function generateUncheckedAppearance(): string
    {
        return $this->generateAppearanceForState(false);
    }

    /**
     * Generate appearance for a given state.
     */
    private function generateAppearanceForState(bool $checked): string
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
        } else {
            $content .= '0 G ';
        }
        $content .= sprintf('%.4f w ', $this->borderWidth);
        $offset = $this->borderWidth / 2;
        $content .= sprintf(
            '%.4f %.4f %.4f %.4f re S ',
            $offset,
            $offset,
            $width - $this->borderWidth,
            $height - $this->borderWidth
        );

        // Check mark (if checked)
        if ($checked) {
            $content .= '0 g ';  // Black
            $content .= 'BT ';
            // Use ZapfDingbats font
            $fontSize = min($width, $height) * 0.8;
            $content .= sprintf('/ZaDb %.1f Tf ', $fontSize);

            // Center the symbol
            $x = ($width - $fontSize * 0.8) / 2;
            $y = ($height - $fontSize) / 2 + $fontSize * 0.1;
            $content .= sprintf('%.4f %.4f Td ', $x, $y);
            $content .= sprintf('(%s) Tj ', $this->getCheckSymbol());
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
            'checked' => $this->checked,
            'defaultChecked' => $this->defaultChecked,
            'exportValue' => $this->exportValue,
            'checkStyle' => $this->checkStyle,
        ]);
    }

    /**
     * Create from array representation.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $field = new self($data['name'] ?? 'Checkbox');
        $field->fromArrayBase($data);

        if (isset($data['checked'])) {
            $field->checked = (bool) $data['checked'];
        }
        if (isset($data['defaultChecked'])) {
            $field->defaultChecked = (bool) $data['defaultChecked'];
        }
        if (isset($data['exportValue'])) {
            $field->exportValue = (string) $data['exportValue'];
        }
        if (isset($data['checkStyle'])) {
            $field->checkStyle = (string) $data['checkStyle'];
        }

        return $field;
    }
}
