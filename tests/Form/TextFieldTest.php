<?php

declare(strict_types=1);

namespace PdfLib\Tests\Form;

use PHPUnit\Framework\TestCase;
use PdfLib\Form\TextField;
use PdfLib\Form\Action\JavaScriptAction;
use PdfLib\Form\Action\FieldActions;

class TextFieldTest extends TestCase
{
    public function testCreate(): void
    {
        $field = TextField::create('username');

        $this->assertInstanceOf(TextField::class, $field);
        $this->assertSame('username', $field->getName());
        $this->assertSame('Tx', $field->getFieldType());
    }

    public function testSetValue(): void
    {
        $field = TextField::create('email');
        $field->setValue('test@example.com');

        $this->assertSame('test@example.com', $field->getValue());
    }

    public function testSetDefaultValue(): void
    {
        $field = TextField::create('country');
        $field->setDefaultValue('United States');

        $this->assertSame('United States', $field->getDefaultValue());
    }

    public function testPosition(): void
    {
        $field = TextField::create('name');
        $field->setPosition(100.0, 200.0);

        $this->assertSame(100.0, $field->getX());
        $this->assertSame(200.0, $field->getY());
    }

    public function testSize(): void
    {
        $field = TextField::create('address');
        $field->setSize(200.0, 30.0);

        $this->assertSame(200.0, $field->getWidth());
        $this->assertSame(30.0, $field->getHeight());
    }

    public function testRect(): void
    {
        $field = TextField::create('city');
        $field->setPosition(50.0, 100.0)->setSize(150.0, 25.0);

        $rect = $field->getRect();
        $this->assertSame([50.0, 100.0, 200.0, 125.0], $rect);
    }

    public function testFluentSetters(): void
    {
        $field = TextField::create('notes')
            ->setPosition(10.0, 20.0)
            ->setSize(100.0, 50.0)
            ->setValue('Test notes')
            ->setRequired()
            ->setMultiline()
            ->setMaxLength(500);

        $this->assertSame(10.0, $field->getX());
        $this->assertSame('Test notes', $field->getValue());
        $this->assertTrue($field->isRequired());
        $this->assertTrue($field->isMultiline());
        $this->assertSame(500, $field->getMaxLength());
    }

    public function testReadOnly(): void
    {
        $field = TextField::create('readonly_field');

        $this->assertFalse($field->isReadOnly());

        $field->setReadOnly();
        $this->assertTrue($field->isReadOnly());

        $field->setReadOnly(false);
        $this->assertFalse($field->isReadOnly());
    }

    public function testRequired(): void
    {
        $field = TextField::create('required_field');

        $this->assertFalse($field->isRequired());

        $field->setRequired();
        $this->assertTrue($field->isRequired());
    }

    public function testMultiline(): void
    {
        $field = TextField::create('multiline_field');

        $this->assertFalse($field->isMultiline());

        $field->setMultiline();
        $this->assertTrue($field->isMultiline());
    }

    public function testPassword(): void
    {
        $field = TextField::create('password_field');

        $this->assertFalse($field->isPassword());

        $field->setPassword();
        $this->assertTrue($field->isPassword());
    }

    public function testMaxLength(): void
    {
        $field = TextField::create('limited_field');

        $this->assertNull($field->getMaxLength());

        $field->setMaxLength(100);
        $this->assertSame(100, $field->getMaxLength());
    }

    public function testTooltip(): void
    {
        $field = TextField::create('with_tooltip');
        $field->setTooltip('Enter your email address');

        $this->assertSame('Enter your email address', $field->getTooltip());
    }

    public function testMappingName(): void
    {
        $field = TextField::create('user_email');
        $field->setMappingName('email');

        $this->assertSame('email', $field->getMappingName());
    }

    public function testBorderStyle(): void
    {
        $field = TextField::create('styled_field');
        $field->setBorderStyle('dashed')
            ->setBorderWidth(2.0)
            ->setBorderColor(1.0, 0.0, 0.0);

        // Verify field was created without exception
        $this->assertInstanceOf(TextField::class, $field);
    }

    public function testBackgroundColor(): void
    {
        $field = TextField::create('colored_field');
        $field->setBackgroundColor(0.9, 0.9, 1.0);

        // Verify field was created without exception
        $this->assertInstanceOf(TextField::class, $field);
    }

    public function testFontSettings(): void
    {
        $field = TextField::create('styled_text');
        $field->setFont('Helvetica', 14.0)
            ->setTextColor(0.0, 0.0, 0.5);

        $this->assertSame('Helvetica', $field->getFontName());
        $this->assertSame(14.0, $field->getFontSize());
    }

