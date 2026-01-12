<?php

declare(strict_types=1);

namespace PdfLib\Form\Action;

use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;

/**
 * Manages additional actions (AA) for form fields.
 *
 * Field actions are triggered by various events during user interaction
 * with form fields. Each event type maps to a specific trigger in the
 * PDF specification.
 *
 * PDF Reference: Section 8.6.2 "Trigger Events"
 *
 * @example
 * ```php
 * $actions = FieldActions::create()
 *     ->onKeystroke(JavaScriptAction::validateNumber(2))
 *     ->onFormat(JavaScriptAction::formatNumber(2, '$'))
 *     ->onValidate(JavaScriptAction::validateRange(0, 10000))
 *     ->onCalculate(JavaScriptAction::calculateSum(['price', 'tax']));
 *
 * $field->setActions($actions);
 * ```
 */
final class FieldActions
{
    // Trigger event types for form fields (PDF Reference Table 8.69)
    public const TRIGGER_KEYSTROKE = 'K';       // Keystroke event
    public const TRIGGER_FORMAT = 'F';          // Format event (before displaying)
    public const TRIGGER_VALIDATE = 'V';        // Validate event (on blur/commit)
    public const TRIGGER_CALCULATE = 'C';       // Calculate event
    public const TRIGGER_FOCUS = 'Fo';          // Focus event (entering field)
    public const TRIGGER_BLUR = 'Bl';           // Blur event (leaving field)
    public const TRIGGER_DOWN = 'D';            // Mouse button down
    public const TRIGGER_UP = 'U';              // Mouse button up
    public const TRIGGER_ENTER = 'E';           // Mouse enter annotation
    public const TRIGGER_EXIT = 'X';            // Mouse exit annotation
    public const TRIGGER_PAGE_OPEN = 'PO';      // Page containing annotation opened
    public const TRIGGER_PAGE_CLOSE = 'PC';     // Page containing annotation closed
    public const TRIGGER_PAGE_VISIBLE = 'PV';   // Page visible
    public const TRIGGER_PAGE_INVISIBLE = 'PI'; // Page invisible

    /** @var array<string, Action> Trigger event to action mapping */
    private array $actions = [];

    /**
     * Create a new FieldActions instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set keystroke action (triggered on each keystroke).
     *
     * Use for input validation/filtering as user types.
     * Access event.change (keystroke), event.selStart, event.selEnd, event.value.
     * Set event.rc = false to reject the keystroke.
     */
    public function onKeystroke(Action $action): self
    {
        $this->actions[self::TRIGGER_KEYSTROKE] = $action;
        return $this;
    }

    /**
     * Get keystroke action.
     */
    public function getKeystrokeAction(): ?Action
    {
        return $this->actions[self::TRIGGER_KEYSTROKE] ?? null;
    }

    /**
     * Set format action (triggered before displaying value).
     *
     * Use for formatting the display value (e.g., currency, dates).
     * Modify event.value to change the displayed value.
     */
    public function onFormat(Action $action): self
    {
        $this->actions[self::TRIGGER_FORMAT] = $action;
        return $this;
    }

    /**
     * Get format action.
     */
    public function getFormatAction(): ?Action
    {
        return $this->actions[self::TRIGGER_FORMAT] ?? null;
    }

    /**
     * Set validate action (triggered when field loses focus or on commit).
     *
     * Use for validating the complete field value.
     * Set event.rc = false to reject the value and keep focus.
     */
    public function onValidate(Action $action): self
    {
        $this->actions[self::TRIGGER_VALIDATE] = $action;
        return $this;
    }

    /**
     * Get validate action.
     */
    public function getValidateAction(): ?Action
    {
        return $this->actions[self::TRIGGER_VALIDATE] ?? null;
    }

    /**
     * Set calculate action (triggered when any field in calculation order changes).
     *
     * Use for computing field value from other fields.
     * Set event.value to the calculated result.
     */
    public function onCalculate(Action $action): self
    {
        $this->actions[self::TRIGGER_CALCULATE] = $action;
        return $this;
    }

    /**
     * Get calculate action.
     */
    public function getCalculateAction(): ?Action
    {
        return $this->actions[self::TRIGGER_CALCULATE] ?? null;
    }

