<?php

declare(strict_types=1);

namespace PdfLib\Form;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfString;

/**
 * Dropdown (combo box) field for PDF forms.
 *
 * @example
 * ```php
 * $field = DropdownField::create('country')
 *     ->setPosition(100, 500)
 *     ->setSize(200, 24)
 *     ->addOption('us', 'United States')
 *     ->addOption('uk', 'United Kingdom')
 *     ->addOption('ca', 'Canada')
 *     ->setSelectedValue('us')
 *     ->setEditable(true);
 * ```
 */
final class DropdownField extends FormField
{
    // Choice field flags
    public const FLAG_COMBO = 131072;               // Bit 18: Combo box (dropdown)
    public const FLAG_EDIT = 262144;                // Bit 19: Editable combo
    public const FLAG_SORT = 524288;                // Bit 20: Sort options
    public const FLAG_DO_NOT_SPELL_CHECK = 4194304; // Bit 23
    public const FLAG_COMMIT_ON_SEL_CHANGE = 67108864; // Bit 27

    /** @var array<int, array{value: string, display: string}> */
    private array $options = [];
    private ?string $selectedValue = null;
    private ?string $defaultValue = null;
    private bool $editable = false;
    private bool $sorted = false;
    private bool $commitOnSelChange = false;

    // Text appearance
    private string $fontName = 'Helvetica';
    private float $fontSize = 12;
    /** @var array{0: float, 1: float, 2: float}|null */
    private ?array $textColor = null;

    /**
     * Create a new dropdown field.
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    public function getFieldType(): string
    {
        return 'Ch';
    }

    protected function getTypeSpecificFlags(): int
    {
        $flags = self::FLAG_COMBO;

        if ($this->editable) {
            $flags |= self::FLAG_EDIT;
        }

        if ($this->sorted) {
            $flags |= self::FLAG_SORT;
        }

        if ($this->commitOnSelChange) {
            $flags |= self::FLAG_COMMIT_ON_SEL_CHANGE;
        }

        return $flags;
    }

    protected function buildFieldSpecificEntries(PdfDictionary $dict): void
    {
        // Options array (/Opt)
        $optArray = new PdfArray();
        foreach ($this->options as $option) {
            // Each option is [exportValue, displayValue] or just string
            if ($option['value'] !== $option['display']) {
                $optArray->push(PdfArray::fromValues([
                    PdfString::literal($option['value']),
                    PdfString::literal($option['display']),
                ]));
            } else {
                $optArray->push(PdfString::literal($option['value']));
            }
        }
        $dict->set('Opt', $optArray);

        // Default appearance
        $dict->set('DA', PdfString::literal($this->buildDefaultAppearance()));

        // Selected index (/I)
        if ($this->selectedValue !== null) {
            $index = $this->findOptionIndex($this->selectedValue);
            if ($index !== null) {
                $dict->set('I', PdfArray::fromValues([PdfNumber::int($index)]));
            }
        }
    }

    public function getValue(): ?string
    {
        return $this->selectedValue;
    }

    public function setValue(mixed $value): static
    {
        $this->selectedValue = $value !== null ? (string) $value : null;
        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    /**
     * Set default value.
     */
    public function setDefaultValue(?string $value): static
    {
        $this->defaultValue = $value;
        return $this;
    }

    /**
     * Add an option.
     *
     * @param string $value Export value
     * @param string|null $display Display text (defaults to value)
     */
    public function addOption(string $value, ?string $display = null): static
    {
        $this->options[] = [
            'value' => $value,
            'display' => $display ?? $value,
        ];
        return $this;
    }

    /**
     * Set options from array.
     *
     * @param array<string, string>|array<int, string> $options
     */
    public function setOptions(array $options): static
    {
        $this->options = [];
        foreach ($options as $key => $value) {
            if (is_int($key)) {
                $this->addOption($value, $value);
            } else {
                $this->addOption($key, $value);
            }
        }
        return $this;
    }

    /**
     * Get all options.
     *
     * @return array<int, array{value: string, display: string}>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Clear all options.
     */
    public function clearOptions(): static
    {
        $this->options = [];
        $this->selectedValue = null;
        return $this;
    }