    public function testTextAlignment(): void
    {
        $field = TextField::create('aligned_field');

        $this->assertSame(TextField::ALIGN_LEFT, $field->getAlignment());

        $field->alignCenter();
        $this->assertSame(TextField::ALIGN_CENTER, $field->getAlignment());

        $field->alignRight();
        $this->assertSame(TextField::ALIGN_RIGHT, $field->getAlignment());
    }

    public function testCombField(): void
    {
        $field = TextField::create('comb_field');

        $this->assertFalse($field->isComb());

        $field->setComb()->setMaxLength(10);
        $this->assertTrue($field->isComb());
    }

    public function testToWidgetDictionary(): void
    {
        $field = TextField::create('test_field')
            ->setPosition(100.0, 200.0)
            ->setSize(150.0, 25.0)
            ->setValue('Test Value');

        $dict = $field->toWidgetDictionary();

        $this->assertSame('Annot', $dict->get('Type')->getValue());
        $this->assertSame('Widget', $dict->get('Subtype')->getValue());
        $this->assertSame('Tx', $dict->get('FT')->getValue());
        $this->assertSame('test_field', $dict->get('T')->getValue());
        $this->assertSame('Test Value', $dict->get('V')->getValue());
    }

    public function testToArray(): void
    {
        $field = TextField::create('array_test')
            ->setPosition(50.0, 100.0)
            ->setSize(200.0, 30.0)
            ->setValue('Array Value')
            ->setRequired();

        $array = $field->toArray();

        $this->assertSame('array_test', $array['name']);
        $this->assertSame([50.0, 100.0, 250.0, 130.0], $array['rect']);
        $this->assertSame('Array Value', $array['value']);
    }

    public function testFromArray(): void
    {
        $data = [
            'name' => 'restored_field',
            'page' => 2,
            'rect' => [10.0, 20.0, 110.0, 45.0],
            'value' => 'Restored Value',
            'defaultValue' => 'Default',
            'multiline' => true,
            'maxLength' => 255,
        ];

        $field = TextField::fromArray($data);

        $this->assertSame('restored_field', $field->getName());
        $this->assertSame(2, $field->getPage());
        $this->assertSame('Restored Value', $field->getValue());
        $this->assertTrue($field->isMultiline());
        $this->assertSame(255, $field->getMaxLength());
    }

    public function testGenerateAppearance(): void
    {
        $field = TextField::create('appearance_test')
            ->setPosition(0.0, 0.0)
            ->setSize(100.0, 20.0)
            ->setValue('Hello World');

        $appearance = $field->generateAppearance();

        $this->assertIsString($appearance);
        $this->assertStringContainsString('BT', $appearance);
        $this->assertStringContainsString('ET', $appearance);
        $this->assertStringContainsString('Hello World', $appearance);
    }

    public function testPage(): void
    {
        $field = TextField::create('page_test');

        $this->assertSame(1, $field->getPage());

        $field->setPage(3);
        $this->assertSame(3, $field->getPage());
    }

    public function testPageInvalidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $field = TextField::create('invalid_page');
        $field->setPage(0);
    }

    public function testWithFieldActions(): void
    {
        $field = TextField::create('with_actions')
            ->setPosition(100.0, 200.0)
            ->setSize(200.0, 25.0);

        $actions = FieldActions::create()
            ->onKeystroke(JavaScriptAction::validateNumber(2))
            ->onFormat(JavaScriptAction::formatNumber(2, '$'));

        $field->setActions($actions);

        $this->assertSame($actions, $field->getActions());
    }

    public function testOnKeystrokeShortcut(): void
    {
        $field = TextField::create('shortcut_test')
            ->onKeystroke(JavaScriptAction::validateInteger());

        $this->assertNotNull($field->getActions());
        $this->assertNotNull($field->getActions()->getKeystrokeAction());
    }

    public function testOnFormatShortcut(): void
    {
        $field = TextField::create('format_test')
            ->onFormat(JavaScriptAction::formatNumber(2, '$'));

        $this->assertNotNull($field->getActions());
        $this->assertNotNull($field->getActions()->getFormatAction());
    }

    public function testOnValidateShortcut(): void
    {
        $field = TextField::create('validate_test')
            ->onValidate(JavaScriptAction::validateEmail());

        $this->assertNotNull($field->getActions());
        $this->assertNotNull($field->getActions()->getValidateAction());
    }

    public function testOnCalculateShortcut(): void
    {
        $field = TextField::create('calc_test')
            ->onCalculate(JavaScriptAction::calculateSum(['price', 'tax']));

        $this->assertNotNull($field->getActions());
        $this->assertNotNull($field->getActions()->getCalculateAction());
    }
}