    /**
     * Set focus action (triggered when field receives focus).
     *
     * Use for actions when user enters a field.
     */
    public function onFocus(Action $action): self
    {
        $this->actions[self::TRIGGER_FOCUS] = $action;
        return $this;
    }

    /**
     * Get focus action.
     */
    public function getFocusAction(): ?Action
    {
        return $this->actions[self::TRIGGER_FOCUS] ?? null;
    }

    /**
     * Set blur action (triggered when field loses focus).
     *
     * Use for actions when user leaves a field.
     * Note: This is different from validate - blur always runs.
     */
    public function onBlur(Action $action): self
    {
        $this->actions[self::TRIGGER_BLUR] = $action;
        return $this;
    }

    /**
     * Get blur action.
     */
    public function getBlurAction(): ?Action
    {
        return $this->actions[self::TRIGGER_BLUR] ?? null;
    }

    /**
     * Set mouse down action (triggered when mouse button pressed).
     *
     * Commonly used for button fields.
     */
    public function onMouseDown(Action $action): self
    {
        $this->actions[self::TRIGGER_DOWN] = $action;
        return $this;
    }

    /**
     * Get mouse down action.
     */
    public function getMouseDownAction(): ?Action
    {
        return $this->actions[self::TRIGGER_DOWN] ?? null;
    }

    /**
     * Set mouse up action (triggered when mouse button released).
     *
     * Commonly used for button fields.
     */
    public function onMouseUp(Action $action): self
    {
        $this->actions[self::TRIGGER_UP] = $action;
        return $this;
    }

    /**
     * Get mouse up action.
     */
    public function getMouseUpAction(): ?Action
    {
        return $this->actions[self::TRIGGER_UP] ?? null;
    }

    /**
     * Set mouse enter action (triggered when mouse enters annotation area).
     */
    public function onMouseEnter(Action $action): self
    {
        $this->actions[self::TRIGGER_ENTER] = $action;
        return $this;
    }

    /**
     * Get mouse enter action.
     */
    public function getMouseEnterAction(): ?Action
    {
        return $this->actions[self::TRIGGER_ENTER] ?? null;
    }

    /**
     * Set mouse exit action (triggered when mouse leaves annotation area).
     */
    public function onMouseExit(Action $action): self
    {
        $this->actions[self::TRIGGER_EXIT] = $action;
        return $this;
    }

    /**
     * Get mouse exit action.
     */
    public function getMouseExitAction(): ?Action
    {
        return $this->actions[self::TRIGGER_EXIT] ?? null;
    }

    /**
     * Set page open action (triggered when page containing field is opened).
     */
    public function onPageOpen(Action $action): self
    {
        $this->actions[self::TRIGGER_PAGE_OPEN] = $action;
        return $this;
    }

    /**
     * Get page open action.
     */
    public function getPageOpenAction(): ?Action
    {
        return $this->actions[self::TRIGGER_PAGE_OPEN] ?? null;
    }

    /**
     * Set page close action (triggered when page containing field is closed).
     */
    public function onPageClose(Action $action): self
    {
        $this->actions[self::TRIGGER_PAGE_CLOSE] = $action;
        return $this;
    }

    /**
     * Get page close action.
     */
    public function getPageCloseAction(): ?Action
    {
        return $this->actions[self::TRIGGER_PAGE_CLOSE] ?? null;
    }

    /**
     * Set page visible action (triggered when page becomes visible).
     */
    public function onPageVisible(Action $action): self
    {
        $this->actions[self::TRIGGER_PAGE_VISIBLE] = $action;
        return $this;
    }

    /**
     * Get page visible action.
     */
    public function getPageVisibleAction(): ?Action
    {
        return $this->actions[self::TRIGGER_PAGE_VISIBLE] ?? null;
    }

    /**
     * Set page invisible action (triggered when page becomes invisible).
     */
    public function onPageInvisible(Action $action): self
    {
        $this->actions[self::TRIGGER_PAGE_INVISIBLE] = $action;
        return $this;
    }

