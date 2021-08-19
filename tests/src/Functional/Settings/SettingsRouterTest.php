<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Settings;

use Helis\SettingsManagerBundle\Settings\SettingsStore;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Helis\SettingsManagerBundle\Model\Type;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use App\DataFixtures\ORM\LoadSettingsData;
use Liip\TestFixturesBundle\Test\FixturesTrait;

/**
 * @IgnoreAnnotation("dataProvider")
 */
class SettingsRouterTest extends WebTestCase
{
    use FixturesTrait;

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

    public function getSettingsByTagDataProvider(): array
    {
        return [
            ['experimental', 1, ['baz']],
            ['poo', 1, ['baz']],
            ['super_switch', 1, ['foo']],
            ['non-existing', 0, []],
        ];
    }

    /**
     * @param string[] $expectedSettingKeys
     *
     * @dataProvider getSettingsByTagDataProvider
     */
    public function testGetSettingsByTag(
        string $tagName,
        int $expectedSettingCount,
        array $expectedSettingKeys
    ): void {
        $this->loadFixtures([LoadSettingsData::class]);
        $settings = $this->settingsRouter->getSettingsByTag($tagName);
        $this->assertCount($expectedSettingCount, $settings);
        $this->assertEquals($expectedSettingKeys, array_keys($settings));

        foreach ($settings as $setting) {
            $this->assertTrue($setting->hasTag($tagName));
        }
    }

    public function warmUpClearDataProvider(): array
    {
        return [
            ['fixture', 'bazinga'],
        ];
    }

    /**
     * @dataProvider warmUpClearDataProvider
     */
    public function testWarmUpClear(string $tagName, string $settingName): void {
        $this->loadFixtures([LoadSettingsData::class]);
        $settingsStore = $this->getContainer()->get(SettingsStore::class);
        $settings = $this->settingsRouter->getSettingsByTag($tagName);

        $this->assertArrayHasKey($settingName, $settings);
        $this->assertEquals($settingName, $settings[$settingName]->getName());

        // Called getSettingsByTag, store is warm
        $this->assertTrue($this->settingsRouter->isWarm());
        $this->assertTrue($settingsStore->hasSettingsByTag($tagName));
        // No $settingNamesToWarmup, so this only clears store
        $this->settingsRouter->warmup();
        $this->assertFalse($this->settingsRouter->isWarm());
        $this->assertFalse($settingsStore->hasSettingsByTag($tagName));

        // Called getSetting, store is warm
        $this->assertNotNull($this->settingsRouter->getSetting($settingName));
        $this->assertTrue($this->settingsRouter->isWarm());
        // There are $settingNamesToWarmup
        $this->settingsRouter->warmup();
        $this->assertTrue($this->settingsRouter->isWarm());

        // Called getSetting, store is warm
        $this->assertNotNull($this->settingsRouter->getSetting($settingName));
        $this->assertTrue($this->settingsRouter->isWarm());
        // Clearing store
        $settingsStore->clear();
        $this->assertFalse($this->settingsRouter->isWarm());
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
        $settingsManager->save($setting);

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
