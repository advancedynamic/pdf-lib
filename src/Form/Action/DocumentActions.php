<?php

declare(strict_types=1);

namespace PdfLib\Form\Action;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfString;

/**
 * Manages document-level JavaScript and actions.
 *
 * Document actions are triggered by document-level events such as
 * opening, closing, saving, or printing the document.
 *
 * PDF Reference: Section 8.6.1 "Document Actions" and Section 8.6.4.2 "Document-Level JavaScript"
 *
 * @example Document-level scripts:
 * ```php
 * $docActions = DocumentActions::create()
 *     ->addScript('init', 'var globalVar = 0;')
 *     ->addScript('helpers', 'function formatCurrency(x) { return "$" + x.toFixed(2); }');
 * ```
 *
 * @example Document event actions:
 * ```php
 * $docActions = DocumentActions::create()
 *     ->onOpen(JavaScriptAction::alert("Welcome to the form!"))
 *     ->onWillSave(JavaScriptAction::create("validateAllFields()"))
 *     ->onWillPrint(JavaScriptAction::create("preparePrintVersion()"));
 * ```
 */
final class DocumentActions
{
    // Document action triggers (PDF Reference Section 8.6.1)
    public const TRIGGER_WILL_CLOSE = 'WC';     // Before document closes
    public const TRIGGER_WILL_SAVE = 'WS';      // Before document saves
    public const TRIGGER_DID_SAVE = 'DS';       // After document saves
    public const TRIGGER_WILL_PRINT = 'WP';     // Before document prints
    public const TRIGGER_DID_PRINT = 'DP';      // After document prints

    /** Document open action (stored in catalog) */
    private ?Action $openAction = null;

    /** @var array<string, Action> Additional document actions */
    private array $additionalActions = [];

    /** @var array<string, string> Named document-level scripts */
    private array $scripts = [];

    /**
     * Create a new DocumentActions instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set document open action.
     *
     * This action is triggered when the document is opened.
     * Note: Stored in the catalog's OpenAction, not in AA dictionary.
     */
    public function onOpen(Action $action): self
    {
        $this->openAction = $action;
        return $this;
    }

    /**
     * Get document open action.
     */
    public function getOpenAction(): ?Action
    {
        return $this->openAction;
    }

    /**
     * Set will-close action (triggered before document closes).
     */
    public function onWillClose(Action $action): self
    {
        $this->additionalActions[self::TRIGGER_WILL_CLOSE] = $action;
        return $this;
    }

    /**
     * Get will-close action.
     */
    public function getWillCloseAction(): ?Action
    {
        return $this->additionalActions[self::TRIGGER_WILL_CLOSE] ?? null;
    }

    /**
     * Set will-save action (triggered before document saves).
     *
     * Useful for validation before saving.
     */
    public function onWillSave(Action $action): self
    {
        $this->additionalActions[self::TRIGGER_WILL_SAVE] = $action;
        return $this;
    }

    /**
     * Get will-save action.
     */
    public function getWillSaveAction(): ?Action
    {
        return $this->additionalActions[self::TRIGGER_WILL_SAVE] ?? null;
    }

    /**
     * Set did-save action (triggered after document saves).
     */
    public function onDidSave(Action $action): self
    {
        $this->additionalActions[self::TRIGGER_DID_SAVE] = $action;
        return $this;
    }

    /**
     * Get did-save action.
     */
    public function getDidSaveAction(): ?Action
    {
        return $this->additionalActions[self::TRIGGER_DID_SAVE] ?? null;
    }

    /**
     * Set will-print action (triggered before document prints).
     *
     * Useful for preparing print-specific formatting.
     */
    public function onWillPrint(Action $action): self
    {
        $this->additionalActions[self::TRIGGER_WILL_PRINT] = $action;
        return $this;
    }

    /**
     * Get will-print action.
     */
    public function getWillPrintAction(): ?Action
    {
        return $this->additionalActions[self::TRIGGER_WILL_PRINT] ?? null;
    }

    /**
     * Set did-print action (triggered after document prints).
     */
    public function onDidPrint(Action $action): self
    {
        $this->additionalActions[self::TRIGGER_DID_PRINT] = $action;
        return $this;
    }

    /**
     * Get did-print action.
     */
    public function getDidPrintAction(): ?Action
    {
        return $this->additionalActions[self::TRIGGER_DID_PRINT] ?? null;
    }

    /**
     * Add a named document-level JavaScript.
     *
     * Document-level scripts run when the document opens and can define
     * global functions and variables used by field actions.
     *
     * @param string $name Script name (must be unique)
     * @param string $script JavaScript code
     */
    public function addScript(string $name, string $script): self
    {
        $this->scripts[$name] = $script;
        return $this;
    }

