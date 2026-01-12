<?php

declare(strict_types=1);

namespace PdfLib\Form\Action;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfString;

/**
 * Reset form action for clearing or resetting form field values.
 *
 * Resets form fields to their default values. Can reset all fields
 * or a specific subset.
 *
 * PDF Reference: Section 8.6.4.5 "Reset-Form Actions"
 *
 * @example Reset all fields:
 * ```php
 * $action = ResetFormAction::create();
 * ```
 *
 * @example Reset specific fields:
 * ```php
 * $action = ResetFormAction::create()
 *     ->includeFields(['name', 'email', 'phone']);
 * ```
 *
 * @example Reset all except certain fields:
 * ```php
 * $action = ResetFormAction::create()
 *     ->excludeFields(['signature', 'date']);
 * ```
 */
final class ResetFormAction extends Action
{
    // Reset form flags (PDF Reference Table 8.77)
    public const FLAG_INCLUDE_EXCLUDE = 1; // If set, fields in Fields array are excluded

    private int $flags = 0;

    /** @var array<int, string> Field names to include or exclude */
    private array $fields = [];

    /**
     * Create a reset form action.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return self::TYPE_RESET_FORM;
    }

    /**
     * Specify which fields to reset.
     *
     * @param array<int, string> $fieldNames Field names to reset
     */
    public function includeFields(array $fieldNames): self
    {
        $this->flags &= ~self::FLAG_INCLUDE_EXCLUDE; // Clear exclude flag
        $this->fields = $fieldNames;
        return $this;
    }

    /**
     * Specify which fields to exclude from reset.
     *
     * All other fields will be reset.
     *
     * @param array<int, string> $fieldNames Field names to exclude from reset
     */
    public function excludeFields(array $fieldNames): self
    {
        $this->flags |= self::FLAG_INCLUDE_EXCLUDE; // Set exclude flag
        $this->fields = $fieldNames;
        return $this;
    }

    /**
     * Reset all fields (default behavior).
     */
    public function resetAllFields(): self
    {
        $this->fields = [];
        $this->flags &= ~self::FLAG_INCLUDE_EXCLUDE;
        return $this;
    }

    /**
     * Get the fields array.
     *
     * @return array<int, string>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Check if fields are being excluded.
     */
    public function isExcluding(): bool
    {
        return ($this->flags & self::FLAG_INCLUDE_EXCLUDE) !== 0;
    }

    /**
     * Get the flags value.
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildActionEntries(PdfDictionary $dict): void
    {
        // Flags
        if ($this->flags !== 0) {
            $dict->set('Flags', PdfNumber::int($this->flags));
        }

        // Fields
        if (!empty($this->fields)) {
            $fieldsArray = new PdfArray();
            foreach ($this->fields as $fieldName) {
                $fieldsArray->push(PdfString::literal($fieldName));
            }
            $dict->set('Fields', $fieldsArray);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'flags' => $this->flags,
            'fields' => $this->fields,
        ]);
    }

    // =========================================================================
    // CONVENIENCE FACTORY METHODS
    // =========================================================================

    /**
     * Create a reset action for all fields.
     */
    public static function all(): self
    {
        return self::create();
    }

    /**
     * Create a reset action for specific fields only.
     *
     * @param array<int, string> $fieldNames Field names to reset
     */
    public static function only(array $fieldNames): self
    {
        return self::create()->includeFields($fieldNames);
    }

    /**
     * Create a reset action that excludes specific fields.
     *
     * @param array<int, string> $fieldNames Field names to exclude from reset
     */
    public static function except(array $fieldNames): self
    {
        return self::create()->excludeFields($fieldNames);
    }
}
