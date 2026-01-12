<?php

declare(strict_types=1);

namespace PdfLib\Tests\Form;

use PHPUnit\Framework\TestCase;
use PdfLib\Form\Action\FieldActions;
use PdfLib\Form\Action\JavaScriptAction;

class FieldActionsTest extends TestCase
{
    public function testCreate(): void
    {
        $actions = FieldActions::create();

        $this->assertInstanceOf(FieldActions::class, $actions);
        $this->assertTrue($actions->isEmpty());
        $this->assertSame(0, $actions->count());
    }

    public function testOnKeystroke(): void
    {
        $action = JavaScriptAction::validateInteger();
        $actions = FieldActions::create()->onKeystroke($action);

        $this->assertSame($action, $actions->getKeystrokeAction());
        $this->assertFalse($actions->isEmpty());
    }

    public function testOnFormat(): void
    {
        $action = JavaScriptAction::formatNumber(2);
        $actions = FieldActions::create()->onFormat($action);

        $this->assertSame($action, $actions->getFormatAction());
    }

    public function testOnValidate(): void
    {
        $action = JavaScriptAction::validateEmail();
        $actions = FieldActions::create()->onValidate($action);

        $this->assertSame($action, $actions->getValidateAction());
    }

    public function testOnCalculate(): void
    {
        $action = JavaScriptAction::calculateSum(['a', 'b']);
        $actions = FieldActions::create()->onCalculate($action);

        $this->assertSame($action, $actions->getCalculateAction());
    }

    public function testOnFocus(): void
    {
        $action = JavaScriptAction::create('console.log("focused")');
        $actions = FieldActions::create()->onFocus($action);

        $this->assertSame($action, $actions->getFocusAction());
    }

    public function testOnBlur(): void
    {
        $action = JavaScriptAction::create('console.log("blurred")');
        $actions = FieldActions::create()->onBlur($action);

        $this->assertSame($action, $actions->getBlurAction());
    }

    public function testOnMouseDown(): void
    {
        $action = JavaScriptAction::create('onMouseDown()');
        $actions = FieldActions::create()->onMouseDown($action);

        $this->assertSame($action, $actions->getMouseDownAction());
    }

    public function testOnMouseUp(): void
    {
        $action = JavaScriptAction::create('onMouseUp()');
        $actions = FieldActions::create()->onMouseUp($action);

        $this->assertSame($action, $actions->getMouseUpAction());
    }

    public function testOnMouseEnter(): void
    {
        $action = JavaScriptAction::create('onEnter()');
        $actions = FieldActions::create()->onMouseEnter($action);

        $this->assertSame($action, $actions->getMouseEnterAction());
    }

    public function testOnMouseExit(): void
    {
        $action = JavaScriptAction::create('onExit()');
        $actions = FieldActions::create()->onMouseExit($action);

        $this->assertSame($action, $actions->getMouseExitAction());
    }

    public function testOnPageOpen(): void
    {
        $action = JavaScriptAction::create('pageOpened()');
        $actions = FieldActions::create()->onPageOpen($action);

        $this->assertSame($action, $actions->getPageOpenAction());
    }

    public function testOnPageClose(): void
    {
        $action = JavaScriptAction::create('pageClosed()');
        $actions = FieldActions::create()->onPageClose($action);

        $this->assertSame($action, $actions->getPageCloseAction());
    }

    public function testOnPageVisible(): void
    {
        $action = JavaScriptAction::create('pageVisible()');
        $actions = FieldActions::create()->onPageVisible($action);

        $this->assertSame($action, $actions->getPageVisibleAction());
    }

    public function testOnPageInvisible(): void
    {
        $action = JavaScriptAction::create('pageInvisible()');
        $actions = FieldActions::create()->onPageInvisible($action);

        $this->assertSame($action, $actions->getPageInvisibleAction());
    }

    public function testSetAction(): void
    {
        $action = JavaScriptAction::create('custom()');
        $actions = FieldActions::create()
            ->setAction(FieldActions::TRIGGER_KEYSTROKE, $action);

        $this->assertSame($action, $actions->getAction(FieldActions::TRIGGER_KEYSTROKE));
    }

    public function testRemoveAction(): void
    {
        $actions = FieldActions::create()
            ->onKeystroke(JavaScriptAction::create('test'))
            ->removeAction(FieldActions::TRIGGER_KEYSTROKE);

        $this->assertNull($actions->getKeystrokeAction());
        $this->assertTrue($actions->isEmpty());
    }

