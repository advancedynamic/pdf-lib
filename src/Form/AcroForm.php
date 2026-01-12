<?php

declare(strict_types=1);

namespace PdfLib\Form;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfBoolean;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfString;

/**
 * Represents the document-level AcroForm dictionary.
 *
 * The AcroForm dictionary defines the interactive form for the document,
 * containing all form fields and their configuration.
 *
 * @example
 * ```php
 * $acroForm = AcroForm::create()
 *     ->addField($textField)
 *     ->addField($checkbox)
 *     ->addRadioGroup($radioGroup)
 *     ->setNeedsAppearances(true);
 *
 * // Get the dictionary for writing
 * $dict = $acroForm->toDictionary();
 * ```
 */
final class AcroForm
{
    // Signature flags
    public const SIG_FLAGS_NONE = 0;
    public const SIG_FLAGS_SIGNATURES_EXIST = 1;
    public const SIG_FLAGS_APPEND_ONLY = 2;

    // Calculation order
    public const CO_ORDER_DOCUMENT = 'document';
    public const CO_ORDER_ROW = 'row';
    public const CO_ORDER_COLUMN = 'column';

    private FormFieldCollection $fields;

    /** @var array<string, RadioButtonGroup> */
    private array $radioGroups = [];

    private bool $needsAppearances = false;
    private int $sigFlags = self::SIG_FLAGS_NONE;
    private ?string $defaultAppearance = null;

    /** @var array<string, PdfReference> Default resources fonts */
    private array $defaultFonts = [];

    /** @var array<int, string> Calculation order (field names) */
    private array $calculationOrder = [];

    /** @var PdfDictionary|null XFA data */
    private ?PdfDictionary $xfa = null;

    public function __construct()
    {
        $this->fields = new FormFieldCollection();
    }

    /**
     * Create a new AcroForm.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Add a form field.
     *
     * @throws \PdfLib\Exception\FormException if field name already exists
     */
    public function addField(FormField $field): self
    {
        $this->fields->add($field);
        return $this;
    }

    /**
     * Add a radio button group.
     */
    public function addRadioGroup(RadioButtonGroup $group): self
    {
        $this->radioGroups[$group->getName()] = $group;
        return $this;
    }

    /**
     * Get all form fields.
     */
    public function getFields(): FormFieldCollection
    {
        return $this->fields;
    }

    /**
     * Get a field by name.
     */
    public function getField(string $name): ?FormField
    {
        return $this->fields->get($name);
    }

    /**
     * Check if a field exists.
     */
    public function hasField(string $name): bool
    {
        return $this->fields->has($name) || isset($this->radioGroups[$name]);
    }

    /**
     * Get all radio button groups.
     *
     * @return array<string, RadioButtonGroup>
     */
    public function getRadioGroups(): array
    {
        return $this->radioGroups;
    }

    /**
     * Get a radio button group by name.
     */
    public function getRadioGroup(string $name): ?RadioButtonGroup
    {
        return $this->radioGroups[$name] ?? null;
    }

    /**
     * Get all field names.
     *
     * @return array<int, string>
     */
    public function getFieldNames(): array
    {
        return array_merge(
            $this->fields->names(),
            array_keys($this->radioGroups)
        );
    }

    /**
     * Get all field values.
     *
     * @return array<string, mixed>
     */
    public function getFieldValues(): array
    {
        $values = $this->fields->getValues();

        foreach ($this->radioGroups as $name => $group) {
            $values[$name] = $group->getSelectedValue();
        }

        return $values;
    }

    /**
     * Set multiple field values.
     *
     * @param array<string, mixed> $values
     * @throws \PdfLib\Exception\FormException if a field is not found
     */
    public function setFieldValues(array $values): self
    {
        foreach ($values as $name => $value) {
            $field = $this->fields->get($name);
            if ($field !== null) {
                $field->setValue($value);
            } elseif (isset($this->radioGroups[$name])) {
                if (is_string($value)) {
                    $this->radioGroups[$name]->setSelectedValue($value);
                }
            } else {
                throw \PdfLib\Exception\FormException::fieldNotFound($name);
            }
        }
        return $this;
    }

