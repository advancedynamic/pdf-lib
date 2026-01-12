<?php

declare(strict_types=1);

namespace PdfLib\Form;

use PdfLib\Exception\FormException;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfBoolean;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfStream;
use PdfLib\Parser\Object\PdfString;
use PdfLib\Parser\PdfParser;

/**
 * Fills PDF form fields with values using incremental updates.
 *
 * This class modifies existing PDFs by appending incremental updates,
 * preserving the original document and any existing signatures.
 *
 * @example
 * ```php
 * $filler = FormFiller::load('form.pdf');
 *
 * $filler->setFieldValue('name', 'John Doe')
 *        ->setFieldValue('email', 'john@example.com')
 *        ->setFieldValue('agree', true)
 *        ->setFieldValue('country', 'us');
 *
 * $filler->fillToFile('filled.pdf');
 * ```
 */
final class FormFiller
{
    private string $content;
    private PdfParser $parser;
    private FormParser $formParser;

    /** @var array<string, mixed> Field values to set */
    private array $values = [];

    private int $nextObjectId;

    /** @var array<int, int> Object ID => byte offset */
    private array $objectOffsets = [];

    public function __construct(string $content)
    {
        if (!str_starts_with($content, '%PDF-')) {
            throw new \InvalidArgumentException('Invalid PDF content');
        }

        $this->content = $content;
        $this->parser = PdfParser::parseString($content);
        $this->formParser = FormParser::fromParser($this->parser);
        $this->nextObjectId = $this->parser->getNextObjectId();
    }