    public function testCount(): void
    {
        $actions = FieldActions::create()
            ->onKeystroke(JavaScriptAction::create('k'))
            ->onFormat(JavaScriptAction::create('f'))
            ->onValidate(JavaScriptAction::create('v'));

        $this->assertSame(3, $actions->count());
    }

    public function testGetTriggers(): void
    {
        $actions = FieldActions::create()
            ->onKeystroke(JavaScriptAction::create('k'))
            ->onValidate(JavaScriptAction::create('v'));

        $triggers = $actions->getTriggers();

        $this->assertContains(FieldActions::TRIGGER_KEYSTROKE, $triggers);
        $this->assertContains(FieldActions::TRIGGER_VALIDATE, $triggers);
        $this->assertCount(2, $triggers);
    }

    public function testToDictionary(): void
    {
        $actions = FieldActions::create()
            ->onKeystroke(JavaScriptAction::validateInteger())
            ->onFormat(JavaScriptAction::formatNumber(2));

        $dict = $actions->toDictionary();

        $this->assertTrue($dict->has('K'));
        $this->assertTrue($dict->has('F'));
    }

    public function testToArray(): void
    {
        $actions = FieldActions::create()
            ->onKeystroke(JavaScriptAction::create('keystroke()'))
            ->onValidate(JavaScriptAction::create('validate()'));

        $array = $actions->toArray();

        $this->assertArrayHasKey('K', $array);
        $this->assertArrayHasKey('V', $array);
    }

    public function testFluentChaining(): void
    {
        $actions = FieldActions::create()
            ->onKeystroke(JavaScriptAction::validateNumber(2))
            ->onFormat(JavaScriptAction::formatNumber(2, '$'))
            ->onValidate(JavaScriptAction::validateRange(0, 10000))
            ->onCalculate(JavaScriptAction::calculateSum(['price', 'tax']));

        $this->assertSame(4, $actions->count());
        $this->assertNotNull($actions->getKeystrokeAction());
        $this->assertNotNull($actions->getFormatAction());
        $this->assertNotNull($actions->getValidateAction());
        $this->assertNotNull($actions->getCalculateAction());
    }

    // Factory method tests

    public function testTextFieldFactory(): void
    {
        $actions = FieldActions::textField(
            JavaScriptAction::validateAlphanumeric(),
            JavaScriptAction::formatUppercase()
        );

        $this->assertNotNull($actions->getKeystrokeAction());
        $this->assertNotNull($actions->getFormatAction());
    }

    public function testNumberFieldFactory(): void
    {
        $actions = FieldActions::numberField(2, 0, 100, '$');

        $this->assertNotNull($actions->getKeystrokeAction());
        $this->assertNotNull($actions->getFormatAction());
        $this->assertNotNull($actions->getValidateAction());
    }

    public function testNumberFieldFactoryWithoutRange(): void
    {
        $actions = FieldActions::numberField(2);

        $this->assertNotNull($actions->getKeystrokeAction());
        $this->assertNotNull($actions->getFormatAction());
        $this->assertNull($actions->getValidateAction());
    }

    public function testEmailFieldFactory(): void
    {
        $actions = FieldActions::emailField();

        $this->assertNotNull($actions->getValidateAction());
    }

    public function testPhoneFieldFactory(): void
    {
        $actions = FieldActions::phoneField();

        $this->assertNotNull($actions->getKeystrokeAction());
        $this->assertNotNull($actions->getFormatAction());
    }

    public function testDateFieldFactory(): void
    {
        $actions = FieldActions::dateField('dd/mm/yyyy');

        $this->assertNotNull($actions->getValidateAction());
        $this->assertNotNull($actions->getFormatAction());
    }

    public function testPercentFieldFactory(): void
    {
        $actions = FieldActions::percentField(1);

        $this->assertNotNull($actions->getKeystrokeAction());
        $this->assertNotNull($actions->getFormatAction());
    }

    public function testCalculatedFieldFactory(): void
    {
        $calcAction = JavaScriptAction::calculateSum(['a', 'b']);
        $actions = FieldActions::calculatedField($calcAction, 2, '$');

        $this->assertNotNull($actions->getCalculateAction());
        $this->assertNotNull($actions->getFormatAction());
    }

    public function testGetActionReturnsNullForUnset(): void
    {
        $actions = FieldActions::create();

        $this->assertNull($actions->getKeystrokeAction());
        $this->assertNull($actions->getFormatAction());
        $this->assertNull($actions->getValidateAction());
        $this->assertNull($actions->getCalculateAction());
        $this->assertNull($actions->getFocusAction());
        $this->assertNull($actions->getBlurAction());
    }
}
