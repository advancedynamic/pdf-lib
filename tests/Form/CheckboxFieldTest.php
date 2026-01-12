<?php

declare(strict_types=1);

namespace PdfLib\Tests\Form;

use PHPUnit\Framework\TestCase;
use PdfLib\Form\CheckboxField;

class CheckboxFieldTest extends TestCase
{
    public function testCreate(): void
    {
        $field = CheckboxField::create('agree');

        $this->assertInstanceOf(CheckboxField::class, $field);
        $this->assertSame('agree', $field->getName());
        $this->assertSame('Btn', $field->getFieldType());
    }

    public function testDefaultCheckedState(): void
    {
        $field = CheckboxField::create('checkbox1');

        $this->assertFalse($field->isChecked());
    }

    public function testSetChecked(): void
    {
        $field = CheckboxField::create('terms');

        $field->setChecked(true);
        $this->assertTrue($field->isChecked());
        $this->assertTrue($field->getValue());

        $field->setChecked(false);
        $this->assertFalse($field->isChecked());
        $this->assertFalse($field->getValue());
    }

    public function testSetValue(): void
    {
        $field = CheckboxField::create('accept');

        $field->setValue(true);
        $this->assertTrue($field->isChecked());

        $field->setValue(false);
        $this->assertFalse($field->isChecked());
    }

    public function testCheck(): void
    {
        $field = CheckboxField::create('opt_in');

        $field->check();
        $this->assertTrue($field->isChecked());
    }

    public function testUncheck(): void
    {
        $field = CheckboxField::create('opt_out');

        $field->check();
        $field->uncheck();
        $this->assertFalse($field->isChecked());
    }

    public function testExportValue(): void
    {
        $field = CheckboxField::create('newsletter');

        $this->assertSame('Yes', $field->getExportValue());

        $field->setExportValue('subscribe');
        $this->assertSame('subscribe', $field->getExportValue());
    }

    public function testPosition(): void
    {
        $field = CheckboxField::create('positioned')
            ->setPosition(100.0, 200.0);

        $this->assertSame(100.0, $field->getX());
        $this->assertSame(200.0, $field->getY());
    }

    public function testSize(): void
    {
        $field = CheckboxField::create('sized')
            ->setSize(20.0, 20.0);

        $this->assertSame(20.0, $field->getWidth());
        $this->assertSame(20.0, $field->getHeight());
    }

    public function testFluentSetters(): void
    {
        $field = CheckboxField::create('fluent')
            ->setPosition(50.0, 150.0)
            ->setSize(16.0, 16.0)
            ->setChecked(true)
            ->setExportValue('enabled')
            ->setRequired();

        $this->assertSame(50.0, $field->getX());
        $this->assertSame(16.0, $field->getWidth());
        $this->assertTrue($field->isChecked());
        $this->assertSame('enabled', $field->getExportValue());
        $this->assertTrue($field->isRequired());
    }

    public function testToWidgetDictionary(): void
    {
        $field = CheckboxField::create('widget_test')
            ->setPosition(100.0, 200.0)
            ->setSize(16.0, 16.0)
            ->setChecked(true);

        $dict = $field->toWidgetDictionary();

        $this->assertSame('Annot', $dict->get('Type')->getValue());
        $this->assertSame('Widget', $dict->get('Subtype')->getValue());
        $this->assertSame('Btn', $dict->get('FT')->getValue());
        $this->assertSame('widget_test', $dict->get('T')->getValue());
    }

    public function testToArray(): void
    {
        $field = CheckboxField::create('array_test')
            ->setPosition(10.0, 20.0)
            ->setSize(14.0, 14.0)
            ->setChecked(true)
            ->setExportValue('accept');

        $array = $field->toArray();

        $this->assertSame('array_test', $array['name']);
        $this->assertTrue($array['checked']);
        $this->assertSame('accept', $array['exportValue']);
    }

    public function testFromArray(): void
    {
        $data = [
            'name' => 'restored_checkbox',
            'page' => 2,
            'rect' => [100.0, 200.0, 116.0, 216.0],
            'checked' => true,
            'exportValue' => 'confirmed',
        ];

        $field = CheckboxField::fromArray($data);

        $this->assertSame('restored_checkbox', $field->getName());
        $this->assertSame(2, $field->getPage());
        $this->assertTrue($field->isChecked());
        $this->assertSame('confirmed', $field->getExportValue());
    }

    public function testGenerateAppearance(): void
    {
        $field = CheckboxField::create('appearance_test')
            ->setPosition(0.0, 0.0)
            ->setSize(16.0, 16.0)
            ->setChecked(true);

        $appearance = $field->generateAppearance();

        $this->assertIsString($appearance);
        // Checkmarks typically use ZapfDingbats 4 (checkmark character)
        $this->assertNotEmpty($appearance);
    }

    public function testGenerateAppearanceUnchecked(): void
    {
        $field = CheckboxField::create('unchecked_appearance')
            ->setPosition(0.0, 0.0)
            ->setSize(16.0, 16.0)
            ->setChecked(false);

        $appearance = $field->generateAppearance();

        $this->assertIsString($appearance);
    }

    public function testReadOnly(): void
    {
        $field = CheckboxField::create('readonly_check')
            ->setReadOnly();

        $this->assertTrue($field->isReadOnly());
    }

    public function testBorderAndBackground(): void
    {
        $field = CheckboxField::create('styled_check')
            ->setBorderColor(0.0, 0.0, 0.0)
            ->setBorderWidth(1.0)
            ->setBackgroundColor(1.0, 1.0, 1.0);

        $this->assertInstanceOf(CheckboxField::class, $field);
    }

    public function testPage(): void
    {
        $field = CheckboxField::create('page_test')
            ->setPage(5);

        $this->assertSame(5, $field->getPage());
    }

    public function testTooltip(): void
    {
        $field = CheckboxField::create('tooltip_check')
            ->setTooltip('Click to agree to terms and conditions');

        $this->assertSame('Click to agree to terms and conditions', $field->getTooltip());
    }

    public function testGetDefaultValue(): void
    {
        $field = CheckboxField::create('default_check');

        // Default value should return false for unchecked
        $this->assertFalse($field->getDefaultValue());
    }

    public function testGetRect(): void
    {
        $field = CheckboxField::create('rect_test')
            ->setPosition(50.0, 100.0)
            ->setSize(20.0, 20.0);

        $rect = $field->getRect();
        $this->assertSame([50.0, 100.0, 70.0, 120.0], $rect);
    }

    public function testSetRect(): void
    {
        $field = CheckboxField::create('set_rect');
        $field->setRect([10.0, 20.0, 30.0, 40.0]);

        $this->assertSame(10.0, $field->getX());
        $this->assertSame(20.0, $field->getY());
        $this->assertSame(20.0, $field->getWidth());
        $this->assertSame(20.0, $field->getHeight());
    }
}
