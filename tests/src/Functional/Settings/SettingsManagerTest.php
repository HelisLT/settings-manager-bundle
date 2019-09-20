<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Settings;

use App\DataFixtures\ORM\LoadSettingsData;
use App\Entity\Setting;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Liip\FunctionalTestBundle\Test\WebTestCase;

class SettingsManagerTest extends WebTestCase
{
    /**
     * @var SettingsManager
     */
    private $settingsManager;

    public function testGetProviders()
    {
        $this->loadFixtures([]);
        $providers = $this->settingsManager->getProviders();

        $this->assertEquals(['config', 'orm', 'cookie'], array_keys($providers));
        $this->assertInstanceOf(SettingsProviderInterface::class, reset($providers));
    }

    public function testGetDomains()
    {
        $this->loadFixtures([LoadSettingsData::class]);
        $domains = $this->settingsManager->getDomains();

        $this->assertEquals(['default', 'omg', 'sea'], array_keys($domains));
        $this->assertInstanceOf(DomainModel::class, reset($domains));

        // assert if keys match domain names
        foreach ($domains as $name => $domain) {
            $this->assertEquals($name, $domain->getName());
        }
    }

    public function testGetEnabledDomains()
    {
        $this->loadFixtures([LoadSettingsData::class]);
        $domains = $this->settingsManager->getDomains(null, true);

        $this->assertEquals(['default'], array_keys($domains));

        // assert all domains are enabled
        foreach ($domains as $domain) {
            $this->assertTrue($domain->isEnabled());
        }
    }

    public function getSettingsByDomainDataProvider()
    {
        yield [LoadSettingsData::DOMAIN_NAME_1, 6, ['foo', 'baz', 'tuna', 'wth_yaml', 'choice', 'bazinga']];

        yield [LoadSettingsData::DOMAIN_NAME_2, 1, ['tuna']];
    }

    /**
     * @dataProvider getSettingsByDomainDataProvider
     */
    public function testGetSettingsByDomain(
        string $domainName,
        int $expectedSettingCount,
        array $expectedSettingKeys
    ) {
        $this->loadFixtures([LoadSettingsData::class]);
        $settings = $this->settingsManager->getSettingsByDomain([$domainName]);
        $this->assertCount($expectedSettingCount, $settings);
        $this->assertEquals($expectedSettingKeys, array_keys($settings));

        // assert domain name
        foreach ($settings as $setting) {
            $this->assertEquals($domainName, $setting->getDomain()->getName());
        }
    }

    public function testSave()
    {
        $this->loadFixtures([LoadSettingsData::class]);
        $settings = $this->settingsManager->getSettingsByName(['default'], ['baz']);
        $setting = array_shift($settings);

        // setting from config, default domain
        $testSaveDomain = (new DomainModel())->setName('test_save');
        $setting->setDomain($testSaveDomain);

        $this->assertTrue($this->settingsManager->save($setting));

        // assert from orm
        $doctrine = $this->getContainer()->get('doctrine');
        $settingEntity = $doctrine
            ->getRepository(Setting::class)
            ->findOneBy(['domain.name' => 'test_save', 'name' => 'baz']);
        $this->assertNotNull($settingEntity);

        // assert from setting manager
        $settings = $this->settingsManager->getSettingsByDomain([$testSaveDomain->getName()]);
        $this->assertCount(1, $settings);
        $this->assertArrayHasKey('baz', $settings);
        $this->assertEquals('baz', $settings['baz']->getName());
        $this->assertEquals('test_save', $settings['baz']->getDomain()->getName());
    }