    /**
     * Get a named script.
     */
    public function getScript(string $name): ?string
    {
        return $this->scripts[$name] ?? null;
    }

    /**
     * Get all scripts.
     *
     * @return array<string, string>
     */
    public function getScripts(): array
    {
        return $this->scripts;
    }

    /**
     * Remove a named script.
     */
    public function removeScript(string $name): self
    {
        unset($this->scripts[$name]);
        return $this;
    }

    /**
     * Check if any scripts are defined.
     */
    public function hasScripts(): bool
    {
        return !empty($this->scripts);
    }

    /**
     * Check if any additional actions are defined.
     */
    public function hasAdditionalActions(): bool
    {
        return !empty($this->additionalActions);
    }

    /**
     * Check if document has any actions or scripts.
     */
    public function isEmpty(): bool
    {
        return $this->openAction === null
            && empty($this->additionalActions)
            && empty($this->scripts);
    }

    /**
     * Build the catalog OpenAction entry.
     *
     * @return PdfDictionary|null OpenAction dictionary or null if not set
     */
    public function buildOpenAction(): ?PdfDictionary
    {
        if ($this->openAction === null) {
            return null;
        }

        return $this->openAction->toDictionary();
    }

    /**
     * Build the Additional Actions (AA) dictionary for the catalog.
     *
     * @return PdfDictionary|null AA dictionary or null if empty
     */
    public function buildAdditionalActions(): ?PdfDictionary
    {
        if (empty($this->additionalActions)) {
            return null;
        }

        $dict = new PdfDictionary();

        foreach ($this->additionalActions as $trigger => $action) {
            $dict->set($trigger, $action->toDictionary());
        }

        return $dict;
    }

    /**
     * Build the Names dictionary JavaScript entry.
     *
     * The JavaScript name tree contains document-level scripts that run
     * when the document opens. Scripts are executed in alphabetical order
     * by name.
     *
     * @return PdfDictionary|null Names.JavaScript dictionary or null if empty
     */
    public function buildJavaScriptNames(): ?PdfDictionary
    {
        if (empty($this->scripts)) {
            return null;
        }

        // Sort scripts by name (PDF viewers execute in this order)
        $sortedScripts = $this->scripts;
        ksort($sortedScripts);

        // Build the Names array: [name1, ref1, name2, ref2, ...]
        $namesArray = new PdfArray();

        foreach ($sortedScripts as $name => $script) {
            $namesArray->push(PdfString::literal($name));

            // Create JavaScript action dictionary
            $jsAction = new PdfDictionary();
            $jsAction->set('S', PdfName::create('JavaScript'));
            $jsAction->set('JS', PdfString::literal($script));

            $namesArray->push($jsAction);
        }

        // Build the JavaScript name tree
        $jsDict = new PdfDictionary();
        $jsDict->set('Names', $namesArray);

        return $jsDict;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->openAction !== null) {
            $data['openAction'] = $this->openAction->toArray();
        }

        if (!empty($this->additionalActions)) {
            $data['additionalActions'] = [];
            foreach ($this->additionalActions as $trigger => $action) {
                $data['additionalActions'][$trigger] = $action->toArray();
            }
        }

        if (!empty($this->scripts)) {
            $data['scripts'] = $this->scripts;
        }

