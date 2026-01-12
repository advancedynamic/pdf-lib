<?php

declare(strict_types=1);

namespace PdfLib\Tests\Form;

use PHPUnit\Framework\TestCase;
use PdfLib\Form\AcroForm;
use PdfLib\Form\TextField;
use PdfLib\Form\CheckboxField;
use PdfLib\Form\DropdownField;
use PdfLib\Form\ListBoxField;
use PdfLib\Form\RadioButtonGroup;
use PdfLib\Exception\FormException;

class AcroFormTest extends TestCase
{
    public function testCreate(): void
    {
        $form = AcroForm::create();

        $this->assertInstanceOf(AcroForm::class, $form);
        $this->assertTrue($form->isEmpty());
        $this->assertSame(0, $form->count());
    }

    public function testAddTextField(): void
    {
        $form = AcroForm::create();
        $field = TextField::create('username');

        $form->addField($field);

        $this->assertFalse($form->isEmpty());
        $this->assertSame(1, $form->count());
        $this->assertTrue($form->hasField('username'));
        $this->assertSame($field, $form->getField('username'));
    }

    public function testAddCheckbox(): void
    {
        $form = AcroForm::create();
        $checkbox = CheckboxField::create('agree');

        $form->addField($checkbox);

        $this->assertTrue($form->hasField('agree'));
    }

    public function testAddRadioGroup(): void
    {
        $form = AcroForm::create();
        $group = RadioButtonGroup::create('gender');

        $form->addRadioGroup($group);

        $this->assertTrue($form->hasField('gender'));
        $this->assertSame($group, $form->getRadioGroup('gender'));
    }

    public function testCreateTextField(): void
    {
        $form = AcroForm::create();
        $field = $form->createTextField('email');

        $this->assertInstanceOf(TextField::class, $field);
        $this->assertSame('email', $field->getName());
        $this->assertTrue($form->hasField('email'));
    }

    public function testCreateCheckbox(): void
    {
        $form = AcroForm::create();
        $field = $form->createCheckbox('newsletter');

        $this->assertInstanceOf(CheckboxField::class, $field);
        $this->assertTrue($form->hasField('newsletter'));
    }

    public function testCreateDropdown(): void
    {
        $form = AcroForm::create();
        $field = $form->createDropdown('country');

        $this->assertInstanceOf(DropdownField::class, $field);
        $this->assertTrue($form->hasField('country'));
    }

    public function testCreateListBox(): void
    {
        $form = AcroForm::create();
        $field = $form->createListBox('interests');

        $this->assertInstanceOf(ListBoxField::class, $field);
        $this->assertTrue($form->hasField('interests'));
    }

    public function testCreateRadioGroup(): void
    {
        $form = AcroForm::create();
        $group = $form->createRadioGroup('payment');

        $this->assertInstanceOf(RadioButtonGroup::class, $group);
        $this->assertTrue($form->hasField('payment'));
    }

    public function testGetFields(): void
    {
        $form = AcroForm::create();
        $form->createTextField('name');
        $form->createTextField('email');
        $form->createCheckbox('subscribe');

        $fields = $form->getFields();

        $this->assertSame(3, count($fields));
    }

    public function testGetFieldNames(): void
    {
        $form = AcroForm::create();
        $form->createTextField('first');
        $form->createTextField('second');
        $form->createRadioGroup('option');

        $names = $form->getFieldNames();

        $this->assertContains('first', $names);
        $this->assertContains('second', $names);
        $this->assertContains('option', $names);
    }

    public function testRemoveField(): void
    {
        $form = AcroForm::create();
        $form->createTextField('toRemove');
        $form->createTextField('toKeep');

        $form->removeField('toRemove');

        $this->assertFalse($form->hasField('toRemove'));
        $this->assertTrue($form->hasField('toKeep'));
    }

    public function testRemoveRadioGroup(): void
    {
        $form = AcroForm::create();
        $form->createRadioGroup('toRemove');
        $form->createTextField('toKeep');

        $form->removeField('toRemove');

        $this->assertFalse($form->hasField('toRemove'));
        $this->assertTrue($form->hasField('toKeep'));
    }

    public function testClear(): void
    {
        $form = AcroForm::create();
        $form->createTextField('field1');
        $form->createTextField('field2');
        $form->createRadioGroup('radio1');

        $form->clear();

        $this->assertTrue($form->isEmpty());
        $this->assertSame(0, $form->count());
    }

    public function testGetFieldValues(): void
    {
        $form = AcroForm::create();

        $form->createTextField('name')->setValue('John Doe');
        $form->createTextField('email')->setValue('john@example.com');
        $form->createCheckbox('subscribe')->setChecked(true);

        $values = $form->getFieldValues();

        $this->assertSame('John Doe', $values['name']);
        $this->assertSame('john@example.com', $values['email']);
        $this->assertTrue($values['subscribe']);
    }

