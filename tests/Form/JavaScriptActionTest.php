<?php

declare(strict_types=1);

namespace PdfLib\Tests\Form;

use PHPUnit\Framework\TestCase;
use PdfLib\Form\Action\JavaScriptAction;
use PdfLib\Form\Action\Action;

class JavaScriptActionTest extends TestCase
{
    public function testCreate(): void
    {
        $action = JavaScriptAction::create('app.alert("Hello!");');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertSame('app.alert("Hello!");', $action->getScript());
    }

    public function testGetType(): void
    {
        $action = JavaScriptAction::create('');

        $this->assertSame(Action::TYPE_JAVASCRIPT, $action->getType());
    }

    public function testSetScript(): void
    {
        $action = JavaScriptAction::create('initial');
        $action->setScript('updated');

        $this->assertSame('updated', $action->getScript());
    }

    public function testToDictionary(): void
    {
        $action = JavaScriptAction::create('console.log("test");');
        $dict = $action->toDictionary();

        $this->assertSame('Action', $dict->get('Type')->getValue());
        $this->assertSame('JavaScript', $dict->get('S')->getValue());
        $this->assertTrue($dict->has('JS'));
    }

    public function testToArray(): void
    {
        $action = JavaScriptAction::create('myScript()');
        $array = $action->toArray();

        $this->assertSame('JavaScript', $array['type']);
        $this->assertSame('myScript()', $array['script']);
    }

    public function testSetNext(): void
    {
        $action1 = JavaScriptAction::create('first()');
        $action2 = JavaScriptAction::create('second()');

        $action1->setNext($action2);

        $this->assertTrue($action1->hasNext());
        $this->assertSame($action2, $action1->getNext());
    }

    public function testNextInDictionary(): void
    {
        $action1 = JavaScriptAction::create('first()');
        $action2 = JavaScriptAction::create('second()');
        $action1->setNext($action2);

        $dict = $action1->toDictionary();

        $this->assertTrue($dict->has('Next'));
    }

    // Pre-built Validation Tests

    public function testValidateInteger(): void
    {
        $action = JavaScriptAction::validateInteger();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('event', $action->getScript());
    }

    public function testValidateNumber(): void
    {
        $action = JavaScriptAction::validateNumber(2);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('decimalPlaces', $script);
    }

    public function testValidatePhone(): void
    {
        $action = JavaScriptAction::validatePhone();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('phone', strtolower($action->getScript()));
    }

    public function testValidateAlphanumeric(): void
    {
        $action = JavaScriptAction::validateAlphanumeric();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('a-zA-Z0-9', $action->getScript());
    }

    public function testValidateLettersOnly(): void
    {
        $action = JavaScriptAction::validateLettersOnly();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('a-zA-Z', $action->getScript());
    }

    public function testValidateEmail(): void
    {
        $action = JavaScriptAction::validateEmail();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('email', strtolower($action->getScript()));
    }

    public function testValidateRange(): void
    {
        $action = JavaScriptAction::validateRange(0, 100);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('0', $script);
        $this->assertStringContainsString('100', $script);
    }

    public function testValidateMinLength(): void
    {
        $action = JavaScriptAction::validateMinLength(5);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('5', $action->getScript());
    }

    public function testValidateMaxLength(): void
    {
        $action = JavaScriptAction::validateMaxLength(50);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('50', $action->getScript());
    }

    public function testValidatePattern(): void
    {
        $action = JavaScriptAction::validatePattern('^[A-Z]{3}$', 'Must be 3 uppercase letters');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('A-Z', $action->getScript());
    }

    public function testValidateDate(): void
    {
        $action = JavaScriptAction::validateDate('mm/dd/yyyy');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('mm/dd/yyyy', $action->getScript());
    }

    public function testValidateRequired(): void
    {
        $action = JavaScriptAction::validateRequired();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('required', strtolower($action->getScript()));
    }

    // Pre-built Calculation Tests

    public function testCalculateSum(): void
    {
        $action = JavaScriptAction::calculateSum(['price', 'tax', 'shipping']);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('sum', $script);
        $this->assertStringContainsString('price', $script);
    }

    public function testCalculateProduct(): void
    {
        $action = JavaScriptAction::calculateProduct(['qty', 'price']);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('product', $script);
    }

    public function testCalculateAverage(): void
    {
        $action = JavaScriptAction::calculateAverage(['score1', 'score2', 'score3']);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('sum', $script);
        $this->assertStringContainsString('count', $script);
    }

    public function testCalculateMin(): void
    {
        $action = JavaScriptAction::calculateMin(['a', 'b', 'c']);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('min', strtolower($action->getScript()));
    }

    public function testCalculateMax(): void
    {
        $action = JavaScriptAction::calculateMax(['a', 'b', 'c']);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('max', strtolower($action->getScript()));
    }