    public function testDelete()
    {
        $this->loadFixtures([LoadSettingsData::class]);

        $settings = $this->settingsManager->getSettingsByName(
            ['default', LoadSettingsData::DOMAIN_NAME_2],
            [LoadSettingsData::SETTING_NAME_2]
        );
        $domains = $this->settingsManager->getDomains();
        $setting = array_shift($settings);
        $this->assertEquals('orm', $setting->getProviderName());
        $this->assertArrayHasKey(LoadSettingsData::DOMAIN_NAME_2, $domains);
        $this->settingsManager->delete($setting);

        $settings = $this->settingsManager->getSettingsByName(
            ['default', LoadSettingsData::DOMAIN_NAME_2],
            [LoadSettingsData::SETTING_NAME_2]
        );
        $domains = $this->settingsManager->getDomains();
        $setting = array_shift($settings);
        $this->assertNotEquals('orm', $setting->getProviderName());
        $this->assertEquals('config', $setting->getProviderName());
        $this->assertArrayNotHasKey(LoadSettingsData::DOMAIN_NAME_2, $domains);
    }

    public function testCopyDomainToProvider()
    {
        $this->loadFixtures([]);
        $this->settingsManager->copyDomainToProvider('omg', 'orm');

        $settings = $this->settingsManager->getSettingsByDomain(['omg']);
        $setting = $settings['fix'];

        $this->assertEquals('orm', $setting->getProviderName());
    }

    public function testUpdateDomain()
    {
        $this->loadFixtures([LoadSettingsData::class]);
        $settings = $this->settingsManager->getSettingsByName(
            [LoadSettingsData::DOMAIN_NAME_2],
            [LoadSettingsData::SETTING_NAME_2]
        );
        $setting = array_shift($settings);
        $domain = $setting->getDomain();
        $this->assertFalse($domain->isEnabled());
        $domain->setEnabled(true);

        $this->settingsManager->updateDomain($domain);
        /** @var SettingModel $setting */
        $setting = $this
            ->getContainer()
            ->get('doctrine')
            ->getRepository(Setting::class)
            ->findOneBy(['domain.name' => $domain->getName()]);

        $this->assertTrue($setting->getDomain()->isEnabled());
    }

    public function testDeleteDomain()
    {
        $this->loadFixtures([LoadSettingsData::class]);
        $domains = $this->settingsManager->getDomains();
        $this->assertArrayHasKey('sea', $domains);

        $this->settingsManager->deleteDomain('sea');

        $domains = $this->settingsManager->getDomains();
        $this->assertArrayNotHasKey('sea', $domains);
    }

    public function testGetSettingsByNameWithHigherPriorityDomain()
    {
        $this->loadFixtures([LoadSettingsData::class]);

        $settings = $this->settingsManager->getSettingsByName(['default', 'vip'], ['bazinga']);
        /** @var Setting $setting */
        $setting = reset($settings);
        $this->assertInstanceOf(Setting::class, $setting);
        $this->assertEquals('orm', $setting->getProviderName());
        $this->assertFalse($setting->getData());

        $setting = clone $setting;
        $setting->getDomain()->setName('vip');
        $setting->getDomain()->setPriority(1);
        $setting->setData(true);
        $this->settingsManager->save($setting);

        // assert with new domain

        $settings = $this->settingsManager->getSettingsByName(['default', 'vip'], ['bazinga']);
        /** @var Setting $setting */
        $setting = reset($settings);
        $this->assertInstanceOf(Setting::class, $setting);
        $this->assertEquals('orm', $setting->getProviderName());
        $this->assertEquals('vip', $setting->getDomain()->getName());
        $this->assertEquals(1, $setting->getDomain()->getPriority());
        $this->assertTrue($setting->getData());

        // assert without new domain

        $settings = $this->settingsManager->getSettingsByName(['default'], ['bazinga']);
        /** @var Setting $setting */
        $setting = reset($settings);
        $this->assertInstanceOf(Setting::class, $setting);
        $this->assertEquals('orm', $setting->getProviderName());
        $this->assertEquals('default', $setting->getDomain()->getName());
        $this->assertEquals(0, $setting->getDomain()->getPriority());
        $this->assertFalse($setting->getData());
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->settingsManager = $this->getContainer()->get(SettingsManager::class);
    }
}
