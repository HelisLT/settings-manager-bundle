<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Predis\Client;
use Predis\CommunicationException;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type;
use Helis\SettingsManagerBundle\Provider\DecoratingPredisSettingsProvider;
use Helis\SettingsManagerBundle\Tests\Functional\DataFixtures\ORM\LoadSettingsData;
use Helis\SettingsManagerBundle\Tests\Functional\Entity\Setting;
use Helis\SettingsManagerBundle\Tests\Functional\Entity\Tag;
use Symfony\Component\Stopwatch\Stopwatch;

class DecoratingPredisSettingsProviderTest extends WebTestCase
{
    protected $provider;
    protected $redis;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->redis = new Client(
            ['host' => getenv('REDIS_HOST'), 'port' => getenv('REDIS_PORT')],
            ['parameters' => ['database' => 0]]
        );

        try {
            $this->redis->ping();
        } catch (CommunicationException $e) {
            $this->markTestSkipped('Running redis server required');
        }

        $container = $this->getContainer();

        $this->provider = new DecoratingPredisSettingsProvider(
            new DoctrineOrmSettingsProvider(
                $container->get('doctrine.orm.default_entity_manager'),
                Setting::class,
                Tag::class
            ),
            $this->redis,
            $container->get('test.settings_manager.serializer')
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        parent::tearDown();

        $this->redis->flushdb();
    }

    public function testGetSettings()
    {
        $this->loadFixtures([LoadSettingsData::class]);
        $domains = $this->provider->getDomains();
        $domainNames = array_map(function (DomainModel $model) {
            return $model->getName();
        }, $domains);

        [$freshSettings, $noCacheDuration] = $this->profileExecution(function () use ($domainNames) {
            return $this->provider->getSettings($domainNames);
        });

        [$cachedSettings, $cachedDuration] = $this->profileExecution(function () use ($domainNames) {
            return $this->provider->getSettings($domainNames);
        });

        $f = function (array $settings) {
            $map =  array_map(function (SettingModel $model) {
                return [$model->getName(), $model->getDomain()->getName()];
            }, $settings);

            sort($map);
            return $map;
        };

        $this->assertTrue($noCacheDuration > $cachedDuration);
        $this->assertEquals($f($freshSettings), $f($cachedSettings));
    }

    public function testGetSettingsByName()
    {
        $this->loadFixtures([LoadSettingsData::class]);

        [$freshSettings, $noCacheDuration] = $this->profileExecution(function () {
            return $this->provider->getSettingsByName(
                [
                    LoadSettingsData::DOMAIN_NAME_1,
                    LoadSettingsData::DOMAIN_NAME_2,
                ],
                [
                    LoadSettingsData::SETTING_NAME_1,
                    LoadSettingsData::SETTING_NAME_2,
                ]
            );
        });

        [$cachedSettings, $cachedDuration] = $this->profileExecution(function () {
            return $this->provider->getSettingsByName(
                [
                    LoadSettingsData::DOMAIN_NAME_1,
                    LoadSettingsData::DOMAIN_NAME_2,
                ],
                [
                    LoadSettingsData::SETTING_NAME_1,
                    LoadSettingsData::SETTING_NAME_2,
                    'missing',
                ]
            );
        });

        $f = function (array $settings) {
            $map =  array_map(function (SettingModel $model) {
                return [$model->getName(), $model->getDomain()->getName()];
            }, $settings);

            sort($map);
            return $map;
        };

        $this->assertTrue($noCacheDuration > $cachedDuration);
        $this->assertEquals($f($freshSettings), $f($cachedSettings));
    }

    public function testSave()
    {
        $this->loadFixtures([LoadSettingsData::class]);

        $settings = $this->provider->getSettings(['sea']);
        $this->assertCount(1, $settings);
        $this->assertEquals('tuna', reset($settings)->getName());

        $newSetting = new SettingModel();
        $newSetting
            ->setName('whale')
            ->setType(Type::BOOL())
            ->setData(false)
            ->setDomain(reset($settings)->getDomain());

        $this->assertTrue($this->provider->save($newSetting));

        $settings = $this->provider->getSettings(['sea']);
        $this->assertCount(2, $settings);
        $map = $this->buildSettingHashmap(...$settings)['sea'];

        $expected = ['tuna', 'whale'];
        sort($expected);
        $actual = array_keys($map);
        sort($actual);
        $this->assertEquals($expected, $actual);

        $setting = $map['whale'];
        $this->assertEquals('whale', $setting->getName());
        $this->assertTrue($setting->getType()->equals(Type::BOOL()));
        $this->assertFalse($setting->getData());
        $this->assertEquals('sea', $setting->getDomain()->getName());
    }

