<?php

declare(strict_types=1);

namespace PdfLib\Form\Action;

use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfString;

/**
 * JavaScript action for PDF forms.
 *
 * Executes JavaScript code when triggered. Used for form field validation,
 * calculations, formatting, and custom interactivity.
 *
 * PDF Reference: Section 8.6.4 "JavaScript Actions"
 *
 * @example Basic usage:
 * ```php
 * $action = JavaScriptAction::create('app.alert("Hello!");');
 * ```
 *
 * @example Pre-built validations:
 * ```php
 * // Validate integer input
 * $field->onKeystroke(JavaScriptAction::validateInteger());
 *
 * // Validate number with 2 decimal places
 * $field->onKeystroke(JavaScriptAction::validateNumber(2));
 *
 * // Validate email format
 * $field->onValidate(JavaScriptAction::validateEmail());
 *
 * // Validate range
 * $field->onValidate(JavaScriptAction::validateRange(0, 100));
 * ```
 *
 * @example Pre-built calculations:
 * ```php
 * // Sum of fields
 * $total->onCalculate(JavaScriptAction::calculateSum(['price', 'tax', 'shipping']));
 *
 * // Product of fields
 * $result->onCalculate(JavaScriptAction::calculateProduct(['qty', 'price']));
 * ```
 *
 * @example Pre-built formats:
 * ```php
 * // Format as currency
 * $price->onFormat(JavaScriptAction::formatNumber(2, '$'));
 *
 * // Format as date
 * $date->onFormat(JavaScriptAction::formatDate('mm/dd/yyyy'));
 *
 * // Format as percentage
 * $percent->onFormat(JavaScriptAction::formatPercent(2));
 * ```
 */
final class JavaScriptAction extends Action
{
    private string $script;

    public function __construct(string $script)
    {
        $this->script = $script;
    }

    /**
     * Create a JavaScript action with the given script.
     */
    public static function create(string $script): self
    {
        return new self($script);
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return self::TYPE_JAVASCRIPT;
    }

    /**
     * Get the JavaScript code.
     */
    public function getScript(): string
    {
        return $this->script;
    }