        return $data;
    }

    // =========================================================================
    // HELPER METHODS FOR COMMON PATTERNS
    // =========================================================================

    /**
     * Add a welcome message on document open.
     *
     * @param string $message Message to display
     */
    public function addWelcomeMessage(string $message): self
    {
        return $this->onOpen(JavaScriptAction::alert($message, 3));
    }

    /**
     * Add form validation before save.
     *
     * Validates required fields and shows a message if validation fails.
     *
     * @param array<int, string> $requiredFields Field names that must be filled
     */
    public function validateBeforeSave(array $requiredFields): self
    {
        $fieldsJson = json_encode($requiredFields);
        $script = <<<JS
var required = $fieldsJson;
var missing = [];
for (var i = 0; i < required.length; i++) {
    var f = this.getField(required[i]);
    if (f && (f.value === "" || f.value === "Off")) {
        missing.push(required[i]);
    }
}
if (missing.length > 0) {
    app.alert("Please fill in the following required fields:\\n\\n" + missing.join("\\n"), 1);
}
JS;
        return $this->onWillSave(JavaScriptAction::create($script));
    }

    /**
     * Add confirmation before printing.
     *
     * @param string $message Confirmation message
     */
    public function confirmBeforePrint(string $message = 'Are you sure you want to print this document?'): self
    {
        $escapedMessage = addslashes($message);
        $script = <<<JS
var response = app.alert("$escapedMessage", 2, 2);
if (response !== 4) { // 4 = Yes
    event.rc = false;
}
JS;
        return $this->onWillPrint(JavaScriptAction::create($script));
    }

    /**
     * Add initialization script for global variables and functions.
     *
     * @param array<string, mixed> $variables Global variables to define
     */
    public function addGlobalVariables(array $variables): self
    {
        $lines = [];
        foreach ($variables as $name => $value) {
            if (is_string($value)) {
                $lines[] = "var $name = \"" . addslashes($value) . "\";";
            } elseif (is_bool($value)) {
                $lines[] = "var $name = " . ($value ? 'true' : 'false') . ";";
            } elseif (is_numeric($value)) {
                $lines[] = "var $name = $value;";
            } elseif (is_array($value)) {
                $lines[] = "var $name = " . json_encode($value) . ";";
            }
        }

        return $this->addScript('_globals', implode("\n", $lines));
    }

    /**
     * Add helper functions for field validation.
     */
    public function addValidationHelpers(): self
    {
        $script = <<<'JS'
function validateAllRequired() {
    var fields = this.getField("");
    if (!fields) return true;

    var missing = [];
    for (var i = 0; i < this.numFields; i++) {
        var name = this.getNthFieldName(i);
        var f = this.getField(name);
        if (f && f.required && (f.value === "" || f.value === "Off")) {
            missing.push(name);
        }
    }

    if (missing.length > 0) {
        app.alert("Required fields are empty:\n" + missing.join("\n"), 1);
        return false;
    }
    return true;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidNumber(value, min, max) {
    var num = parseFloat(value);
    if (isNaN(num)) return false;
    if (min !== undefined && num < min) return false;
    if (max !== undefined && num > max) return false;
    return true;
}
JS;
        return $this->addScript('_validationHelpers', $script);
    }

    /**
     * Add helper functions for calculations.
     */
    public function addCalculationHelpers(): self
    {
        $script = <<<'JS'
function sumFields(fieldNames) {
    var sum = 0;
    for (var i = 0; i < fieldNames.length; i++) {
        var f = this.getField(fieldNames[i]);
        if (f) {
            var val = parseFloat(f.value);
            if (!isNaN(val)) sum += val;
        }
    }
    return sum;
}

function productFields(fieldNames) {
    var product = 1;
    var hasValue = false;
    for (var i = 0; i < fieldNames.length; i++) {
        var f = this.getField(fieldNames[i]);
        if (f) {
            var val = parseFloat(f.value);
            if (!isNaN(val)) {
                product *= val;
                hasValue = true;
            }
        }
    }
    return hasValue ? product : 0;
}

function averageFields(fieldNames) {
    var sum = 0;
    var count = 0;
    for (var i = 0; i < fieldNames.length; i++) {
        var f = this.getField(fieldNames[i]);
        if (f) {
            var val = parseFloat(f.value);
            if (!isNaN(val)) {
                sum += val;
                count++;
            }
        }
    }
    return count > 0 ? sum / count : 0;
}

function getFieldValue(name, defaultValue) {
    var f = this.getField(name);
    if (!f || f.value === "") return defaultValue !== undefined ? defaultValue : 0;
    var val = parseFloat(f.value);
    return isNaN(val) ? (defaultValue !== undefined ? defaultValue : 0) : val;
}

function setFieldValue(name, value) {
    var f = this.getField(name);
    if (f) f.value = value;
}
JS;
        return $this->addScript('_calculationHelpers', $script);
    }

    /**
     * Add helper functions for formatting.
     */
    public function addFormatHelpers(): self
    {
        $script = <<<'JS'
function formatCurrency(value, symbol, decimals) {
    symbol = symbol || "$";
    decimals = decimals !== undefined ? decimals : 2;
    var num = parseFloat(value);
    if (isNaN(num)) return "";
    var fixed = num.toFixed(decimals);
    var parts = fixed.split(".");
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return symbol + parts.join(".");
}

function formatPercent(value, decimals) {
    decimals = decimals !== undefined ? decimals : 2;
    var num = parseFloat(value);
    if (isNaN(num)) return "";
    return num.toFixed(decimals) + "%";
}

function formatPhone(value) {
    var digits = value.replace(/\D/g, "");
    if (digits.length === 10) {
        return "(" + digits.substr(0, 3) + ") " + digits.substr(3, 3) + "-" + digits.substr(6, 4);
    }
    return value;
}

function formatDate(date, format) {
    format = format || "mm/dd/yyyy";
    return util.printd(format, date);
}
JS;
        return $this->addScript('_formatHelpers', $script);
    }

    /**
     * Add all common helper functions.
     */
    public function addAllHelpers(): self
    {
        return $this
            ->addValidationHelpers()
            ->addCalculationHelpers()
            ->addFormatHelpers();
    }
}
