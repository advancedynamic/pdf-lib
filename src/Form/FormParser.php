<?php

declare(strict_types=1);

namespace PdfLib\Form;

use PdfLib\Exception\FormException;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfString;
use PdfLib\Parser\PdfParser;

/**
 * Parses form fields from existing PDF documents.
 *
 * @example
 * ```php
 * $parser = FormParser::fromFile('form.pdf');
 *
 * if ($parser->hasForm()) {
 *     $fields = $parser->getFields();
 *     foreach ($fields as $field) {
 *         echo $field->getName() . ': ' . $field->getValue() . "\n";
 *     }
 * }
 *
 * // Get specific field
 * $nameField = $parser->getField('name');
 * if ($nameField !== null) {
 *     echo $nameField->getValue();
 * }
 * ```
 */
final class FormParser
{
    private PdfParser $parser;
    private ?PdfDictionary $acroFormDict = null;
    private bool $parsed = false;

    /** @var array<string, FormField> */
    private array $fields = [];

    /** @var array<string, RadioButtonGroup> */
    private array $radioGroups = [];

    public function __construct(PdfParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Create parser from file.
     */
    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("PDF file not found: $path");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Could not read PDF file: $path");
        }

        return self::fromString($content);
    }

    /**
     * Create parser from PDF content.
     */
    public static function fromString(string $content): self
    {
        $parser = PdfParser::parseString($content);
        return new self($parser);
    }

    /**
     * Create from existing parser.
     */
    public static function fromParser(PdfParser $parser): self
    {
        return new self($parser);
    }

    /**
     * Check if document has a form.
     */
    public function hasForm(): bool
    {
        $this->loadAcroForm();
        return $this->acroFormDict !== null;
    }

    /**
     * Get the AcroForm as a structured object.
     */
    public function getAcroForm(): ?AcroForm
    {
        if (!$this->hasForm()) {
            return null;
        }

        $this->parseFields();

        $acroForm = AcroForm::create();

        // Add parsed fields
        foreach ($this->fields as $field) {
            try {
                $acroForm->addField($field);
            } catch (\Exception $e) {
                // Skip duplicate fields
            }
        }

        // Add radio groups
        foreach ($this->radioGroups as $group) {
            $acroForm->addRadioGroup($group);
        }

        // Set flags
        if ($this->acroFormDict !== null) {
            if ($this->acroFormDict->has('NeedAppearances')) {
                $na = $this->acroFormDict->get('NeedAppearances');
                if ($na instanceof \PdfLib\Parser\Object\PdfBoolean) {
                    $acroForm->setNeedsAppearances($na->getValue());
                }
            }

            if ($this->acroFormDict->has('SigFlags')) {
                $sf = $this->acroFormDict->get('SigFlags');
                if ($sf instanceof \PdfLib\Parser\Object\PdfNumber) {
                    $acroForm->setSigFlags((int) $sf->getValue());
                }
            }

            if ($this->acroFormDict->has('DA')) {
                $da = $this->resolveValue($this->acroFormDict->get('DA'));
                if ($da instanceof PdfString) {
                    $acroForm->setDefaultAppearance($da->getValue());
                }
            }
        }

        return $acroForm;
    }

    /**
     * Get a specific field by name.
     */
    public function getField(string $name): ?FormField
    {
        $this->parseFields();
        return $this->fields[$name] ?? null;
    }

    /**
     * Get all parsed fields.
     *
     * @return array<string, FormField>
     */
    public function getFields(): array
    {
        $this->parseFields();
        return $this->fields;
    }

    /**
     * Get all radio button groups.
     *
     * @return array<string, RadioButtonGroup>
     */
    public function getRadioGroups(): array
    {
        $this->parseFields();
        return $this->radioGroups;
    }

    /**
     * Get all field names.
     *
     * @return array<int, string>
     */
    public function getFieldNames(): array
    {
        $this->parseFields();
        return array_merge(
            array_keys($this->fields),
            array_keys($this->radioGroups)
        );
    }

    /**
     * Get all field values as associative array.
     *
     * @return array<string, mixed>
     */
    public function getFieldValues(): array
    {
        $this->parseFields();

        $values = [];
        foreach ($this->fields as $name => $field) {
            $values[$name] = $field->getValue();
        }
        foreach ($this->radioGroups as $name => $group) {
            $values[$name] = $group->getSelectedValue();
        }

        return $values;
    }

    /**
     * Load AcroForm dictionary from document.
     */
    private function loadAcroForm(): void
    {
        if ($this->acroFormDict !== null) {
            return;
        }

        $catalog = $this->parser->getCatalog();
        if ($catalog === null || !$catalog->has('AcroForm')) {
            return;
        }

        $acroFormObj = $this->resolveValue($catalog->get('AcroForm'));
        if ($acroFormObj instanceof PdfDictionary) {
            $this->acroFormDict = $acroFormObj;
        }
    }

    /**
     * Parse all form fields.
     */
    private function parseFields(): void
    {
        if ($this->parsed) {
            return;
        }

        $this->parsed = true;
        $this->loadAcroForm();

        if ($this->acroFormDict === null || !$this->acroFormDict->has('Fields')) {
            return;
        }

        $fieldsArray = $this->resolveValue($this->acroFormDict->get('Fields'));
        if (!$fieldsArray instanceof PdfArray) {
            return;
        }

        foreach ($fieldsArray->getItems() as $fieldRef) {
            $fieldDict = $this->resolveValue($fieldRef);
            if ($fieldDict instanceof PdfDictionary) {
                $this->parseFieldDict($fieldDict, null);
            }
        }
    }

    /**
     * Parse a single field dictionary.
     */
    private function parseFieldDict(PdfDictionary $dict, ?string $parentName): void
    {
        // Get field name
        $name = $this->getFieldName($dict, $parentName);

        // Check if this is a parent with Kids
        if ($dict->has('Kids')) {
            $kids = $this->resolveValue($dict->get('Kids'));
            if ($kids instanceof PdfArray) {
                // Check if this is a radio button group
                $fieldType = $this->getInheritedFieldType($dict);
                if ($fieldType === 'Btn' && $this->isRadioButton($dict)) {
                    $this->parseRadioGroup($dict, $name, $kids);
                } else {
                    // Process child fields
                    foreach ($kids->getItems() as $kidRef) {
                        $kidDict = $this->resolveValue($kidRef);
                        if ($kidDict instanceof PdfDictionary) {
                            $this->parseFieldDict($kidDict, $name);
                        }
                    }
                }
                return;
            }
        }

        // Parse as terminal field
        $field = $this->createFieldFromDict($dict, $parentName);
        if ($field !== null && !isset($this->fields[$name])) {
            $this->fields[$name] = $field;
        }
    }

    /**
     * Parse a radio button group.
     */
    private function parseRadioGroup(PdfDictionary $dict, string $name, PdfArray $kids): void
    {
        $group = RadioButtonGroup::create($name);

        // Get selected value
        $selectedValue = null;
        if ($dict->has('V')) {
            $v = $this->resolveValue($dict->get('V'));
            if ($v instanceof PdfName) {
                $selectedValue = $v->getValue();
                if ($selectedValue === 'Off') {
                    $selectedValue = null;
                }
            }
        }

        // Parse options
        $optionIndex = 0;
        foreach ($kids->getItems() as $kidRef) {
            $kidDict = $this->resolveValue($kidRef);
            if (!$kidDict instanceof PdfDictionary) {
                continue;
            }

            // Get option value from appearance state name
            $optionValue = $this->getRadioOptionValue($kidDict, $optionIndex);
            if ($optionValue === null) {
                $optionValue = 'option' . $optionIndex;
            }

            // Get position
            $rect = $this->parseRect($kidDict);

            $group->addOption(
                $optionValue,
                $rect[0],
                $rect[1],
                abs($rect[2] - $rect[0])
            );

            // Get page
            if ($kidDict->has('P')) {
                $pageRef = $kidDict->get('P');
                if ($pageRef instanceof PdfReference) {
                    $pageNum = $this->findPageNumber($pageRef);
                    if ($pageNum > 0) {
                        $option = $group->getOption($optionValue);
                        if ($option !== null) {
                            $option->setPage($pageNum);
                        }
                    }
                }
            }

            $optionIndex++;
        }

        // Set selected value
        if ($selectedValue !== null) {
            $group->setSelectedValue($selectedValue);
        }

        $this->radioGroups[$name] = $group;
    }

    /**
     * Create a FormField from dictionary.
     */
    private function createFieldFromDict(PdfDictionary $dict, ?string $parentName): ?FormField
    {
        $fieldType = $this->getInheritedFieldType($dict);
        $name = $this->getFieldName($dict, $parentName);

        $field = match ($fieldType) {
            'Tx' => $this->parseTextField($dict, $name),
            'Btn' => $this->parseButtonField($dict, $name),
            'Ch' => $this->parseChoiceField($dict, $name),
            default => null,
        };

        if ($field === null) {
            return null;
        }

        // Set common properties
        $this->setCommonProperties($field, $dict);

        return $field;
    }

    /**
     * Parse a text field.
     */
    private function parseTextField(PdfDictionary $dict, string $name): TextField
    {
        $field = TextField::create($name);

        // Get value
        if ($dict->has('V')) {
            $v = $this->resolveValue($dict->get('V'));
            if ($v instanceof PdfString) {
                $field->setValue($v->getValue());
            }
        }

        // Get default value
        if ($dict->has('DV')) {
            $dv = $this->resolveValue($dict->get('DV'));
            if ($dv instanceof PdfString) {
                $field->setDefaultValue($dv->getValue());
            }
        }

        // Check flags
        $ff = $this->getFieldFlags($dict);
        if ($ff & TextField::FLAG_MULTILINE) {
            $field->setMultiline(true);
        }
        if ($ff & TextField::FLAG_PASSWORD) {
            $field->setPassword(true);
        }
        if ($ff & TextField::FLAG_COMB) {
            $field->setComb(true);
        }

        // Max length
        if ($dict->has('MaxLen')) {
            $ml = $this->resolveValue($dict->get('MaxLen'));
            if ($ml instanceof \PdfLib\Parser\Object\PdfNumber) {
                $field->setMaxLength((int) $ml->getValue());
            }
        }

        return $field;
    }

    /**
     * Parse a button field (checkbox or radio).
     */
    private function parseButtonField(PdfDictionary $dict, string $name): ?FormField
    {
        $ff = $this->getFieldFlags($dict);

        // Radio buttons are handled separately in parseRadioGroup
        if ($ff & RadioButtonGroup::FLAG_RADIO) {
            return null;
        }

        // This is a checkbox
        $field = CheckboxField::create($name);

        // Get value
        if ($dict->has('V')) {
            $v = $this->resolveValue($dict->get('V'));
            if ($v instanceof PdfName) {
                $value = $v->getValue();
                $field->setChecked($value !== 'Off');
                if ($value !== 'Off' && $value !== 'Yes') {
                    $field->setExportValue($value);
                }
            }
        }

        // Get appearance state
        if ($dict->has('AS')) {
            $as = $this->resolveValue($dict->get('AS'));
            if ($as instanceof PdfName) {
                $field->setChecked($as->getValue() !== 'Off');
            }
        }

        return $field;
    }

    /**
     * Parse a choice field (dropdown or listbox).
     */
    private function parseChoiceField(PdfDictionary $dict, string $name): ?FormField
    {
        $ff = $this->getFieldFlags($dict);

        if ($ff & DropdownField::FLAG_COMBO) {
            return $this->parseDropdownField($dict, $name, $ff);
        }

        return $this->parseListBoxField($dict, $name, $ff);
    }

    /**
     * Parse a dropdown field.
     */
    private function parseDropdownField(PdfDictionary $dict, string $name, int $ff): DropdownField
    {
        $field = DropdownField::create($name);

        // Parse options
        $this->parseChoiceOptions($dict, $field);

        // Get value
        if ($dict->has('V')) {
            $v = $this->resolveValue($dict->get('V'));
            if ($v instanceof PdfString) {
                $field->setSelectedValue($v->getValue());
            }
        }

        // Editable
        if ($ff & DropdownField::FLAG_EDIT) {
            $field->setEditable(true);
        }

        // Sorted
        if ($ff & DropdownField::FLAG_SORT) {
            $field->setSorted(true);
        }

        return $field;
    }

    /**
     * Parse a listbox field.
     */
    private function parseListBoxField(PdfDictionary $dict, string $name, int $ff): ListBoxField
    {
        $field = ListBoxField::create($name);

        // Parse options
        $this->parseChoiceOptions($dict, $field);

        // Get selected values
        if ($dict->has('V')) {
            $v = $this->resolveValue($dict->get('V'));
            if ($v instanceof PdfString) {
                $field->setSelectedValue($v->getValue());
            } elseif ($v instanceof PdfArray) {
                $values = [];
                foreach ($v->getItems() as $item) {
                    $resolved = $this->resolveValue($item);
                    if ($resolved instanceof PdfString) {
                        $values[] = $resolved->getValue();
                    }
                }
                $field->setSelectedValues($values);
            }
        }

        // Multi-select
        if ($ff & ListBoxField::FLAG_MULTI_SELECT) {
            $field->setMultiSelect(true);
        }

        // Sorted
        if ($ff & ListBoxField::FLAG_SORT) {
            $field->setSorted(true);
        }

        return $field;
    }

    /**
     * Parse choice field options.
     *
     * @param DropdownField|ListBoxField $field
     */
    private function parseChoiceOptions(PdfDictionary $dict, FormField $field): void
    {
        if (!$dict->has('Opt')) {
            return;
        }

        $opt = $this->resolveValue($dict->get('Opt'));
        if (!$opt instanceof PdfArray) {
            return;
        }

        foreach ($opt->getItems() as $item) {
            $resolved = $this->resolveValue($item);

            if ($resolved instanceof PdfString) {
                // Simple option: value = display
                $field->addOption($resolved->getValue());
            } elseif ($resolved instanceof PdfArray && $resolved->count() >= 2) {
                // Complex option: [exportValue, displayValue]
                $items = $resolved->getItems();
                $export = $this->resolveValue($items[0]);
                $display = $this->resolveValue($items[1]);

                if ($export instanceof PdfString && $display instanceof PdfString) {
                    $field->addOption($export->getValue(), $display->getValue());
                }
            }
        }
    }

    /**
     * Set common field properties.
     */
    private function setCommonProperties(FormField $field, PdfDictionary $dict): void
    {
        // Rectangle/position
        $rect = $this->parseRect($dict);
        $field->setRect($rect);

        // Page
        if ($dict->has('P')) {
            $pageRef = $dict->get('P');
            if ($pageRef instanceof PdfReference) {
                $pageNum = $this->findPageNumber($pageRef);
                if ($pageNum > 0) {
                    $field->setPage($pageNum);
                }
            }
        }

        // Flags
        $ff = $this->getFieldFlags($dict);
        if ($ff & FormField::FLAG_READ_ONLY) {
            $field->setReadOnly(true);
        }
        if ($ff & FormField::FLAG_REQUIRED) {
            $field->setRequired(true);
        }
        if ($ff & FormField::FLAG_NO_EXPORT) {
            $field->setNoExport(true);
        }

        // Tooltip
        if ($dict->has('TU')) {
            $tu = $this->resolveValue($dict->get('TU'));
            if ($tu instanceof PdfString) {
                $field->setTooltip($tu->getValue());
            }
        }

        // Mapping name
        if ($dict->has('TM')) {
            $tm = $this->resolveValue($dict->get('TM'));
            if ($tm instanceof PdfString) {
                $field->setMappingName($tm->getValue());
            }
        }
    }

    /**
     * Get field name, handling inheritance.
     */
    private function getFieldName(PdfDictionary $dict, ?string $parentName): string
    {
        $partialName = '';

        if ($dict->has('T')) {
            $t = $this->resolveValue($dict->get('T'));
            if ($t instanceof PdfString) {
                $partialName = $t->getValue();
            }
        }

        if ($parentName !== null && $partialName !== '') {
            return $parentName . '.' . $partialName;
        }

        return $partialName !== '' ? $partialName : 'unnamed';
    }

    /**
     * Get inherited field type.
     */
    private function getInheritedFieldType(PdfDictionary $dict): ?string
    {
        if ($dict->has('FT')) {
            $ft = $this->resolveValue($dict->get('FT'));
            if ($ft instanceof PdfName) {
                return $ft->getValue();
            }
        }

        // Check parent
        if ($dict->has('Parent')) {
            $parent = $this->resolveValue($dict->get('Parent'));
            if ($parent instanceof PdfDictionary) {
                return $this->getInheritedFieldType($parent);
            }
        }

        return null;
    }

    /**
     * Get field flags, handling inheritance.
     */
    private function getFieldFlags(PdfDictionary $dict): int
    {
        if ($dict->has('Ff')) {
            $ff = $this->resolveValue($dict->get('Ff'));
            if ($ff instanceof \PdfLib\Parser\Object\PdfNumber) {
                return (int) $ff->getValue();
            }
        }

        // Check parent
        if ($dict->has('Parent')) {
            $parent = $this->resolveValue($dict->get('Parent'));
            if ($parent instanceof PdfDictionary) {
                return $this->getFieldFlags($parent);
            }
        }

        return 0;
    }

    /**
     * Check if button is a radio button.
     */
    private function isRadioButton(PdfDictionary $dict): bool
    {
        $ff = $this->getFieldFlags($dict);
        return ($ff & RadioButtonGroup::FLAG_RADIO) !== 0;
    }

    /**
     * Get radio option value from appearance dictionary.
     */
    private function getRadioOptionValue(PdfDictionary $dict, int $index): ?string
    {
        // Try to get from AP/N dictionary keys (excluding 'Off')
        if ($dict->has('AP')) {
            $ap = $this->resolveValue($dict->get('AP'));
            if ($ap instanceof PdfDictionary && $ap->has('N')) {
                $n = $this->resolveValue($ap->get('N'));
                if ($n instanceof PdfDictionary) {
                    foreach ($n->getKeys() as $key) {
                        if ($key !== 'Off') {
                            return $key;
                        }
                    }
                }
            }
        }

        // Try AS (appearance state)
        if ($dict->has('AS')) {
            $as = $this->resolveValue($dict->get('AS'));
            if ($as instanceof PdfName) {
                $value = $as->getValue();
                if ($value !== 'Off') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Parse Rect array.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function parseRect(PdfDictionary $dict): array
    {
        $default = [0.0, 0.0, 100.0, 20.0];

        if (!$dict->has('Rect')) {
            return $default;
        }

        $rect = $this->resolveValue($dict->get('Rect'));
        if (!$rect instanceof PdfArray || $rect->count() < 4) {
            return $default;
        }

        $items = $rect->getItems();
        $values = [];

        for ($i = 0; $i < 4; $i++) {
            $item = $this->resolveValue($items[$i]);
            if ($item instanceof \PdfLib\Parser\Object\PdfNumber) {
                $values[] = (float) $item->getValue();
            } else {
                $values[] = 0.0;
            }
        }

        return [$values[0], $values[1], $values[2], $values[3]];
    }

    /**
     * Find page number for a page reference.
     */
    private function findPageNumber(PdfReference $pageRef): int
    {
        $pages = $this->parser->getPages();
        foreach ($pages as $index => $pageDict) {
            // This is a simplified check - actual implementation would compare references
            if ($pageDict instanceof PdfDictionary) {
                return $index + 1;
            }
        }
        return 1;
    }

    /**
     * Resolve a value (follow references).
     */
    private function resolveValue(mixed $value): mixed
    {
        if ($value instanceof PdfReference) {
            return $this->parser->getObject(
                $value->getObjectNumber(),
                $value->getGenerationNumber()
            );
        }
        return $value;
    }
}
