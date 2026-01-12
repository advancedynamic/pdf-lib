<?php

declare(strict_types=1);

namespace PdfLib\Form;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfString;

/**
 * List box field with optional multi-select support for PDF forms.
 *
 * @example
 * ```php
 * $field = ListBoxField::create('interests')
 *     ->setPosition(100, 400)
 *     ->setSize(200, 100)
 *     ->setMultiSelect()
 *     ->addOption('technology', 'Technology')
 *     ->addOption('sports', 'Sports')
 *     ->addOption('music', 'Music')
 *     ->setSelectedValues(['technology', 'music']);
 * ```
 */
final class ListBoxField extends FormField
{
    // Choice field flags
    public const FLAG_SORT = 524288;                // Bit 20: Sort options
    public const FLAG_MULTI_SELECT = 2097152;       // Bit 22
    public const FLAG_DO_NOT_SPELL_CHECK = 4194304; // Bit 23
    public const FLAG_COMMIT_ON_SEL_CHANGE = 67108864; // Bit 27

    /** @var array<int, array{value: string, display: string}> */
    private array $options = [];
    /** @var array<int, string> */
    private array $selectedValues = [];
    /** @var array<int, string> */
    private array $defaultValues = [];
    private bool $multiSelect = false;
    private bool $sorted = false;
    private bool $commitOnSelChange = false;

    // Text appearance
    private string $fontName = 'Helvetica';
    private float $fontSize = 10;
    /** @var array{0: float, 1: float, 2: float}|null */
    private ?array $textColor = null;

    /**
     * Create a new list box field.
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
        $flags = 0;

        if ($this->multiSelect) {
            $flags |= self::FLAG_MULTI_SELECT;
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
        // Options array
        $optArray = new PdfArray();
        foreach ($this->options as $option) {
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

        // Selected indices
        if (!empty($this->selectedValues)) {
            $indices = [];
            foreach ($this->options as $index => $option) {
                if (in_array($option['value'], $this->selectedValues, true)) {
                    $indices[] = PdfNumber::int($index);
                }
            }
            if (!empty($indices)) {
                $dict->set('I', new PdfArray($indices));
            }
        }
    }

    /**
     * @return array<int, string>|string|null
     */
    public function getValue(): array|string|null
    {
        if (empty($this->selectedValues)) {
            return null;
        }
        if (!$this->multiSelect && count($this->selectedValues) === 1) {
            return $this->selectedValues[0];
        }
        return $this->selectedValues;
    }

    public function setValue(mixed $value): static
    {
        if ($value === null) {
            $this->selectedValues = [];
        } elseif (is_array($value)) {
            $this->selectedValues = array_map('strval', $value);
        } else {
            $this->selectedValues = [(string) $value];
        }
        return $this;
    }

    /**
     * @return array<int, string>|string|null
     */
    public function getDefaultValue(): array|string|null
    {
        if (empty($this->defaultValues)) {
            return null;
        }
        if (!$this->multiSelect && count($this->defaultValues) === 1) {
            return $this->defaultValues[0];
        }
        return $this->defaultValues;
    }

    /**
     * Override to handle multiple values.
     */
    protected function getValueForPdf(): ?PdfObject
    {
        if (empty($this->selectedValues)) {
            return null;
        }
        if (count($this->selectedValues) === 1) {
            return PdfString::literal($this->selectedValues[0]);
        }
        return PdfArray::fromValues(
            array_map(
                static fn(string $v): PdfString => PdfString::literal($v),
                $this->selectedValues
            )
        );
    }

    /**
     * Add an option.
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
        $this->selectedValues = [];
        return $this;
    }

    /**
     * Get selected values.
     *
     * @return array<int, string>
     */
    public function getSelectedValues(): array
    {
        return $this->selectedValues;
    }

    /**
     * Set selected value (single).
     */
    public function setSelectedValue(string $value): static
    {
        $this->selectedValues = [$value];
        return $this;
    }

    /**
     * Set selected values (multiple).
     *
     * @param array<int, string> $values
     */
    public function setSelectedValues(array $values): static
    {
        $this->selectedValues = $values;
        return $this;
    }

    /**
     * Add to selection.
     */
    public function addSelection(string $value): static
    {
        if (!in_array($value, $this->selectedValues, true)) {
            $this->selectedValues[] = $value;
        }
        return $this;
    }

    /**
     * Remove from selection.
     */
    public function removeSelection(string $value): static
    {
        $this->selectedValues = array_values(
            array_filter(
                $this->selectedValues,
                static fn(string $v): bool => $v !== $value
            )
        );
        return $this;
    }

    /**
     * Clear selection.
     */
    public function clearSelection(): static
    {
        $this->selectedValues = [];
        return $this;
    }

    /**
     * Get selected indices.
     *
     * @return array<int, int>
     */
    public function getSelectedIndices(): array
    {
        $indices = [];
        foreach ($this->options as $index => $option) {
            if (in_array($option['value'], $this->selectedValues, true)) {
                $indices[] = $index;
            }
        }
        return $indices;
    }

    /**
     * Check if multi-select is enabled.
     */
    public function isMultiSelect(): bool
    {
        return $this->multiSelect;
    }

    /**
     * Set multi-select mode.
     */
    public function setMultiSelect(bool $multiSelect = true): static
    {
        $this->multiSelect = $multiSelect;
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

        // Draw visible options
        $lineHeight = $this->fontSize + 2;
        $visibleLines = (int) floor($height / $lineHeight);
        $yOffset = $height - $lineHeight;

        foreach (array_slice($this->options, 0, $visibleLines) as $option) {
            $isSelected = in_array($option['value'], $this->selectedValues, true);

            if ($isSelected) {
                // Highlight background
                $content .= '0.8 0.9 1 rg ';
                $content .= sprintf(
                    '2 %.4f %.4f %.4f re f ',
                    $yOffset - 1,
                    $width - 4,
                    $lineHeight
                );
            }

            // Text
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
            $content .= sprintf('4 %.4f Td ', $yOffset + 2);
            $content .= sprintf('(%s) Tj ', $this->escapeText($option['display']));
            $content .= 'ET ';

            $yOffset -= $lineHeight;
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
            'selectedValues' => $this->selectedValues,
            'defaultValues' => $this->defaultValues,
            'multiSelect' => $this->multiSelect,
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
        $field = new self($data['name'] ?? 'ListBox');
        $field->fromArrayBase($data);

        if (isset($data['options'])) {
            $field->options = $data['options'];
        }
        if (isset($data['selectedValues'])) {
            $field->selectedValues = $data['selectedValues'];
        }
        if (isset($data['defaultValues'])) {
            $field->defaultValues = $data['defaultValues'];
        }
        if (isset($data['multiSelect'])) {
            $field->multiSelect = (bool) $data['multiSelect'];
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
