<?php

declare(strict_types=1);

namespace PdfLib\Form;

use PdfLib\Form\Action\Action;
use PdfLib\Form\Action\FieldActions;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfString;

/**
 * Abstract base class for PDF form fields.
 *
 * Form fields are interactive elements in PDF documents that allow
 * users to enter data or make selections. This base class provides
 * common functionality for all field types.
 */
abstract class FormField
{
    // Field Flags (Ff) - Common to all fields (Table 227 in PDF spec)
    public const FLAG_READ_ONLY = 1;           // Bit 1: Read-only field
    public const FLAG_REQUIRED = 2;            // Bit 2: Required field
    public const FLAG_NO_EXPORT = 4;           // Bit 3: Do not export

    // Annotation Flags (F) - Widget annotation flags
    public const ANNOT_INVISIBLE = 1;          // Bit 1
    public const ANNOT_HIDDEN = 2;             // Bit 2
    public const ANNOT_PRINT = 4;              // Bit 3
    public const ANNOT_NO_ZOOM = 8;            // Bit 4
    public const ANNOT_NO_ROTATE = 16;         // Bit 5
    public const ANNOT_NO_VIEW = 32;           // Bit 6
    public const ANNOT_LOCKED = 128;           // Bit 8

    protected string $name;
    protected int $page = 1;
    protected float $x = 0;
    protected float $y = 0;
    protected float $width = 100;
    protected float $height = 20;
    protected int $fieldFlags = 0;
    protected int $annotationFlags;
    protected ?string $tooltip = null;
    protected ?string $mappingName = null;

    // Appearance properties
    protected string $borderStyle = 'solid';
    protected float $borderWidth = 1;
    /** @var array{0: float, 1: float, 2: float}|null */
    protected ?array $borderColor = null;
    /** @var array{0: float, 1: float, 2: float}|null */
    protected ?array $backgroundColor = null;

    // Additional actions
    protected ?PdfDictionary $additionalActions = null;
    protected ?FieldActions $fieldActions = null;

    public function __construct(string $name)
    {
        $this->name = $this->sanitizeFieldName($name);
        $this->annotationFlags = self::ANNOT_PRINT;
    }

    /**
     * Get the field type for PDF /FT entry.
     *
     * @return string Tx (text), Btn (button/checkbox/radio), Ch (choice), Sig (signature)
     */
    abstract public function getFieldType(): string;

    /**
     * Get field-specific flags to add to the common flags.
     */
    abstract protected function getTypeSpecificFlags(): int;

    /**
     * Build field-specific dictionary entries.
     */
    abstract protected function buildFieldSpecificEntries(PdfDictionary $dict): void;

    /**
     * Get the current value for the /V entry.
     */
    abstract public function getValue(): mixed;

    /**
     * Set the field value.
     */
    abstract public function setValue(mixed $value): static;

    /**
     * Get the default value for the /DV entry.
     */
    abstract public function getDefaultValue(): mixed;

    /**
     * Generate appearance stream content for normal state.
     */
    abstract public function generateAppearance(): string;

    /**
     * Get field name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set field name.
     */
    public function setName(string $name): static
    {
        $this->name = $this->sanitizeFieldName($name);
        return $this;
    }

