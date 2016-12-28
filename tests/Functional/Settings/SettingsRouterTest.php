<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Settings;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Helis\SettingsManagerBundle\Model\Type;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Helis\SettingsManagerBundle\Tests\Functional\DataFixtures\ORM\LoadSettingsData;

class SettingsRouterTest extends WebTestCase
{
    /**
     * @var SettingsRouter
     */
    private $settingsRouter;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->settingsRouter = $this->getContainer()->get(SettingsRouter::class);
    }

    /**
     * @return array
     */
    public function getSettingDataProvider(): array
    {
        return [
            ['foo', 'fixture bool foo setting', [], Type::BOOL(), true, 'orm'],
            ['baz', 'baz desc', ['experimental', 'poo'], Type::BOOL(), true, 'config'],
            ['tuna', 'tuna desc', [], Type::STRING(), 'fish', 'config'],
            [
                'wth_yaml',
                'ohohoho',
                [],
                Type::YAML(),
                ['amazing' => ['foo', 'foo', 'foo', 'yee'], 'cool' => ['yes' => ['yes', 'no']], 'damn' => 5],
                'config'
            ],
        ];
    }

    /**
     * @param string $settingName
     * @param string $expectedDescription
     * @param array $expectedTags
     * @param Type $expectedType
     * @param mixed $expectedData
     * @param string $expectedProvider
     *
     * @dataProvider getSettingDataProvider
     */
    public function testGetSetting(
        string $settingName,
        string $expectedDescription,
        array $expectedTags,
        Type $expectedType,
        $expectedData,
        string $expectedProvider
    ) {
        $this->loadFixtures([LoadSettingsData::class]);
        $setting = $this->settingsRouter->getSetting($settingName);

        $this->assertNotFalse($settingName, 'Setting not found');
        $this->assertEquals($expectedDescription, $setting->getDescription());
        $this->assertTrue($setting->getType()->equals($expectedType));
        $this->assertEquals($expectedData, $setting->getData());
        $this->assertEquals($expectedProvider, $setting->getProviderName());

        if ($expectedType->equals(Type::STRING())) {
            $this->assertEquals($expectedData, $this->settingsRouter->getString($settingName));
        } elseif ($expectedType->equals(Type::BOOL())) {
            $this->assertEquals($expectedData, $this->settingsRouter->getBool($settingName));
        } elseif ($expectedType->equals(Type::YAML())) {
            $this->assertEquals($expectedData, $this->settingsRouter->getArray($settingName));
        }

        foreach ($expectedTags as $expectedTag) {
            $this->assertTrue($setting->hasTag($expectedTag), 'Missing tag');
        }
    }

    public function testWarmupWithoutSave()
    {
        $this->loadFixtures([]);

        $value = $this->settingsRouter->getBool('foo');
        $this->assertFalse($value);

        $this->settingsRouter->warmup();

        $value = $this->settingsRouter->getBool('foo');
        $this->assertFalse($value);
    }

    public function testWarmupWithSave()
    {
        $this->loadFixtures([]);

        $this->settingsRouter->getSetting('baz');
        $setting = $this->settingsRouter->getSetting('foo');
        $this->assertFalse($setting->getData());

        $settingsManager = $this->getContainer()->get(SettingsManager::class);
        $setting->setData(true);
        $settingsManager->update($setting);

        $this->settingsRouter->warmup();

        $this->assertEquals(
            'orm',
            $this->settingsRouter->getSetting('foo')->getProviderName(),
            'foo should be fetched from orm'
        );
        $this->assertEquals(
            'config',
            $this->settingsRouter->getSetting('baz')->getProviderName(),
            'baz should be fetched from config'
        );
    }
}
