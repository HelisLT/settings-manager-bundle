<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Validator\Constraints;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type;
use Helis\SettingsManagerBundle\Validator\Constraints\SettingType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

class SettingTypeValidatorTest extends TestCase
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $builder = new ValidatorBuilder();
        $builder->disableAnnotationMapping();
        $this->validator = $builder->getValidator();
    }

    public static function dataProviderTestValid(): array
    {
        return [
            [Type::INT(), 13],
            [Type::FLOAT(), 0.1],
            [Type::FLOAT(), 0.11111],
            [Type::STRING(), 'Batman'],
            [Type::STRING(), '5'],
            [Type::BOOL(), true],
            [Type::BOOL(), false],
            [Type::YAML(), []],
            [Type::YAML(), ['haha']],
            [Type::YAML(), ['batman' => 1, 'robin' => 0]],
        ];
    }

    /**
     * @param mixed $data
     *
     * @dataProvider dataProviderTestValid
     */
    public function testValid(Type $type, $data)
    {
        $setting = new SettingModel();
        $setting->setType($type);
        $setting->setData($data);

        $violations = $this->validator->validate($setting, new SettingType());
        $this->assertCount(0, $violations);
    }

    public static function dataProviderTestInvalid(): array
    {
        return [
            [Type::BOOL(), 12, 'Setting data value should be of type bool.'],
            [Type::BOOL(), 'false', 'Setting data value should be of type bool.'],
            [Type::STRING(), 0.1, 'Setting data value should be of type string.'],
            [Type::STRING(), false, 'Setting data value should be of type string.'],
            [Type::INT(), 2.2, 'Setting data value should be of type int.'],
            [Type::INT(), true, 'Setting data value should be of type int.'],
            [Type::FLOAT(), 1, 'Setting data value should be of type float.'],
            [Type::FLOAT(), '2', 'Setting data value should be of type float.'],
            [Type::YAML(), '[]', 'Setting data value should be of type array.'],
            [Type::YAML(), true, 'Setting data value should be of type array.'],
        ];
    }

    /**
     * @param mixed $data
     *
     * @dataProvider dataProviderTestInvalid
     */
    public function testInvalid(Type $type, $data, string $message)
    {
        $setting = new SettingModel();
        $setting->setType($type);
        $setting->setData($data);

        $violations = $this->validator->validate($setting, new SettingType());

        $this->assertCount(1, $violations);
        $this->assertEquals($message, $violations[0]->getMessage());
    }
}
