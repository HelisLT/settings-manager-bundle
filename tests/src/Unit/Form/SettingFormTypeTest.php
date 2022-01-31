<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Form;

use Helis\SettingsManagerBundle\Form\SettingFormType;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type;
use Symfony\Component\Form\Test\TypeTestCase;

class SettingFormTypeTest extends TypeTestCase
{
    public function submitValidDataProvider()
    {
        // test bool submit
        $data1 = new SettingModel();
        $data1->setType(Type::BOOL());

        $formData1 = [
            'name' => 'foo',
            'description' => 'lorem ipsum',
            'data' => 1,
        ];

        $object1 = new SettingModel();
        $object1
            ->setDescription('lorem ipsum')
            ->setDomain(new DomainModel())
            ->setType(Type::BOOL())
            ->setData(true);

        // 0: bool as bool
        yield [$formData1, $data1, $object1];

        // test string submit
        $data2 = new SettingModel();
        $data2->setType(Type::STRING());

        $formData2 = [
            'name' => 'foo',
            'data' => 2.5678,
        ];

        $object2 = new SettingModel();
        $object2
            ->setType(Type::STRING())
            ->setDomain(new DomainModel())
            ->setData('2.5678');

        // 1: float as string
        yield [$formData2, $data2, $object2];

        // test float submit
        $data3 = new SettingModel();
        $data3->setType(Type::FLOAT());

        $formData3 = [
            'name' => 'foo',
            'data' => 2.5678,
        ];

        $object3 = new SettingModel();
        $object3
            ->setType(Type::FLOAT())
            ->setDomain(new DomainModel())
            ->setData(2.57);

        // 2: float as round float
        yield [$formData3, $data3, $object3];
    }

    /**
     * @dataProvider submitValidDataProvider
     */
    public function testSubmitValidData(array $formData, SettingModel $data, SettingModel $expectedObject)
    {
        $form = $this->factory->create(SettingFormType::class, $data);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expectedObject, $form->getData());
    }
}