    /**
     * Get page number (1-based).
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Set page number (1-based).
     */
    public function setPage(int $page): static
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page number must be >= 1');
        }
        $this->page = $page;
        return $this;
    }

    /**
     * Get X position.
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * Get Y position.
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * Set position.
     */
    public function setPosition(float $x, float $y): static
    {
        $this->x = $x;
        $this->y = $y;
        return $this;
    }

    /**
     * Get width.
     */
    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * Get height.
     */
    public function getHeight(): float
    {
        return $this->height;
    }

    /**
     * Set size.
     */
    public function setSize(float $width, float $height): static
    {
        $this->width = max(0, $width);
        $this->height = max(0, $height);
        return $this;
    }

    /**
     * Get rectangle [x1, y1, x2, y2].
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function getRect(): array
    {
        return [
            $this->x,
            $this->y,
            $this->x + $this->width,
            $this->y + $this->height,
        ];
    }

    /**
     * Set rectangle [x1, y1, x2, y2].
     *
     * @param array{0: float, 1: float, 2: float, 3: float} $rect
     */
    public function setRect(array $rect): static
    {
        $this->x = $rect[0];
        $this->y = $rect[1];
        $this->width = $rect[2] - $rect[0];
        $this->height = $rect[3] - $rect[1];
        return $this;
    }

    /**
     * Check if field is read-only.
     */
    public function isReadOnly(): bool
    {
        return ($this->fieldFlags & self::FLAG_READ_ONLY) !== 0;
    }

    /**
     * Set read-only flag.
     */
    public function setReadOnly(bool $readOnly = true): static
    {
        if ($readOnly) {
            $this->fieldFlags |= self::FLAG_READ_ONLY;
        } else {
            $this->fieldFlags &= ~self::FLAG_READ_ONLY;
        }
        return $this;
    }

    /**
     * Check if field is required.
     */
    public function isRequired(): bool
    {
        return ($this->fieldFlags & self::FLAG_REQUIRED) !== 0;
    }

    /**
     * Set required flag.
     */
    public function setRequired(bool $required = true): static
    {
        if ($required) {
            $this->fieldFlags |= self::FLAG_REQUIRED;
        } else {
            $this->fieldFlags &= ~self::FLAG_REQUIRED;
        }
        return $this;
    }

    /**
     * Check if field should not be exported.
     */
    public function isNoExport(): bool
    {
        return ($this->fieldFlags & self::FLAG_NO_EXPORT) !== 0;
    }

    /**
     * Set no-export flag.
     */
    public function setNoExport(bool $noExport = true): static
    {
        if ($noExport) {
            $this->fieldFlags |= self::FLAG_NO_EXPORT;
        } else {
            $this->fieldFlags &= ~self::FLAG_NO_EXPORT;
        }
        return $this;
    }

    /**
     * Get tooltip (alternative name /TU).
     */
    public function getTooltip(): ?string
    {
        return $this->tooltip;
    }

    /**
     * Set tooltip (alternative name /TU).
     */
    public function setTooltip(string $tooltip): static
    {
        $this->tooltip = $tooltip;
        return $this;
    }

    /**
     * Get mapping name for export (/TM).
     */
    public function getMappingName(): ?string
    {
        return $this->mappingName;
    }

    /**
     * Set mapping name for export (/TM).
     */
    public function setMappingName(string $mappingName): static
    {
        $this->mappingName = $mappingName;
        return $this;
    }

    /**
     * Set border style.
     *
     * @param string $style One of: solid, dashed, beveled, inset, underline
     */
    public function setBorderStyle(string $style): static
    {
        $this->borderStyle = $style;
        return $this;
    }

    /**
     * Set border width.
     */
    public function setBorderWidth(float $width): static
    {
        $this->borderWidth = max(0, $width);
        return $this;
    }

    /**
     * Set border color (RGB values 0-1).
     */
    public function setBorderColor(float $r, float $g, float $b): static
    {
        $this->borderColor = [$r, $g, $b];
        return $this;
    }

    /**
     * Set background color (RGB values 0-1).
     */
    public function setBackgroundColor(float $r, float $g, float $b): static
    {
        $this->backgroundColor = [$r, $g, $b];
        return $this;
    }

    /**
     * Get combined field flags (common + type-specific).
     */
    public function getFieldFlags(): int
    {
        return $this->fieldFlags | $this->getTypeSpecificFlags();
    }

    /**
     * Get annotation flags.
     */
    public function getAnnotationFlags(): int
    {
        return $this->annotationFlags;
    }

    /**
     * Set annotation flags.
     */
    public function setAnnotationFlags(int $flags): static
    {
        $this->annotationFlags = $flags;
        return $this;
    }

    /**
     * Set field as hidden.
     */
    public function setHidden(bool $hidden = true): static
    {
        if ($hidden) {
            $this->annotationFlags |= self::ANNOT_HIDDEN | self::ANNOT_NO_VIEW;
        } else {
            $this->annotationFlags &= ~(self::ANNOT_HIDDEN | self::ANNOT_NO_VIEW);
        }
        return $this;
    }

    /**
     * Build the widget annotation dictionary (combined field + widget).
     */
    public function toWidgetDictionary(?PdfReference $pageRef = null): PdfDictionary
    {
        $dict = new PdfDictionary();

        // Annotation entries
        $dict->set('Type', PdfName::create('Annot'));
        $dict->set('Subtype', PdfName::create('Widget'));
        $dict->set('Rect', $this->buildRectArray());
        $dict->set('F', PdfNumber::int($this->annotationFlags));

        // Field entries
        $dict->set('FT', PdfName::create($this->getFieldType()));
        $dict->set('T', PdfString::literal($this->name));

        $ff = $this->getFieldFlags();
        if ($ff !== 0) {
            $dict->set('Ff', PdfNumber::int($ff));
        }

        // Value and default value
        $value = $this->getValueForPdf();
        if ($value !== null) {
            $dict->set('V', $value);
        }

        $defaultValue = $this->getDefaultValueForPdf();
        if ($defaultValue !== null) {
            $dict->set('DV', $defaultValue);
        }

        // Optional entries
        if ($this->tooltip !== null) {
            $dict->set('TU', PdfString::literal($this->tooltip));
        }

        if ($this->mappingName !== null) {
            $dict->set('TM', PdfString::literal($this->mappingName));
        }

        if ($pageRef !== null) {
            $dict->set('P', $pageRef);
        }

        // Border style
        $dict->set('BS', $this->buildBorderStyleDict());

        // Appearance characteristics
        $mk = $this->buildAppearanceCharacteristicsDict();
        if ($mk->count() > 0) {
            $dict->set('MK', $mk);
        }

        // Additional actions
        if ($this->fieldActions !== null && !$this->fieldActions->isEmpty()) {
            $dict->set('AA', $this->fieldActions->toDictionary());
        } elseif ($this->additionalActions !== null) {
            $dict->set('AA', $this->additionalActions);
        }

        // Let subclass add field-specific entries
        $this->buildFieldSpecificEntries($dict);

        return $dict;
    }

    /**
     * Get value as PDF object.
     */
    protected function getValueForPdf(): ?PdfObject
    {
        $value = $this->getValue();
        return $value !== null ? $this->toPdfValue($value) : null;
    }

    /**
     * Get default value as PDF object.
     */
    protected function getDefaultValueForPdf(): ?PdfObject
    {
        $value = $this->getDefaultValue();
        return $value !== null ? $this->toPdfValue($value) : null;
    }

    /**
     * Build the Rect array.
     */
    protected function buildRectArray(): PdfArray
    {
        $rect = $this->getRect();
        return PdfArray::fromValues([
            PdfNumber::real($rect[0]),
            PdfNumber::real($rect[1]),
            PdfNumber::real($rect[2]),
            PdfNumber::real($rect[3]),
        ]);
    }

    /**
     * Build border style dictionary (/BS).
     */
    protected function buildBorderStyleDict(): PdfDictionary
    {
        $bs = new PdfDictionary();
        $bs->set('W', PdfNumber::real($this->borderWidth));

        $styleMap = [
            'solid' => 'S',
            'dashed' => 'D',
            'beveled' => 'B',
            'inset' => 'I',
            'underline' => 'U',
        ];
        $style = $styleMap[$this->borderStyle] ?? 'S';
        $bs->set('S', PdfName::create($style));

        return $bs;
    }

    /**
     * Build appearance characteristics dictionary (/MK).
     */
    protected function buildAppearanceCharacteristicsDict(): PdfDictionary
    {
        $mk = new PdfDictionary();

        if ($this->borderColor !== null) {
            $mk->set('BC', PdfArray::fromValues([
                PdfNumber::real($this->borderColor[0]),
                PdfNumber::real($this->borderColor[1]),
                PdfNumber::real($this->borderColor[2]),
            ]));
        }

        if ($this->backgroundColor !== null) {
            $mk->set('BG', PdfArray::fromValues([
                PdfNumber::real($this->backgroundColor[0]),
                PdfNumber::real($this->backgroundColor[1]),
                PdfNumber::real($this->backgroundColor[2]),
            ]));
        }

        return $mk;
    }

    /**
     * Convert PHP value to appropriate PDF object.
     */
    protected function toPdfValue(mixed $value): PdfObject
    {
        if ($value instanceof PdfObject) {
            return $value;
        }

        return match (true) {
            is_string($value) => PdfString::literal($value),
            is_int($value) => PdfNumber::int($value),
            is_float($value) => PdfNumber::real($value),
            is_bool($value) => $value ? PdfName::create('Yes') : PdfName::create('Off'),
            is_array($value) => PdfArray::fromValues($value),
            default => PdfString::literal((string) $value),
        };
    }

    /**
     * Sanitize field name (remove special characters).
     */
    protected function sanitizeFieldName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $name);
        return $name !== '' && $name !== null ? $name : 'Field';
    }

    /**
     * Escape text for PDF string.
     */
    protected function escapeText(string $text): string
    {
        return strtr($text, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
        ]);
    }

    /**
     * Set additional actions dictionary.
     */
    public function setAdditionalActions(PdfDictionary $actions): static
    {
        $this->additionalActions = $actions;
        return $this;
    }

    /**
     * Get additional actions dictionary.
     */
    public function getAdditionalActions(): ?PdfDictionary
    {
        return $this->additionalActions;
    }

    /**
     * Set field actions using a FieldActions object.
     *
     * @example
     * ```php
     * $field->setActions(
     *     FieldActions::create()
     *         ->onKeystroke(JavaScriptAction::validateNumber(2))
     *         ->onFormat(JavaScriptAction::formatNumber(2, '$'))
     * );
     * ```
     */
    public function setActions(FieldActions $actions): static
    {
        $this->fieldActions = $actions;
        return $this;
    }

    /**
     * Get field actions object.
     */
    public function getActions(): ?FieldActions
    {
        return $this->fieldActions;
    }

    /**
     * Set keystroke action (triggered on each keystroke).
     *
     * Shortcut for setActions()->onKeystroke().
     */
    public function onKeystroke(Action $action): static
    {
        if ($this->fieldActions === null) {
            $this->fieldActions = FieldActions::create();
        }
        $this->fieldActions->onKeystroke($action);
        return $this;
    }

    /**
     * Set format action (triggered before displaying value).
     *
     * Shortcut for setActions()->onFormat().
     */
    public function onFormat(Action $action): static
    {
        if ($this->fieldActions === null) {
            $this->fieldActions = FieldActions::create();
        }
        $this->fieldActions->onFormat($action);
        return $this;
    }

    /**
     * Set validate action (triggered when field loses focus).
     *
     * Shortcut for setActions()->onValidate().
     */
    public function onValidate(Action $action): static
    {
        if ($this->fieldActions === null) {
            $this->fieldActions = FieldActions::create();
        }
        $this->fieldActions->onValidate($action);
        return $this;
    }

    /**
     * Set calculate action (triggered when related fields change).
     *
     * Shortcut for setActions()->onCalculate().
     */
    public function onCalculate(Action $action): static
    {
        if ($this->fieldActions === null) {
            $this->fieldActions = FieldActions::create();
        }
        $this->fieldActions->onCalculate($action);
        return $this;
    }

    /**
     * Set focus action (triggered when field receives focus).
     *
     * Shortcut for setActions()->onFocus().
     */
    public function onFocus(Action $action): static
    {
        if ($this->fieldActions === null) {
            $this->fieldActions = FieldActions::create();
        }
        $this->fieldActions->onFocus($action);
        return $this;
    }

    /**
     * Set blur action (triggered when field loses focus).
     *
     * Shortcut for setActions()->onBlur().
     */
    public function onBlur(Action $action): static
    {
        if ($this->fieldActions === null) {
            $this->fieldActions = FieldActions::create();
        }
        $this->fieldActions->onBlur($action);
        return $this;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => static::class,
            'name' => $this->name,
            'page' => $this->page,
            'rect' => $this->getRect(),
            'fieldFlags' => $this->fieldFlags,
            'annotationFlags' => $this->annotationFlags,
            'tooltip' => $this->tooltip,
            'mappingName' => $this->mappingName,
            'borderStyle' => $this->borderStyle,
            'borderWidth' => $this->borderWidth,
            'borderColor' => $this->borderColor,
            'backgroundColor' => $this->backgroundColor,
        ];
    }

    /**
     * Restore base properties from array.
     *
     * @param array<string, mixed> $data
     */
    protected function fromArrayBase(array $data): void
    {
        if (isset($data['page'])) {
            $this->page = (int) $data['page'];
        }
        if (isset($data['rect'])) {
            $this->setRect($data['rect']);
        }
        if (isset($data['fieldFlags'])) {
            $this->fieldFlags = (int) $data['fieldFlags'];
        }
        if (isset($data['annotationFlags'])) {
            $this->annotationFlags = (int) $data['annotationFlags'];
        }
        if (isset($data['tooltip'])) {
            $this->tooltip = $data['tooltip'];
        }
        if (isset($data['mappingName'])) {
            $this->mappingName = $data['mappingName'];
        }
        if (isset($data['borderStyle'])) {
            $this->borderStyle = $data['borderStyle'];
        }
        if (isset($data['borderWidth'])) {
            $this->borderWidth = (float) $data['borderWidth'];
        }
        if (isset($data['borderColor'])) {
            $this->borderColor = $data['borderColor'];
        }
        if (isset($data['backgroundColor'])) {
            $this->backgroundColor = $data['backgroundColor'];
        }
    }
}