    /**
     * Set the JavaScript code.
     */
    public function setScript(string $script): self
    {
        $this->script = $script;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildActionEntries(PdfDictionary $dict): void
    {
        // JavaScript can be a text string or a stream
        // For simplicity, we use a text string
        $dict->set('JS', PdfString::literal($this->script));
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'script' => $this->script,
        ]);
    }

    // =========================================================================
    // PRE-BUILT KEYSTROKE VALIDATIONS
    // =========================================================================

    /**
     * Validate integer input (keystroke event).
     *
     * Only allows digits and optional leading minus sign.
     */
    public static function validateInteger(): self
    {
        return new self(<<<'JS'
if (event.willCommit) {
    if (event.value !== "" && !/^-?\d+$/.test(event.value)) {
        app.alert("Please enter a valid integer.");
        event.rc = false;
    }
} else {
    event.rc = /^-?\d*$/.test(event.change) || event.change === "-" && event.selStart === 0;
}
JS);
    }

    /**
     * Validate number input with specified decimal places (keystroke event).
     *
     * @param int $decimals Maximum decimal places allowed (0 for integer)
     * @param bool $allowNegative Allow negative numbers
     */
    public static function validateNumber(int $decimals = 2, bool $allowNegative = true): self
    {
        $negativePattern = $allowNegative ? '-?' : '';
        $script = <<<JS
var decimalPlaces = $decimals;
var allowNeg = $allowNegative;

if (event.willCommit) {
    if (event.value !== "") {
        var pattern = allowNeg ? /^-?\\d*\\.?\\d*$/ : /^\\d*\\.?\\d*$/;
        if (!pattern.test(event.value)) {
            app.alert("Please enter a valid number.");
            event.rc = false;
        } else {
            var parts = event.value.split(".");
            if (parts.length > 1 && parts[1].length > decimalPlaces) {
                app.alert("Maximum " + decimalPlaces + " decimal places allowed.");
                event.rc = false;
            }
        }
    }
} else {
    var newValue = AFMergeChange(event);
    var pattern = allowNeg ? /^-?\\d*\\.?\\d*$/ : /^\\d*\\.?\\d*$/;
    event.rc = pattern.test(newValue);
}
JS;
        return new self($script);
    }

    /**
     * Validate phone number input (keystroke event).
     *
     * Allows digits, spaces, dashes, parentheses, and plus sign.
     */
    public static function validatePhone(): self
    {
        return new self(<<<'JS'
if (event.willCommit) {
    if (event.value !== "" && !/^[\d\s\-\(\)\+]+$/.test(event.value)) {
        app.alert("Please enter a valid phone number.");
        event.rc = false;
    }
} else {
    event.rc = /^[\d\s\-\(\)\+]*$/.test(event.change);
}
JS);
    }

    /**
     * Validate alphanumeric input only (keystroke event).
     */
    public static function validateAlphanumeric(): self
    {
        return new self(<<<'JS'
if (event.willCommit) {
    if (event.value !== "" && !/^[a-zA-Z0-9]+$/.test(event.value)) {
        app.alert("Please enter only letters and numbers.");
        event.rc = false;
    }
} else {
    event.rc = /^[a-zA-Z0-9]*$/.test(event.change);
}
JS);
    }

    /**
     * Validate letters only input (keystroke event).
     */
    public static function validateLettersOnly(): self
    {
        return new self(<<<'JS'
if (event.willCommit) {
    if (event.value !== "" && !/^[a-zA-Z\s]+$/.test(event.value)) {
        app.alert("Please enter only letters.");
        event.rc = false;
    }
} else {
    event.rc = /^[a-zA-Z\s]*$/.test(event.change);
}
JS);
    }

    // =========================================================================
    // PRE-BUILT VALIDATE ACTIONS
    // =========================================================================

    /**
     * Validate email format (validate event).
     */
    public static function validateEmail(): self
    {
        return new self(<<<'JS'
if (event.value !== "") {
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(event.value)) {
        app.alert("Please enter a valid email address.");
        event.rc = false;
    }
}
JS);
    }

    /**
     * Validate value is within a range (validate event).
     *
     * @param float $min Minimum value
     * @param float $max Maximum value
     */
    public static function validateRange(float $min, float $max): self
    {
        $script = <<<JS
if (event.value !== "") {
    var num = parseFloat(event.value);
    if (isNaN(num) || num < $min || num > $max) {
        app.alert("Value must be between $min and $max.");
        event.rc = false;
    }
}
JS;
        return new self($script);
    }

    /**
     * Validate minimum length (validate event).
     *
     * @param int $minLength Minimum character length
     */
    public static function validateMinLength(int $minLength): self
    {
        $script = <<<JS
if (event.value !== "" && event.value.length < $minLength) {
    app.alert("Please enter at least $minLength characters.");
    event.rc = false;
}
JS;
        return new self($script);
    }

    /**
     * Validate maximum length (validate event).
     *
     * @param int $maxLength Maximum character length
     */
    public static function validateMaxLength(int $maxLength): self
    {
        $script = <<<JS
if (event.value.length > $maxLength) {
    app.alert("Please enter no more than $maxLength characters.");
    event.rc = false;
}
JS;
        return new self($script);
    }

    /**
     * Validate using a custom regular expression (validate event).
     *
     * @param string $pattern Regular expression pattern (without delimiters)
     * @param string $message Error message to display
     */
    public static function validatePattern(string $pattern, string $message = 'Invalid format.'): self
    {
        $escapedPattern = addslashes($pattern);
        $escapedMessage = addslashes($message);
        $script = <<<JS
if (event.value !== "") {
    var pattern = new RegExp("$escapedPattern");
    if (!pattern.test(event.value)) {
        app.alert("$escapedMessage");
        event.rc = false;
    }
}
JS;
        return new self($script);
    }

    /**
     * Validate date format (validate event).
     *
     * @param string $format Date format (e.g., "mm/dd/yyyy", "dd-mm-yyyy")
     */
    public static function validateDate(string $format = 'mm/dd/yyyy'): self
    {
        $escapedFormat = addslashes($format);
        $script = <<<JS
if (event.value !== "") {
    var d = util.scand("$escapedFormat", event.value);
    if (d === null) {
        app.alert("Please enter a valid date in format: $escapedFormat");
        event.rc = false;
    }
}
JS;
        return new self($script);
    }

    /**
     * Validate required field on blur (blur event).
     */
    public static function validateRequired(): self
    {
        return new self(<<<'JS'
if (event.value === "") {
    app.alert("This field is required.");
    event.target.textColor = color.red;
} else {
    event.target.textColor = color.black;
}
JS);
    }

    // =========================================================================
    // PRE-BUILT CALCULATIONS
    // =========================================================================

    /**
     * Calculate sum of field values.
     *
     * @param array<int, string> $fieldNames Names of fields to sum
     */
    public static function calculateSum(array $fieldNames): self
    {
        $fieldsJson = json_encode($fieldNames);
        $script = <<<JS
var fields = $fieldsJson;
var sum = 0;
for (var i = 0; i < fields.length; i++) {
    var f = this.getField(fields[i]);
    if (f) {
        var val = parseFloat(f.value);
        if (!isNaN(val)) {
            sum += val;
        }
    }
}
event.value = sum;
JS;
        return new self($script);
    }

    /**
     * Calculate product of field values.
     *
     * @param array<int, string> $fieldNames Names of fields to multiply
     */
    public static function calculateProduct(array $fieldNames): self
    {
        $fieldsJson = json_encode($fieldNames);
        $script = <<<JS
var fields = $fieldsJson;
var product = 1;
var hasValue = false;
for (var i = 0; i < fields.length; i++) {
    var f = this.getField(fields[i]);
    if (f) {
        var val = parseFloat(f.value);
        if (!isNaN(val)) {
            product *= val;
            hasValue = true;
        }
    }
}
event.value = hasValue ? product : 0;
JS;
        return new self($script);
    }

    /**
     * Calculate average of field values.
     *
     * @param array<int, string> $fieldNames Names of fields to average
     */
    public static function calculateAverage(array $fieldNames): self
    {
        $fieldsJson = json_encode($fieldNames);
        $script = <<<JS
var fields = $fieldsJson;
var sum = 0;
var count = 0;
for (var i = 0; i < fields.length; i++) {
    var f = this.getField(fields[i]);
    if (f) {
        var val = parseFloat(f.value);
        if (!isNaN(val)) {
            sum += val;
            count++;
        }
    }
}
event.value = count > 0 ? sum / count : 0;
JS;
        return new self($script);
    }

    /**
     * Calculate minimum of field values.
     *
     * @param array<int, string> $fieldNames Names of fields to compare
     */
    public static function calculateMin(array $fieldNames): self
    {
        $fieldsJson = json_encode($fieldNames);
        $script = <<<JS
var fields = $fieldsJson;
var min = Infinity;
for (var i = 0; i < fields.length; i++) {
    var f = this.getField(fields[i]);
    if (f) {
        var val = parseFloat(f.value);
        if (!isNaN(val) && val < min) {
            min = val;
        }
    }
}
event.value = min === Infinity ? "" : min;
JS;
        return new self($script);
    }

    /**
     * Calculate maximum of field values.
     *
     * @param array<int, string> $fieldNames Names of fields to compare
     */
    public static function calculateMax(array $fieldNames): self
    {
        $fieldsJson = json_encode($fieldNames);
        $script = <<<JS
var fields = $fieldsJson;
var max = -Infinity;
for (var i = 0; i < fields.length; i++) {
    var f = this.getField(fields[i]);
    if (f) {
        var val = parseFloat(f.value);
        if (!isNaN(val) && val > max) {
            max = val;
        }
    }
}
event.value = max === -Infinity ? "" : max;
JS;
        return new self($script);
    }

    /**
     * Calculate percentage: (value / total) * 100.
     *
     * @param string $valueField Field containing the value
     * @param string $totalField Field containing the total
     */
    public static function calculatePercentage(string $valueField, string $totalField): self
    {
        $escapedValue = addslashes($valueField);
        $escapedTotal = addslashes($totalField);
        $script = <<<JS
var valueField = this.getField("$escapedValue");
var totalField = this.getField("$escapedTotal");
if (valueField && totalField) {
    var value = parseFloat(valueField.value) || 0;
    var total = parseFloat(totalField.value) || 0;
    event.value = total !== 0 ? (value / total) * 100 : 0;
} else {
    event.value = 0;
}
JS;
        return new self($script);
    }

    /**
     * Custom calculation with a formula.
     *
     * Use field names in the formula (e.g., "{price} * {qty}").
     *
     * @param string $formula Formula with {fieldName} placeholders
     */
    public static function calculateFormula(string $formula): self
    {
        // Extract field names from {fieldName} placeholders
        preg_match_all('/\{([^}]+)\}/', $formula, $matches);
        $fieldNames = array_unique($matches[1]);

        $fieldsJson = json_encode($fieldNames);

        // Create JavaScript to build the formula
        $script = <<<JS
var fields = $fieldsJson;
var formula = "$formula";
var values = {};

for (var i = 0; i < fields.length; i++) {
    var name = fields[i];
    var f = this.getField(name);
    var val = 0;
    if (f) {
        val = parseFloat(f.value);
        if (isNaN(val)) val = 0;
    }
    values[name] = val;
}

// Replace {fieldName} with actual values
for (var name in values) {
    formula = formula.replace(new RegExp("\\\\{" + name + "\\\\}", "g"), values[name]);
}

try {
    event.value = eval(formula);
} catch (e) {
    event.value = 0;
}
JS;
        return new self($script);
    }

    // =========================================================================
    // PRE-BUILT FORMAT ACTIONS
    // =========================================================================

    /**
     * Format number with decimal places and optional currency symbol.
     *
     * @param int $decimals Number of decimal places
     * @param string|null $currencySymbol Currency symbol (e.g., "$", "€")
     * @param bool $symbolBefore Put symbol before the number
     * @param string $thousandsSep Thousands separator
     * @param string $decimalSep Decimal separator
     */
    public static function formatNumber(
        int $decimals = 2,
        ?string $currencySymbol = null,
        bool $symbolBefore = true,
        string $thousandsSep = ',',
        string $decimalSep = '.'
    ): self {
        $symbol = $currencySymbol !== null ? addslashes($currencySymbol) : '';
        $before = $symbolBefore ? 'true' : 'false';
        $escapedThousands = addslashes($thousandsSep);
        $escapedDecimal = addslashes($decimalSep);

        $script = <<<JS
if (event.value !== "") {
    var num = parseFloat(event.value);
    if (!isNaN(num)) {
        var decimals = $decimals;
        var symbol = "$symbol";
        var symbolBefore = $before;
        var thousandsSep = "$escapedThousands";
        var decimalSep = "$escapedDecimal";

        var fixed = num.toFixed(decimals);
        var parts = fixed.split(".");
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
        var formatted = parts.join(decimalSep);

        if (symbol !== "") {
            event.value = symbolBefore ? symbol + formatted : formatted + symbol;
        } else {
            event.value = formatted;
        }
    }
}
JS;
        return new self($script);
    }

    /**
     * Format as percentage.
     *
     * @param int $decimals Number of decimal places
     */
    public static function formatPercent(int $decimals = 2): self
    {
        $script = <<<JS
if (event.value !== "") {
    var num = parseFloat(event.value);
    if (!isNaN(num)) {
        event.value = num.toFixed($decimals) + "%";
    }
}
JS;
        return new self($script);
    }

    /**
     * Format date.
     *
     * @param string $format Date format (e.g., "mm/dd/yyyy", "dd-mmm-yyyy")
     */
    public static function formatDate(string $format = 'mm/dd/yyyy'): self
    {
        $escapedFormat = addslashes($format);
        $script = <<<JS
if (event.value !== "") {
    var d = util.scand("$escapedFormat", event.value);
    if (d !== null) {
        event.value = util.printd("$escapedFormat", d);
    }
}
JS;
        return new self($script);
    }

    /**
     * Format as uppercase.
     */
    public static function formatUppercase(): self
    {
        return new self('event.value = event.value.toUpperCase();');
    }

    /**
     * Format as lowercase.
     */
    public static function formatLowercase(): self
    {
        return new self('event.value = event.value.toLowerCase();');
    }

    /**
     * Format with title case (capitalize first letter of each word).
     */
    public static function formatTitleCase(): self
    {
        return new self(<<<'JS'
event.value = event.value.replace(/\w\S*/g, function(txt) {
    return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
});
JS);
    }

    /**
     * Format phone number.
     *
     * Formats as (XXX) XXX-XXXX for 10-digit numbers.
     */
    public static function formatPhone(): self
    {
        return new self(<<<'JS'
var digits = event.value.replace(/\D/g, "");
if (digits.length === 10) {
    event.value = "(" + digits.substr(0, 3) + ") " + digits.substr(3, 3) + "-" + digits.substr(6, 4);
} else if (digits.length === 11 && digits.charAt(0) === "1") {
    event.value = "1 (" + digits.substr(1, 3) + ") " + digits.substr(4, 3) + "-" + digits.substr(7, 4);
}
JS);
    }

    /**
     * Format Social Security Number (XXX-XX-XXXX).
     */
    public static function formatSSN(): self
    {
        return new self(<<<'JS'
var digits = event.value.replace(/\D/g, "");
if (digits.length === 9) {
    event.value = digits.substr(0, 3) + "-" + digits.substr(3, 2) + "-" + digits.substr(5, 4);
}
JS);
    }

    /**
     * Format ZIP code (XXXXX or XXXXX-XXXX).
     */
    public static function formatZipCode(): self
    {
        return new self(<<<'JS'
var digits = event.value.replace(/\D/g, "");
if (digits.length === 5) {
    event.value = digits;
} else if (digits.length === 9) {
    event.value = digits.substr(0, 5) + "-" + digits.substr(5, 4);
}
JS);
    }

    /**
     * Format credit card number with spaces (XXXX XXXX XXXX XXXX).
     */
    public static function formatCreditCard(): self
    {
        return new self(<<<'JS'
var digits = event.value.replace(/\D/g, "");
if (digits.length >= 13 && digits.length <= 19) {
    var formatted = "";
    for (var i = 0; i < digits.length; i++) {
        if (i > 0 && i % 4 === 0) {
            formatted += " ";
        }
        formatted += digits.charAt(i);
    }
    event.value = formatted;
}
JS);
    }

    // =========================================================================
    // PRE-BUILT UTILITY ACTIONS
    // =========================================================================

    /**
     * Show an alert message.
     *
     * @param string $message The message to display
     * @param int $icon Icon type (0=error, 1=warning, 2=question, 3=info)
     */
    public static function alert(string $message, int $icon = 3): self
    {
        $escapedMessage = addslashes($message);
        return new self("app.alert(\"$escapedMessage\", $icon);");
    }

    /**
     * Set a field's value.
     *
     * @param string $fieldName Name of the field to set
     * @param string $value Value to set
     */
    public static function setField(string $fieldName, string $value): self
    {
        $escapedField = addslashes($fieldName);
        $escapedValue = addslashes($value);
        return new self("this.getField(\"$escapedField\").value = \"$escapedValue\";");
    }

    /**
     * Clear a field's value.
     *
     * @param string $fieldName Name of the field to clear
     */
    public static function clearField(string $fieldName): self
    {
        $escapedField = addslashes($fieldName);
        return new self("this.getField(\"$escapedField\").value = \"\";");
    }

    /**
     * Show or hide a field.
     *
     * @param string $fieldName Name of the field
     * @param bool $visible Whether to show (true) or hide (false)
     */
    public static function setFieldVisibility(string $fieldName, bool $visible): self
    {
        $escapedField = addslashes($fieldName);
        $display = $visible ? 'display.visible' : 'display.hidden';
        return new self("this.getField(\"$escapedField\").display = $display;");
    }

    /**
     * Set field read-only state.
     *
     * @param string $fieldName Name of the field
     * @param bool $readOnly Whether field should be read-only
     */
    public static function setFieldReadOnly(string $fieldName, bool $readOnly): self
    {
        $escapedField = addslashes($fieldName);
        $value = $readOnly ? 'true' : 'false';
        return new self("this.getField(\"$escapedField\").readonly = $value;");
    }

    /**
     * Focus on a specific field.
     *
     * @param string $fieldName Name of the field to focus
     */
    public static function focusField(string $fieldName): self
    {
        $escapedField = addslashes($fieldName);
        return new self("this.getField(\"$escapedField\").setFocus();");
    }

    /**
     * Print the document.
     *
     * @param bool $showDialog Whether to show the print dialog
     */
    public static function printDocument(bool $showDialog = true): self
    {
        return new self($showDialog ? 'this.print(true);' : 'this.print({bUI: false, bSilent: true});');
    }

    /**
     * Reset all form fields.
     */
    public static function resetForm(): self
    {
        return new self('this.resetForm();');
    }

    /**
     * Reset specific form fields.
     *
     * @param array<int, string> $fieldNames Names of fields to reset
     */
    public static function resetFields(array $fieldNames): self
    {
        $fieldsJson = json_encode($fieldNames);
        return new self("this.resetForm($fieldsJson);");
    }

    /**
     * Conditional field visibility based on another field's value.
     *
     * @param string $conditionField Field to check
     * @param string $expectedValue Value that triggers visibility
     * @param array<int, string> $targetFields Fields to show/hide
     */
    public static function conditionalVisibility(
        string $conditionField,
        string $expectedValue,
        array $targetFields
    ): self {
        $escapedCondition = addslashes($conditionField);
        $escapedExpected = addslashes($expectedValue);
        $fieldsJson = json_encode($targetFields);

        $script = <<<JS
var conditionField = this.getField("$escapedCondition");
var targetFields = $fieldsJson;
var show = conditionField && conditionField.value === "$escapedExpected";

for (var i = 0; i < targetFields.length; i++) {
    var f = this.getField(targetFields[i]);
    if (f) {
        f.display = show ? display.visible : display.hidden;
    }
}
JS;
        return new self($script);
    }

    /**
     * Copy value from one field to another.
     *
     * @param string $sourceField Field to copy from
     * @param string $targetField Field to copy to
     */
    public static function copyFieldValue(string $sourceField, string $targetField): self
    {
        $escapedSource = addslashes($sourceField);
        $escapedTarget = addslashes($targetField);
        $script = <<<JS
var source = this.getField("$escapedSource");
var target = this.getField("$escapedTarget");
if (source && target) {
    target.value = source.value;
}
JS;
        return new self($script);
    }

    /**
     * Go to a specific page in the document.
     *
     * @param int $pageNumber Page number (0-indexed)
     */
    public static function goToPage(int $pageNumber): self
    {
        return new self("this.pageNum = $pageNumber;");
    }

    /**
     * Open a URL in the browser.
     *
     * @param string $url URL to open
     */
    public static function openURL(string $url): self
    {
        $escapedUrl = addslashes($url);
        return new self("app.launchURL(\"$escapedUrl\", true);");
    }

    /**
     * Execute multiple actions in sequence.
     *
     * @param array<int, self> $actions Array of JavaScriptActions
     */
    public static function sequence(array $actions): self
    {
        $scripts = array_map(fn(self $action) => $action->getScript(), $actions);
        return new self(implode("\n", $scripts));
    }
}