    public function testSetFieldValues(): void
    {
        $form = AcroForm::create();
        $form->createTextField('name');
        $form->createTextField('email');

        $form->setFieldValues([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $this->assertSame('Jane Doe', $form->getField('name')->getValue());
        $this->assertSame('jane@example.com', $form->getField('email')->getValue());
    }

    public function testSetFieldValuesThrowsForUnknownField(): void
    {
        $this->expectException(FormException::class);

        $form = AcroForm::create();
        $form->createTextField('name');

        $form->setFieldValues([
            'unknown' => 'value',
        ]);
    }

    public function testNeedsAppearances(): void
    {
        $form = AcroForm::create();

        $this->assertFalse($form->needsAppearances());

        $form->setNeedsAppearances(true);
        $this->assertTrue($form->needsAppearances());

        $form->setNeedsAppearances(false);
        $this->assertFalse($form->needsAppearances());
    }

    public function testSigFlags(): void
    {
        $form = AcroForm::create();

        $this->assertSame(0, $form->getSigFlags());

        $form->setSigFlags(AcroForm::SIG_FLAGS_SIGNATURES_EXIST);
        $this->assertSame(1, $form->getSigFlags());

        $form->setSignaturesExist();
        $this->assertTrue(($form->getSigFlags() & AcroForm::SIG_FLAGS_SIGNATURES_EXIST) !== 0);

        $form->setAppendOnly();
        $this->assertTrue(($form->getSigFlags() & AcroForm::SIG_FLAGS_APPEND_ONLY) !== 0);
    }

    public function testDefaultAppearance(): void
    {
        $form = AcroForm::create();

        $this->assertNull($form->getDefaultAppearance());

        $form->setDefaultAppearance('/Helv 12 Tf 0 g');
        $this->assertSame('/Helv 12 Tf 0 g', $form->getDefaultAppearance());
    }

    public function testCalculationOrder(): void
    {
        $form = AcroForm::create();

        $this->assertEmpty($form->getCalculationOrder());

        $form->setCalculationOrder(['subtotal', 'tax', 'total']);
        $this->assertSame(['subtotal', 'tax', 'total'], $form->getCalculationOrder());

        $form->addToCalculationOrder('discount');
        $this->assertContains('discount', $form->getCalculationOrder());
    }

    public function testAddToCalculationOrderNoDuplicates(): void
    {
        $form = AcroForm::create();
        $form->setCalculationOrder(['field1', 'field2']);

        $form->addToCalculationOrder('field1');

        $order = $form->getCalculationOrder();
        $this->assertSame(2, count($order));
    }

    public function testToDictionary(): void
    {
        $form = AcroForm::create();
        $form->setNeedsAppearances(true);
        $form->setDefaultAppearance('/Helv 10 Tf 0 g');

        $dict = $form->toDictionary();

        $this->assertTrue($dict->has('Fields'));
        $this->assertTrue($dict->has('NeedAppearances'));
        $this->assertTrue($dict->has('DA'));
    }

    public function testToArray(): void
    {
        $form = AcroForm::create();
        $form->createTextField('test');
        $form->setNeedsAppearances(true);

        $array = $form->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('fields', $array);
        $this->assertArrayHasKey('needsAppearances', $array);
        $this->assertTrue($array['needsAppearances']);
    }

    public function testFromArray(): void
    {
        $data = [
            'needsAppearances' => true,
            'sigFlags' => 1,
            'defaultAppearance' => '/Helv 12 Tf 0 g',
            'calculationOrder' => ['total'],
        ];

        $form = AcroForm::fromArray($data);

        $this->assertTrue($form->needsAppearances());
        $this->assertSame(1, $form->getSigFlags());
        $this->assertSame('/Helv 12 Tf 0 g', $form->getDefaultAppearance());
        $this->assertSame(['total'], $form->getCalculationOrder());
    }

    public function testGetRadioGroups(): void
    {
        $form = AcroForm::create();
        $form->createRadioGroup('gender');
        $form->createRadioGroup('payment');

        $groups = $form->getRadioGroups();

        $this->assertCount(2, $groups);
        $this->assertArrayHasKey('gender', $groups);
        $this->assertArrayHasKey('payment', $groups);
    }

    public function testGetRadioGroupReturnsNull(): void
    {
        $form = AcroForm::create();

        $this->assertNull($form->getRadioGroup('nonexistent'));
    }

    public function testGetFieldReturnsNull(): void
    {
        $form = AcroForm::create();

        $this->assertNull($form->getField('nonexistent'));
    }

    public function testCountIncludesRadioGroups(): void
    {
        $form = AcroForm::create();
        $form->createTextField('name');
        $form->createTextField('email');
        $form->createRadioGroup('gender');

        // 2 text fields + 1 radio group = 3
        $this->assertSame(3, $form->count());
    }

    public function testDuplicateFieldNameThrowsException(): void
    {
        $this->expectException(FormException::class);

        $form = AcroForm::create();
        $form->createTextField('duplicate');
        $form->createTextField('duplicate');
    }
}