    /**
     * Check if needs appearances flag is set.
     */
    public function needsAppearances(): bool
    {
        return $this->needsAppearances;
    }

    /**
     * Set needs appearances flag.
     *
     * When true, the PDF viewer should generate appearance streams.
     */
    public function setNeedsAppearances(bool $needs): self
    {
        $this->needsAppearances = $needs;
        return $this;
    }

    /**
     * Get signature flags.
     */
    public function getSigFlags(): int
    {
        return $this->sigFlags;
    }

    /**
     * Set signature flags.
     */
    public function setSigFlags(int $flags): self
    {
        $this->sigFlags = $flags;
        return $this;
    }

    /**
     * Mark that signatures exist in the document.
     */
    public function setSignaturesExist(bool $exist = true): self
    {
        if ($exist) {
            $this->sigFlags |= self::SIG_FLAGS_SIGNATURES_EXIST;
        } else {
            $this->sigFlags &= ~self::SIG_FLAGS_SIGNATURES_EXIST;
        }
        return $this;
    }

    /**
     * Set append-only mode (for signed documents).
     */
    public function setAppendOnly(bool $appendOnly = true): self
    {
        if ($appendOnly) {
            $this->sigFlags |= self::SIG_FLAGS_APPEND_ONLY;
        } else {
            $this->sigFlags &= ~self::SIG_FLAGS_APPEND_ONLY;
        }
        return $this;
    }

    /**
     * Get default appearance string.
     */
    public function getDefaultAppearance(): ?string
    {
        return $this->defaultAppearance;
    }

    /**
     * Set default appearance string.
     *
     * Example: "/Helv 12 Tf 0 g" (Helvetica 12pt, black)
     */
    public function setDefaultAppearance(string $da): self
    {
        $this->defaultAppearance = $da;
        return $this;
    }

    /**
     * Add a default font.
     */
    public function addDefaultFont(string $name, PdfReference $fontRef): self
    {
        $this->defaultFonts[$name] = $fontRef;
        return $this;
    }

    /**
     * Get default fonts.
     *
     * @return array<string, PdfReference>
     */
    public function getDefaultFonts(): array
    {
        return $this->defaultFonts;
    }

    /**
     * Set calculation order for fields with calculate actions.
     *
     * @param array<int, string> $fieldNames
     */
    public function setCalculationOrder(array $fieldNames): self
    {
        $this->calculationOrder = $fieldNames;
        return $this;
    }

    /**
     * Add a field to the calculation order.
     */
    public function addToCalculationOrder(string $fieldName): self
    {
        if (!in_array($fieldName, $this->calculationOrder, true)) {
            $this->calculationOrder[] = $fieldName;
        }
        return $this;
    }

    /**
     * Get calculation order.
     *
     * @return array<int, string>
     */
    public function getCalculationOrder(): array
    {
        return $this->calculationOrder;
    }

    /**
     * Set XFA data (XML Forms Architecture).
     */
    public function setXfa(PdfDictionary $xfa): self
    {
        $this->xfa = $xfa;
        return $this;
    }

    /**
     * Get XFA data.
     */
    public function getXfa(): ?PdfDictionary
    {
        return $this->xfa;
    }

    /**
     * Check if form has XFA data.
     */
    public function hasXfa(): bool
    {
        return $this->xfa !== null;
    }

    /**
     * Get total number of fields.
     */
    public function count(): int
    {
        return count($this->fields) + count($this->radioGroups);
    }

    /**
     * Check if form has any fields.
     */
    public function isEmpty(): bool
    {
        return $this->fields->isEmpty() && empty($this->radioGroups);
    }

