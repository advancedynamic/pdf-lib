<?php

declare(strict_types=1);

namespace PdfLib\Form;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfString;

/**
 * Radio button group container for PDF forms.
 *
 * Radio buttons in PDF are grouped under a parent field that contains
 * child widget annotations. Only one option can be selected at a time.
 *
 * @example
 * ```php
 * $group = RadioButtonGroup::create('payment_method')
 *     ->setPage(1)
 *     ->addOption('credit_card', 100, 500)
 *     ->addOption('paypal', 100, 480)
 *     ->addOption('bank_transfer', 100, 460)
 *     ->setSelectedValue('credit_card');
 * ```
 */
final class RadioButtonGroup
{
    // Button flags for radio buttons
    public const FLAG_NO_TOGGLE_TO_OFF = 16384;     // Bit 15
    public const FLAG_RADIO = 32768;                 // Bit 16
    public const FLAG_RADIOS_IN_UNISON = 33554432;   // Bit 26

    private string $name;
    private int $page = 1;
    /** @var array<string, RadioButtonOption> */
    private array $options = [];
    private ?string $selectedValue = null;
    private bool $noToggleToOff = true;
    private bool $required = false;

    public function __construct(string $name)
    {
        $this->name = $this->sanitizeFieldName($name);
    }

    /**
     * Create a new radio button group.
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * Get group name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get page number.
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Set page number for all options.
     */
    public function setPage(int $page): self
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page number must be >= 1');
        }
        $this->page = $page;
        foreach ($this->options as $option) {
            $option->setPage($page);
        }
        return $this;
    }

    /**
     * Add a radio button option.
     *
     * @param string $value Option value
     * @param float $x X position
     * @param float $y Y position
     * @param float $size Size (width and height)
     */
    public function addOption(
        string $value,
        float $x,
        float $y,
        float $size = 16
    ): self {
        $option = new RadioButtonOption($this->name, $value);
        $option->setPosition($x, $y)
               ->setSize($size, $size)
               ->setPage($this->page);

        $this->options[$value] = $option;

        return $this;
    }

    /**
     * Add a pre-configured option.
     */
    public function addOptionObject(RadioButtonOption $option): self
    {
        $option->setGroupName($this->name);
        $this->options[$option->getOptionValue()] = $option;
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
    public function setSelectedValue(string $value): self
    {
        if (isset($this->options[$value])) {
            $this->selectedValue = $value;
            foreach ($this->options as $optValue => $option) {
                $option->setSelected($optValue === $value);
            }
        }
        return $this;
    }

    /**
     * Clear selection.
     */
    public function clearSelection(): self
    {
        $this->selectedValue = null;
        foreach ($this->options as $option) {
            $option->setSelected(false);
        }
        return $this;
    }

    /**
     * Get all options.
     *
     * @return array<string, RadioButtonOption>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get an option by value.
     */
    public function getOption(string $value): ?RadioButtonOption
    {
        return $this->options[$value] ?? null;
    }

    /**
     * Check if required.
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Set required flag.
     */
    public function setRequired(bool $required = true): self
    {
        $this->required = $required;
        return $this;
    }

    /**
     * Set no toggle to off behavior.
     */
    public function setNoToggleToOff(bool $noToggleToOff = true): self
    {
        $this->noToggleToOff = $noToggleToOff;
        return $this;
    }

    /**
     * Get field flags.
     */
    public function getFieldFlags(): int
    {
        $flags = self::FLAG_RADIO;
        if ($this->noToggleToOff) {
            $flags |= self::FLAG_NO_TOGGLE_TO_OFF;
        }
        if ($this->required) {
            $flags |= FormField::FLAG_REQUIRED;
        }
        return $flags;
    }

    /**
     * Build parent field dictionary.
     */
    public function toFieldDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('FT', PdfName::create('Btn'));
        $dict->set('Ff', PdfNumber::int($this->getFieldFlags()));
        $dict->set('T', PdfString::literal($this->name));

        if ($this->selectedValue !== null) {
            $dict->set('V', PdfName::create($this->selectedValue));
        }

        return $dict;
    }

    /**
     * Sanitize field name.
     */
    private function sanitizeFieldName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $name);
        return $name !== '' && $name !== null ? $name : 'RadioGroup';
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => self::class,
            'name' => $this->name,
            'page' => $this->page,
            'selectedValue' => $this->selectedValue,
            'noToggleToOff' => $this->noToggleToOff,
            'required' => $this->required,
            'options' => array_map(
                static fn(RadioButtonOption $opt): array => $opt->toArray(),
                $this->options
            ),
        ];
    }

    /**
     * Create from array representation.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $group = new self($data['name'] ?? 'RadioGroup');

        if (isset($data['page'])) {
            $group->page = (int) $data['page'];
        }
        if (isset($data['noToggleToOff'])) {
            $group->noToggleToOff = (bool) $data['noToggleToOff'];
        }
        if (isset($data['required'])) {
            $group->required = (bool) $data['required'];
        }
        if (isset($data['options'])) {
            foreach ($data['options'] as $optData) {
                $option = RadioButtonOption::fromArray($optData);
                $option->setGroupName($group->name);
                $group->options[$option->getOptionValue()] = $option;
            }
        }
        if (isset($data['selectedValue'])) {
            $group->setSelectedValue($data['selectedValue']);
        }

        return $group;
    }
}