    public function testDelete()
    {
        $this->loadFixtures([LoadSettingsData::class]);

        $settings = $this->provider->getSettings(['default']);
        $this->assertCount(2, $settings);
        $settingToDelete = end($settings);
        $this->assertEquals('bazinga', $settingToDelete->getName());

        $this->assertTrue($this->provider->delete($settingToDelete));

        $domains = $this->buildDomainMap(...$this->provider->getDomains());
        $this->assertArrayHasKey('default', $domains);
        $settings = $this->provider->getSettings(['default']);
        $this->assertCount(1, $settings);

        /** @var SettingModel $setting */
        $setting = array_shift($settings);
        $this->assertEquals('foo', $setting->getName());
    }

    public function testDeleteLastSettingFromDomain()
    {
        $this->loadFixtures([LoadSettingsData::class]);

        $settings = $this->provider->getSettings(['sea']);
        $this->assertCount(1, $settings);
        $setting = array_shift($settings);
        $this->assertEquals('tuna', $setting->getName());

        $this->assertTrue($this->provider->delete($setting));

        $domains = $this->buildDomainMap(...$this->provider->getDomains());
        $this->assertArrayNotHasKey('sea', $domains);
    }

    public function testGetDomains()
    {
        $this->loadFixtures([LoadSettingsData::class]);

        [$freshDomains, $noCacheDuration] = $this->profileExecution(function () {
            return $this->provider->getDomains();
        });
        [$cachedDomains, $cachedDuration] = $this->profileExecution(function () {
            return $this->provider->getDomains();
        });

        $f = function (array $domainModels) {
            $domains =  array_map(function (DomainModel $model) {
                return $model->getName();
            }, $domainModels);

            sort($domains);
            return $domains;
        };

        $this->assertEquals($f($freshDomains), $f($cachedDomains));
        $this->assertTrue($noCacheDuration > $cachedDuration);
    }

    public function testUpdateDomain()
    {
        $this->loadFixtures([LoadSettingsData::class]);
        $domains = $this->buildDomainMap(...$this->provider->getDomains());

        // assert before update

        $this->assertArrayHasKey('sea', $domains);
        $domainToUpdate = $domains['sea'];
        $settings = $this->provider->getSettings(['sea']);
        $this->assertCount(1, $settings);
        /** @var SettingModel $setting */
        $setting = array_shift($settings);
        $this->assertFalse($setting->getDomain()->isEnabled());
        $this->assertEquals(0, $setting->getDomain()->getPriority());
        $this->assertFalse($domainToUpdate->isEnabled());
        $this->assertEquals(0, $domainToUpdate->getPriority());

        $domainToUpdate->setEnabled(true);
        $domainToUpdate->setPriority(11);
        $this->provider->updateDomain($domainToUpdate);

        // assert after update

        $domains = $this->buildDomainMap(...$this->provider->getDomains());

        $this->assertArrayHasKey('sea', $domains);
        $domainToUpdate = $domains['sea'];
        $settings = $this->provider->getSettings(['sea']);
        $this->assertCount(1, $settings);
        /** @var SettingModel $setting */
        $setting = array_shift($settings);
        $this->assertTrue($setting->getDomain()->isEnabled());
        $this->assertEquals(11, $setting->getDomain()->getPriority());
        $this->assertTrue($domainToUpdate->isEnabled());
        $this->assertEquals(11, $domainToUpdate->getPriority());
    }

    public function testDeleteDomain()
    {
        $this->loadFixtures([LoadSettingsData::class]);
        $domains = $this->provider->getDomains();
        $domainNames = array_map(function (DomainModel $model) {
            return $model->getName();
        }, $domains);

        $settings = $this->provider->getSettings($domainNames);
        $this->assertArrayHasKey('default', $this->buildSettingHashmap(...$settings));

        $this->provider->deleteDomain('default');

        // check if settings from deleted domain is missing
        $settings = $this->provider->getSettings($domainNames);
        $this->assertArrayNotHasKey('default', $this->buildSettingHashmap(...$settings));

        // check if domain is missing
        $this->assertArrayNotHasKey('default', $this->buildDomainMap(...$this->provider->getDomains()));
    }
    
    /**
     * @param callable $callable
     * @return array
     */
    private function profileExecution(callable $callable): array
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('profile');
        $out = $callable();
        $event = $stopwatch->stop('profile');

        return [$out, $event->getDuration()];
    }

    /**
     * @param SettingModel[] ...$models
     *
     * @return SettingModel[][]
     */
    private function buildSettingHashmap(SettingModel ...$models): array
    {
        $map = [];
        foreach ($models as $model) {
            $map[$model->getDomain()->getName()][$model->getName()] = $model;
        }

        ksort($map);

        return $map;
    }

    /**
     * @param DomainModel[] ...$models
     *
     * @return DomainModel[]
     */
    private function buildDomainMap(DomainModel ...$models): array
    {
        $map = [];
        foreach ($models as $model) {
            $map[$model->getName()] = $model;
        }

        ksort($map);

        return $map;
    }
}
