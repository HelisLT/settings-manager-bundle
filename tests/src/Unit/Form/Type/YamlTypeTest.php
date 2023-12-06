<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Form\Type;

use Helis\SettingsManagerBundle\Form\Type\YamlType;
use Symfony\Component\Form\Test\TypeTestCase;

class YamlTypeTest extends TypeTestCase
{
    public static function dataProviderTestSubmit(): array
    {
        return [
            [json_encode('pineapple'), ['pineapple']],
            [json_encode(['pineapple']), ['pineapple']],
            ['pineapple', ['pineapple']],
            ['', []],
            [null, []],
        ];
    }

    /**
     * @dataProvider dataProviderTestSubmit
     */
    public function testSubmit($submitData, $expectedData)
    {
        $form = $this->factory->create(YamlType::class);
        $form->submit($submitData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expectedData, $form->getData());
    }
}