    /**
     * Get page invisible action.
     */
    public function getPageInvisibleAction(): ?Action
    {
        return $this->actions[self::TRIGGER_PAGE_INVISIBLE] ?? null;
    }

    /**
     * Set action for a specific trigger type.
     *
     * @param string $trigger One of the TRIGGER_* constants
     */
    public function setAction(string $trigger, Action $action): self
    {
        $this->actions[$trigger] = $action;
        return $this;
    }

    /**
     * Get action for a specific trigger type.
     *
     * @param string $trigger One of the TRIGGER_* constants
     */
    public function getAction(string $trigger): ?Action
    {
        return $this->actions[$trigger] ?? null;
    }

    /**
     * Remove action for a specific trigger type.
     *
     * @param string $trigger One of the TRIGGER_* constants
     */
    public function removeAction(string $trigger): self
    {
        unset($this->actions[$trigger]);
        return $this;
    }

    /**
     * Check if any actions are defined.
     */
    public function isEmpty(): bool
    {
        return empty($this->actions);
    }

    /**
     * Get count of defined actions.
     */
    public function count(): int
    {
        return count($this->actions);
    }

    /**
     * Get all defined trigger types.
     *
     * @return array<int, string>
     */
    public function getTriggers(): array
    {
        return array_keys($this->actions);
    }

    /**
     * Build the Additional Actions (AA) dictionary.
     */
    public function toDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();

        foreach ($this->actions as $trigger => $action) {
            $dict->set($trigger, $action->toDictionary());
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
        $data = [];

        foreach ($this->actions as $trigger => $action) {
            $data[$trigger] = $action->toArray();
        }

        return $data;
    }

    /**
     * Create common text field actions (keystroke + format).
     *
     * @param Action $keystroke Keystroke validation action
     * @param Action $format Format action
     */
    public static function textField(Action $keystroke, Action $format): self
    {
        return self::create()
            ->onKeystroke($keystroke)
            ->onFormat($format);
    }

    /**
     * Create common number field actions (keystroke + format + validate).
     *
     * @param int $decimals Number of decimal places
     * @param float|null $min Minimum value (null for no minimum)
     * @param float|null $max Maximum value (null for no maximum)
     * @param string|null $currency Currency symbol (null for no currency)
     */
    public static function numberField(
        int $decimals = 2,
        ?float $min = null,
        ?float $max = null,
        ?string $currency = null
    ): self {
        $actions = self::create()
            ->onKeystroke(JavaScriptAction::validateNumber($decimals))
            ->onFormat(JavaScriptAction::formatNumber($decimals, $currency));

        if ($min !== null && $max !== null) {
            $actions->onValidate(JavaScriptAction::validateRange($min, $max));
        }

        return $actions;
    }

    /**
     * Create common email field actions.
     */
    public static function emailField(): self
    {
        return self::create()
            ->onValidate(JavaScriptAction::validateEmail());
    }

    /**
     * Create common phone field actions.
     */
    public static function phoneField(): self
    {
        return self::create()
            ->onKeystroke(JavaScriptAction::validatePhone())
            ->onFormat(JavaScriptAction::formatPhone());
    }

    /**
     * Create common date field actions.
     *
     * @param string $format Date format
     */
    public static function dateField(string $format = 'mm/dd/yyyy'): self
    {
        return self::create()
            ->onValidate(JavaScriptAction::validateDate($format))
            ->onFormat(JavaScriptAction::formatDate($format));
    }

    /**
     * Create common percentage field actions.
     *
     * @param int $decimals Number of decimal places
     */
    public static function percentField(int $decimals = 2): self
    {
        return self::create()
            ->onKeystroke(JavaScriptAction::validateNumber($decimals))
            ->onFormat(JavaScriptAction::formatPercent($decimals));
    }

    /**
     * Create common calculation field actions.
     *
     * @param Action $calculate Calculate action
     * @param int $decimals Number of decimal places for display
     * @param string|null $currency Currency symbol (null for no currency)
     */
    public static function calculatedField(
        Action $calculate,
        int $decimals = 2,
        ?string $currency = null
    ): self {
        return self::create()
            ->onCalculate($calculate)
            ->onFormat(JavaScriptAction::formatNumber($decimals, $currency));
    }
}
