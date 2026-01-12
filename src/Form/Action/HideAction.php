<?php

declare(strict_types=1);

namespace PdfLib\Form\Action;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfBoolean;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfString;

/**
 * Hide action for showing or hiding annotations and form fields.
 *
 * Controls the visibility of annotations (including form field widgets)
 * based on user interaction.
 *
 * PDF Reference: Section 8.6.4.6 "Hide Actions"
 *
 * @example Hide a field:
 * ```php
 * $action = HideAction::create(['optionalField'])->hide();
 * ```
 *
 * @example Show a field:
 * ```php
 * $action = HideAction::create(['conditionalField'])->show();
 * ```
 *
 * @example Toggle visibility based on condition:
 * ```php
 * $hideAction = HideAction::create(['section2'])->hide();
 * $showAction = HideAction::create(['section2'])->show();
 * ```
 */
final class HideAction extends Action
{
    /** @var array<int, string> Annotation names (field names for form fields) */
    private array $targets = [];

    /** @var bool Whether to hide (true) or show (false) */
    private bool $hidden = true;

    /**
     * @param array<int, string> $targets Annotation/field names to show/hide
     */
    public function __construct(array $targets = [])
    {
        $this->targets = $targets;
    }

    /**
     * Create a hide action.
     *
     * @param array<int, string> $targets Annotation/field names to show/hide
     */
    public static function create(array $targets = []): self
    {
        return new self($targets);
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return self::TYPE_HIDE;
    }

    /**
     * Set targets to be hidden.
     */
    public function hide(): self
    {
        $this->hidden = true;
        return $this;
    }

    /**
     * Set targets to be shown.
     */
    public function show(): self
    {
        $this->hidden = false;
        return $this;
    }

    /**
     * Set whether to hide or show.
     *
     * @param bool $hidden True to hide, false to show
     */
    public function setHidden(bool $hidden): self
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * Check if action will hide targets.
     */
    public function isHiding(): bool
    {
        return $this->hidden;
    }

    /**
     * Get the target names.
     *
     * @return array<int, string>
     */
    public function getTargets(): array
    {
        return $this->targets;
    }

    /**
     * Set the target names.
     *
     * @param array<int, string> $targets Annotation/field names
     */
    public function setTargets(array $targets): self
    {
        $this->targets = $targets;
        return $this;
    }

    /**
     * Add a target.
     */
    public function addTarget(string $name): self
    {
        if (!in_array($name, $this->targets, true)) {
            $this->targets[] = $name;
        }
        return $this;
    }

    /**
     * Remove a target.
     */
    public function removeTarget(string $name): self
    {
        $this->targets = array_values(array_filter(
            $this->targets,
            fn($t) => $t !== $name
        ));
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildActionEntries(PdfDictionary $dict): void
    {
        // T - Target annotations
        if (count($this->targets) === 1) {
            // Single target can be a string
            $dict->set('T', PdfString::literal($this->targets[0]));
        } elseif (count($this->targets) > 1) {
            // Multiple targets as an array
            $targetsArray = new PdfArray();
            foreach ($this->targets as $target) {
                $targetsArray->push(PdfString::literal($target));
            }
            $dict->set('T', $targetsArray);
        }

        // H - Hide flag (default is true, so only include if false)
        if (!$this->hidden) {
            $dict->set('H', PdfBoolean::create(false));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'targets' => $this->targets,
            'hidden' => $this->hidden,
        ]);
    }

    // =========================================================================
    // CONVENIENCE FACTORY METHODS
    // =========================================================================

    /**
     * Create an action to hide a single field.
     */
    public static function hideField(string $fieldName): self
    {
        return self::create([$fieldName])->hide();
    }

    /**
     * Create an action to show a single field.
     */
    public static function showField(string $fieldName): self
    {
        return self::create([$fieldName])->show();
    }

    /**
     * Create an action to hide multiple fields.
     *
     * @param array<int, string> $fieldNames
     */
    public static function hideFields(array $fieldNames): self
    {
        return self::create($fieldNames)->hide();
    }

    /**
     * Create an action to show multiple fields.
     *
     * @param array<int, string> $fieldNames
     */
    public static function showFields(array $fieldNames): self
    {
        return self::create($fieldNames)->show();
    }

    /**
     * Create a toggle action (use JavaScript for actual toggle logic).
     *
     * Note: PDF's Hide action doesn't support true toggle.
     * This creates a show action; use with conditional JavaScript.
     *
     * @param array<int, string> $fieldNames
     */
    public static function toggleFields(array $fieldNames): self
    {
        return self::create($fieldNames)->show();
    }
}