    /**
     * Load from file.
     */
    public static function load(string $path): self
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("PDF file not found: $path");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Could not read PDF file: $path");
        }

        return new self($content);
    }

    /**
     * Load from string content.
     */
    public static function loadContent(string $content): self
    {
        return new self($content);
    }

    /**
     * Check if form has fields.
     */
    public function hasForm(): bool
    {
        return $this->formParser->hasForm();
    }

    /**
     * Get a field by name.
     */
    public function getField(string $name): ?FormField
    {
        return $this->formParser->getField($name);
    }

    /**
     * Get all field names.
     *
     * @return array<int, string>
     */
    public function getFieldNames(): array
    {
        return $this->formParser->getFieldNames();
    }

    /**
     * Get current field values.
     *
     * @return array<string, mixed>
     */
    public function getFieldValues(): array
    {
        return $this->formParser->getFieldValues();
    }

    /**
     * Set a field value.
     */
    public function setFieldValue(string $name, mixed $value): self
    {
        $this->values[$name] = $value;
        return $this;
    }

    /**
     * Set multiple field values at once.
     *
     * @param array<string, mixed> $values
     */
    public function setFieldValues(array $values): self
    {
        foreach ($values as $name => $value) {
            $this->values[$name] = $value;
        }
        return $this;
    }

    /**
     * Reset a field to its default value.
     */
    public function resetField(string $name): self
    {
        $field = $this->formParser->getField($name);
        if ($field !== null) {
            $this->values[$name] = $field->getDefaultValue();
        }
        return $this;
    }

    /**
     * Reset all fields to default values.
     */
    public function resetAllFields(): self
    {
        foreach ($this->formParser->getFields() as $name => $field) {
            $this->values[$name] = $field->getDefaultValue();
        }
        return $this;
    }

    /**
     * Fill the form and return PDF content.
     */
    public function fill(): string
    {
        if (!$this->hasForm()) {
            throw FormException::noAcroForm();
        }

        if (empty($this->values)) {
            return $this->content;
        }

        return $this->buildIncrementalUpdate();
    }

    /**
     * Fill the form and save to file.
     */
    public function fillToFile(string $path): bool
    {
        $content = $this->fill();
        return file_put_contents($path, $content) !== false;
    }

    /**
     * Build incremental update with modified field values.
     */
    private function buildIncrementalUpdate(): string
    {
        $output = $this->content;

        // Ensure content ends with newline
        if (!str_ends_with($output, "\n")) {
            $output .= "\n";
        }

        // Get original xref position for /Prev
        $prevXref = $this->findXrefPosition($this->content);

        // Find and update field objects
        $modifiedObjects = $this->buildModifiedFieldObjects();

        if (empty($modifiedObjects)) {
            return $this->content;
        }

        // Append modified objects
        foreach ($modifiedObjects as $objNum => $objContent) {
            $this->objectOffsets[$objNum] = strlen($output);
            $output .= $objContent;
        }

        // Build xref table
        $xrefOffset = strlen($output);
        $output .= $this->buildIncrementalXref($prevXref);

        // Write startxref
        $output .= "startxref\n";
        $output .= $xrefOffset . "\n";
        $output .= "%%EOF\n";

        return $output;
    }

    /**
     * Build modified field objects.
     *
     * @return array<int, string> Object number => object content
     */
    private function buildModifiedFieldObjects(): array
    {
        $objects = [];
        $catalog = $this->parser->getCatalog();

        if ($catalog === null || !$catalog->has('AcroForm')) {
            return $objects;
        }

        $acroFormRef = $catalog->get('AcroForm');
        $acroFormDict = $this->resolveValue($acroFormRef);

        if (!$acroFormDict instanceof PdfDictionary || !$acroFormDict->has('Fields')) {
            return $objects;
        }

        $fieldsArray = $this->resolveValue($acroFormDict->get('Fields'));
        if (!$fieldsArray instanceof PdfArray) {
            return $objects;
        }

        // Process each field
        foreach ($fieldsArray->getItems() as $fieldRef) {
            if (!$fieldRef instanceof PdfReference) {
                continue;
            }

            $fieldDict = $this->resolveValue($fieldRef);
            if (!$fieldDict instanceof PdfDictionary) {
                continue;
            }

            $modified = $this->processFieldForUpdate($fieldDict, $fieldRef, null);
            if ($modified !== null) {
                $objects[$fieldRef->getObjectNumber()] = $modified;
            }

            // Process Kids
            if ($fieldDict->has('Kids')) {
                $this->processKidsForUpdate($fieldDict, $objects);
            }
        }

        return $objects;
    }

    /**
     * Process Kids array for updates.
     *
     * @param array<int, string> $objects
     */
    private function processKidsForUpdate(PdfDictionary $parentDict, array &$objects): void
    {
        $kids = $this->resolveValue($parentDict->get('Kids'));
        if (!$kids instanceof PdfArray) {
            return;
        }

        // Get parent name
        $parentName = null;
        if ($parentDict->has('T')) {
            $t = $this->resolveValue($parentDict->get('T'));
            if ($t instanceof PdfString) {
                $parentName = $t->getValue();
            }
        }

        foreach ($kids->getItems() as $kidRef) {
            if (!$kidRef instanceof PdfReference) {
                continue;
            }

            $kidDict = $this->resolveValue($kidRef);
            if (!$kidDict instanceof PdfDictionary) {
                continue;
            }

            $modified = $this->processFieldForUpdate($kidDict, $kidRef, $parentName);
            if ($modified !== null) {
                $objects[$kidRef->getObjectNumber()] = $modified;
            }

            // Recurse
            if ($kidDict->has('Kids')) {
                $this->processKidsForUpdate($kidDict, $objects);
            }
        }
    }

    /**
     * Process a single field for update.
     */
    private function processFieldForUpdate(
        PdfDictionary $fieldDict,
        PdfReference $fieldRef,
        ?string $parentName
    ): ?string {
        // Get field name
        $name = $this->getFieldName($fieldDict, $parentName);

        // Check if we have a value for this field
        if (!isset($this->values[$name])) {
            return null;
        }

        $value = $this->values[$name];

        // Get field type
        $fieldType = $this->getInheritedFieldType($fieldDict);

        // Build updated object
        return $this->buildUpdatedFieldObject(
            $fieldRef->getObjectNumber(),
            $fieldDict,
            $fieldType,
            $value
        );
    }

    /**
     * Build updated field object.
     */
    private function buildUpdatedFieldObject(
        int $objNum,
        PdfDictionary $original,
        ?string $fieldType,
        mixed $value
    ): string {
        $obj = $objNum . " 0 obj\n";
        $obj .= "<<\n";

        // Copy all original entries except V and AS
        foreach ($original->getKeys() as $key) {
            if ($key === 'V' || $key === 'AS') {
                continue;
            }

            $val = $original->get($key);
            $obj .= '/' . $key . ' ' . $this->serializeValue($val) . "\n";
        }

        // Add updated value based on field type
        switch ($fieldType) {
            case 'Tx':
                // Text field
                $obj .= '/V ' . $this->encodePdfString((string) $value) . "\n";
                break;

            case 'Btn':
                // Check/radio button
                $ff = $this->getFieldFlags($original);
                if ($ff & RadioButtonGroup::FLAG_RADIO) {
                    // Radio button
                    $stateName = is_string($value) ? $value : 'Off';
                    $obj .= '/V /' . $this->sanitizeName($stateName) . "\n";
                    $obj .= '/AS /' . $this->sanitizeName($stateName) . "\n";
                } else {
                    // Checkbox
                    $checked = $value === true || $value === 'Yes' || $value === 1;
                    $stateName = $checked ? $this->getCheckboxOnState($original) : 'Off';
                    $obj .= '/V /' . $stateName . "\n";
                    $obj .= '/AS /' . $stateName . "\n";
                }
                break;

            case 'Ch':
                // Choice field
                if (is_array($value)) {
                    // Multiple selection
                    $obj .= '/V [';
                    foreach ($value as $v) {
                        $obj .= $this->encodePdfString((string) $v) . ' ';
                    }
                    $obj .= "]\n";
                } else {
                    $obj .= '/V ' . $this->encodePdfString((string) $value) . "\n";
                }
                break;

            default:
                // Unknown type, use string
                if ($value !== null) {
                    $obj .= '/V ' . $this->encodePdfString((string) $value) . "\n";
                }
        }

        $obj .= ">>\n";
        $obj .= "endobj\n";

        return $obj;
    }

    /**
     * Get checkbox "on" state name from appearance dictionary.
     */
    private function getCheckboxOnState(PdfDictionary $dict): string
    {
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
        return 'Yes';
    }

    /**
     * Serialize a PDF value.
     */
    private function serializeValue(mixed $value): string
    {
        if ($value instanceof PdfReference) {
            return $value->getObjectNumber() . ' ' . $value->getGenerationNumber() . ' R';
        }

        if ($value instanceof PdfName) {
            return '/' . $value->getValue();
        }

        if ($value instanceof PdfString) {
            return $this->encodePdfString($value->getValue());
        }

        if ($value instanceof PdfNumber) {
            $num = $value->getValue();
            if (is_int($num) || floor($num) === $num) {
                return (string) (int) $num;
            }
            return sprintf('%.6f', $num);
        }

        if ($value instanceof PdfBoolean) {
            return $value->getValue() ? 'true' : 'false';
        }

        if ($value instanceof PdfArray) {
            $parts = [];
            foreach ($value->getItems() as $item) {
                $parts[] = $this->serializeValue($item);
            }
            return '[' . implode(' ', $parts) . ']';
        }

        if ($value instanceof PdfDictionary) {
            $parts = [];
            foreach ($value->getKeys() as $key) {
                $parts[] = '/' . $key . ' ' . $this->serializeValue($value->get($key));
            }
            return '<<' . implode(' ', $parts) . '>>';
        }

        if ($value instanceof \PdfLib\Parser\Object\PdfNull) {
            return 'null';
        }

        return (string) $value;
    }

    /**
     * Build incremental xref table.
     */
    private function buildIncrementalXref(int $prevXref): string
    {
        $xref = "xref\n";

        // Add free object entry
        $xref .= "0 1\n";
        $xref .= "0000000000 65535 f \n";

        // Add entries for each modified object
        $objectIds = array_keys($this->objectOffsets);
        sort($objectIds);

        foreach ($objectIds as $id) {
            $offset = $this->objectOffsets[$id];
            $xref .= sprintf("%d 1\n", $id);
            $xref .= sprintf("%010d 00000 n \n", $offset);
        }

        // Get catalog reference
        $rootRef = $this->parser->getRootReference();
        $catalogObjNum = $rootRef ? $rootRef['id'] : 1;

        // Trailer
        $xref .= "trailer\n";
        $xref .= "<<\n";
        $xref .= "/Size " . $this->nextObjectId . "\n";
        $xref .= "/Root " . $catalogObjNum . " 0 R\n";

        // Get info reference from original trailer
        $trailer = $this->parser->getTrailer();
        if ($trailer !== null && $trailer->has('Info')) {
            $infoRef = $trailer->get('Info');
            if ($infoRef instanceof PdfReference) {
                $xref .= "/Info " . $infoRef->getObjectNumber() . " 0 R\n";
            }
        }

        $xref .= "/Prev " . $prevXref . "\n";
        $xref .= ">>\n";

        return $xref;
    }

    /**
     * Find xref position in PDF content.
     */
    private function findXrefPosition(string $content): int
    {
        $pos = strrpos($content, 'startxref');
        if ($pos === false) {
            return 0;
        }

        if (preg_match('/startxref\s+(\d+)/', substr($content, $pos), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Get field name with parent prefix.
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

        if ($dict->has('Parent')) {
            $parent = $this->resolveValue($dict->get('Parent'));
            if ($parent instanceof PdfDictionary) {
                return $this->getInheritedFieldType($parent);
            }
        }

        return null;
    }

    /**
     * Get field flags.
     */
    private function getFieldFlags(PdfDictionary $dict): int
    {
        if ($dict->has('Ff')) {
            $ff = $this->resolveValue($dict->get('Ff'));
            if ($ff instanceof PdfNumber) {
                return (int) $ff->getValue();
            }
        }

        if ($dict->has('Parent')) {
            $parent = $this->resolveValue($dict->get('Parent'));
            if ($parent instanceof PdfDictionary) {
                return $this->getFieldFlags($parent);
            }
        }

        return 0;
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

    /**
     * Encode string for PDF.
     */
    private function encodePdfString(string $str): string
    {
        // Check if we need Unicode encoding
        $needsUnicode = preg_match('/[^\x20-\x7E]/', $str) === 1;

        if ($needsUnicode) {
            // UTF-16BE with BOM
            $utf16 = "\xFE\xFF" . mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
            return '<' . bin2hex($utf16) . '>';
        }

        // Escape special characters
        $escaped = str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $str
        );

        return '(' . $escaped . ')';
    }

    /**
     * Sanitize name for PDF.
     */
    private function sanitizeName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $name) ?: 'name';
    }
}