    public function testCalculatePercentage(): void
    {
        $action = JavaScriptAction::calculatePercentage('part', 'total');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('100', $script);
    }

    public function testCalculateFormula(): void
    {
        $action = JavaScriptAction::calculateFormula('{price} * {qty} * (1 - {discount})');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('price', $script);
        $this->assertStringContainsString('qty', $script);
    }

    // Pre-built Format Tests

    public function testFormatNumber(): void
    {
        $action = JavaScriptAction::formatNumber(2, '$');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('toFixed', $script);
    }

    public function testFormatPercent(): void
    {
        $action = JavaScriptAction::formatPercent(2);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('%', $action->getScript());
    }

    public function testFormatDate(): void
    {
        $action = JavaScriptAction::formatDate('mm/dd/yyyy');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('printd', $action->getScript());
    }

    public function testFormatUppercase(): void
    {
        $action = JavaScriptAction::formatUppercase();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('toUpperCase', $action->getScript());
    }

    public function testFormatLowercase(): void
    {
        $action = JavaScriptAction::formatLowercase();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('toLowerCase', $action->getScript());
    }

    public function testFormatTitleCase(): void
    {
        $action = JavaScriptAction::formatTitleCase();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('toUpperCase', $script);
        $this->assertStringContainsString('toLowerCase', $script);
    }

    public function testFormatPhone(): void
    {
        $action = JavaScriptAction::formatPhone();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('digits', $action->getScript());
    }

    public function testFormatSSN(): void
    {
        $action = JavaScriptAction::formatSSN();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('9', $action->getScript());
    }

    public function testFormatZipCode(): void
    {
        $action = JavaScriptAction::formatZipCode();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('5', $action->getScript());
    }

    public function testFormatCreditCard(): void
    {
        $action = JavaScriptAction::formatCreditCard();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('4', $action->getScript());
    }

    // Utility Actions

    public function testAlert(): void
    {
        $action = JavaScriptAction::alert('Test message');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('app.alert', $action->getScript());
        $this->assertStringContainsString('Test message', $action->getScript());
    }

    public function testSetField(): void
    {
        $action = JavaScriptAction::setField('targetField', 'newValue');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('targetField', $script);
        $this->assertStringContainsString('newValue', $script);
    }

    public function testClearField(): void
    {
        $action = JavaScriptAction::clearField('fieldToClear');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('fieldToClear', $action->getScript());
    }

    public function testSetFieldVisibility(): void
    {
        $showAction = JavaScriptAction::setFieldVisibility('field1', true);
        $hideAction = JavaScriptAction::setFieldVisibility('field2', false);

        $this->assertStringContainsString('visible', $showAction->getScript());
        $this->assertStringContainsString('hidden', $hideAction->getScript());
    }

    public function testSetFieldReadOnly(): void
    {
        $action = JavaScriptAction::setFieldReadOnly('field1', true);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('readonly', $action->getScript());
    }

    public function testFocusField(): void
    {
        $action = JavaScriptAction::focusField('fieldToFocus');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('setFocus', $action->getScript());
    }

    public function testPrintDocument(): void
    {
        $action = JavaScriptAction::printDocument();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('print', $action->getScript());
    }

    public function testResetForm(): void
    {
        $action = JavaScriptAction::resetForm();

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('resetForm', $action->getScript());
    }

    public function testResetFields(): void
    {
        $action = JavaScriptAction::resetFields(['field1', 'field2']);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('resetForm', $script);
        $this->assertStringContainsString('field1', $script);
    }

    public function testConditionalVisibility(): void
    {
        $action = JavaScriptAction::conditionalVisibility('type', 'other', ['otherField']);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('type', $script);
        $this->assertStringContainsString('other', $script);
    }

    public function testCopyFieldValue(): void
    {
        $action = JavaScriptAction::copyFieldValue('source', 'target');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $script = $action->getScript();
        $this->assertStringContainsString('source', $script);
        $this->assertStringContainsString('target', $script);
    }

    public function testGoToPage(): void
    {
        $action = JavaScriptAction::goToPage(5);

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('pageNum', $action->getScript());
        $this->assertStringContainsString('5', $action->getScript());
    }

    public function testOpenURL(): void
    {
        $action = JavaScriptAction::openURL('https://example.com');

        $this->assertInstanceOf(JavaScriptAction::class, $action);
        $this->assertStringContainsString('launchURL', $action->getScript());
    }

    public function testSequence(): void
    {
        $actions = [
            JavaScriptAction::create('first()'),
            JavaScriptAction::create('second()'),
            JavaScriptAction::create('third()'),
        ];

        $combined = JavaScriptAction::sequence($actions);

        $this->assertInstanceOf(JavaScriptAction::class, $combined);
        $script = $combined->getScript();
        $this->assertStringContainsString('first()', $script);
        $this->assertStringContainsString('second()', $script);
        $this->assertStringContainsString('third()', $script);
    }
}