    /**
     * Get selected value.
     */
    public function getSelectedValue(): ?string
    {
        return $this->selectedValue;
    }

    /**
     * Set selected value.
     */
    public function setSelectedValue(?string $value): static
    {
        $this->selectedValue = $value;
        return $this;
    }

    /**
     * Select by index.
     */
    public function selectByIndex(int $index): static
    {
        if (isset($this->options[$index])) {
            $this->selectedValue = $this->options[$index]['value'];
        }
        return $this;
    }

    /**
     * Get selected index.
     */
    public function getSelectedIndex(): ?int
    {
        return $this->findOptionIndex($this->selectedValue);
    }

    /**
     * Check if editable.
     */
    public function isEditable(): bool
    {
        return $this->editable;
    }

    /**
     * Set editable mode.
     */
    public function setEditable(bool $editable = true): static
    {
        $this->editable = $editable;
        return $this;
    }

    /**
     * Check if sorted.
     */
    public function isSorted(): bool
    {
        return $this->sorted;
    }

    /**
     * Set sorted mode.
     */
    public function setSorted(bool $sorted = true): static
    {
        $this->sorted = $sorted;
        return $this;
    }

    /**
     * Set commit on selection change.
     */
    public function setCommitOnSelChange(bool $commit = true): static
    {
        $this->commitOnSelChange = $commit;
        return $this;
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

        $da .= sprintf('/%s %.1f Tf', $this->fontName, $this->fontSize);

        return $da;
    }

    /**
     * Find option index by value.
     */
    private function findOptionIndex(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        foreach ($this->options as $index => $option) {
            if ($option['value'] === $value) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Get display text for current selection.
     */
    private function getSelectedDisplayText(): ?string
    {
        if ($this->selectedValue === null) {
            return null;
        }
        foreach ($this->options as $option) {
            if ($option['value'] === $this->selectedValue) {
                return $option['display'];
            }
        }
        return $this->selectedValue;
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
            $content .= sprintf('0 0 %.4f %.4f re S ', $width, $height);
        }

        // Dropdown arrow
        $arrowSize = $height * 0.5;
        $arrowX = $width - $arrowSize - 4;
        $arrowY = ($height - $arrowSize * 0.5) / 2;
        $content .= '0 g ';
        $content .= sprintf('%.4f %.4f m ', $arrowX, $arrowY + $arrowSize * 0.5);
        $content .= sprintf('%.4f %.4f l ', $arrowX + $arrowSize, $arrowY + $arrowSize * 0.5);
        $content .= sprintf('%.4f %.4f l ', $arrowX + $arrowSize / 2, $arrowY);
        $content .= 'f ';

        // Selected text
        $displayText = $this->getSelectedDisplayText();
        if ($displayText !== null) {
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
            $textY = ($height - $this->fontSize) / 2 + 2;
            $content .= sprintf('%.4f %.4f Td ', 4.0, $textY);
            $content .= sprintf('(%s) Tj ', $this->escapeText($displayText));
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
            'options' => $this->options,
            'selectedValue' => $this->selectedValue,
            'defaultValue' => $this->defaultValue,
            'editable' => $this->editable,
            'sorted' => $this->sorted,
            'commitOnSelChange' => $this->commitOnSelChange,
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
        $field = new self($data['name'] ?? 'Dropdown');
        $field->fromArrayBase($data);

        if (isset($data['options'])) {
            $field->options = $data['options'];
        }
        if (isset($data['selectedValue'])) {
            $field->selectedValue = $data['selectedValue'];
        }
        if (isset($data['defaultValue'])) {
            $field->defaultValue = $data['defaultValue'];
        }
        if (isset($data['editable'])) {
            $field->editable = (bool) $data['editable'];
        }
        if (isset($data['sorted'])) {
            $field->sorted = (bool) $data['sorted'];
        }
        if (isset($data['commitOnSelChange'])) {
            $field->commitOnSelChange = (bool) $data['commitOnSelChange'];
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