    /**
     * Build the AcroForm dictionary.
     *
     * @param array<int, PdfReference> $fieldRefs References to field objects
     */
    public function toDictionary(array $fieldRefs = []): PdfDictionary
    {
        $dict = new PdfDictionary();

        // Fields array
        if (!empty($fieldRefs)) {
            $dict->set('Fields', new PdfArray($fieldRefs));
        } else {
            $dict->set('Fields', new PdfArray());
        }

        // NeedAppearances
        if ($this->needsAppearances) {
            $dict->set('NeedAppearances', PdfBoolean::create(true));
        }

        // SigFlags
        if ($this->sigFlags !== self::SIG_FLAGS_NONE) {
            $dict->set('SigFlags', PdfNumber::int($this->sigFlags));
        }

        // Default Resources (DR)
        if (!empty($this->defaultFonts)) {
            $dr = new PdfDictionary();
            $fontDict = new PdfDictionary();
            foreach ($this->defaultFonts as $name => $ref) {
                $fontDict->set($name, $ref);
            }
            $dr->set('Font', $fontDict);
            $dict->set('DR', $dr);
        }

        // Default Appearance (DA)
        if ($this->defaultAppearance !== null) {
            $dict->set('DA', PdfString::literal($this->defaultAppearance));
        }

        // Calculation Order (CO)
        if (!empty($this->calculationOrder)) {
            // Note: CO should contain references to fields, not names
            // This will be populated when field refs are available
        }

        // XFA
        if ($this->xfa !== null) {
            $dict->set('XFA', $this->xfa);
        }

        return $dict;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fields' => $this->fields->toArray(),
            'radioGroups' => array_map(
                static fn(RadioButtonGroup $g): array => $g->toArray(),
                $this->radioGroups
            ),
            'needsAppearances' => $this->needsAppearances,
            'sigFlags' => $this->sigFlags,
            'defaultAppearance' => $this->defaultAppearance,
            'calculationOrder' => $this->calculationOrder,
        ];
    }

    /**
     * Create from array representation.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $acroForm = new self();

        if (isset($data['needsAppearances'])) {
            $acroForm->needsAppearances = (bool) $data['needsAppearances'];
        }

        if (isset($data['sigFlags']) && is_numeric($data['sigFlags'])) {
            $acroForm->sigFlags = (int) $data['sigFlags'];
        }

        if (isset($data['defaultAppearance']) && is_string($data['defaultAppearance'])) {
            $acroForm->defaultAppearance = $data['defaultAppearance'];
        }

        if (isset($data['calculationOrder']) && is_array($data['calculationOrder'])) {
            /** @var array<int, string> $order */
            $order = $data['calculationOrder'];
            $acroForm->calculationOrder = $order;
        }

        // Fields and radioGroups would be restored separately
        // since they require instantiating the correct field types

        return $acroForm;
    }

    /**
     * Create a text field and add it to the form.
     */
    public function createTextField(string $name): TextField
    {
        $field = TextField::create($name);
        $this->addField($field);
        return $field;
    }

    /**
     * Create a checkbox and add it to the form.
     */
    public function createCheckbox(string $name): CheckboxField
    {
        $field = CheckboxField::create($name);
        $this->addField($field);
        return $field;
    }

    /**
     * Create a dropdown and add it to the form.
     */
    public function createDropdown(string $name): DropdownField
    {
        $field = DropdownField::create($name);
        $this->addField($field);
        return $field;
    }

    /**
     * Create a list box and add it to the form.
     */
    public function createListBox(string $name): ListBoxField
    {
        $field = ListBoxField::create($name);
        $this->addField($field);
        return $field;
    }

    /**
     * Create a radio button group and add it to the form.
     */
    public function createRadioGroup(string $name): RadioButtonGroup
    {
        $group = RadioButtonGroup::create($name);
        $this->addRadioGroup($group);
        return $group;
    }

    /**
     * Remove a field by name.
     */
    public function removeField(string $name): self
    {
        if ($this->fields->has($name)) {
            $this->fields->remove($name);
        } elseif (isset($this->radioGroups[$name])) {
            unset($this->radioGroups[$name]);
        }
        return $this;
    }

    /**
     * Clear all fields.
     */
    public function clear(): self
    {
        $this->fields->clear();
        $this->radioGroups = [];
        return $this;
    }
}
