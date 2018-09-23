<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use App\Entity\Setting;
use App\Entity\Tag;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Liip\FunctionalTestBundle\Test\WebTestCase;

abstract class AbstractSettingsProviderTest extends WebTestCase
{
    /**
     * @var SettingsProviderInterface
     */
    protected $provider;

    protected function setUp()
    {
        parent::setUp();

        $this->provider = $this->createProvider();
    }

    abstract protected function createProvider(): SettingsProviderInterface;

    public function testGetSettings()
    {
        $this->loadSettings();

        $domains = $this->provider->getDomains();
        $domainNames = array_map(
            function (DomainModel $model) {
                return $model->getName();
            },
            $domains
        );
        $settings = $this->provider->getSettings($domainNames);


        $f = function (array $settings) {
            $map =  array_map(function (SettingModel $model) {
                return [
                    $model->getName(),
                    $model->getDomain()->getName(),
                    $model->getType()->getValue(),
                    $model->getData(),
                ];
            }, $settings);

            sort($map);
            return $map;
        };

        $this->assertEquals(
            [
                ['bazinga', 'default', 'bool', false],
                ['foo', 'default', 'bool', true],
                ['tuna', 'sea', 'string', 'fishing'],
            ],
            $f($settings)
        );
    }

    public function testGetSettingsByName()
    {
        $this->loadSettings();
        $settings = $this->provider->getSettingsByName(
            ['default', 'sea'],
            ['foo', 'tuna']
        );

        $f = function (array $settings) {
            $map =  array_map(function (SettingModel $model) {
                return [
                    $model->getName(),
                    $model->getDomain()->getName(),
                    $model->getType()->getValue(),
                    $model->getData(),
                ];
            }, $settings);

            sort($map);
            return $map;
        };

        $this->assertEquals(
            [
                ['foo', 'default', 'bool', true],
                ['tuna', 'sea', 'string', 'fishing'],
            ],
            $f($settings)
        );
    }

    public function testSave()
    {
        $this->loadSettings();

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

        /** @var SettingModel $setting */
        $setting = $map['whale'];
        $this->assertEquals('whale', $setting->getName());
        $this->assertTrue($setting->getType()->equals(Type::BOOL()));
        $this->assertFalse($setting->getData());
        $this->assertEquals('sea', $setting->getDomain()->getName());
    }

    public function testDelete()
    {
        $this->loadSettings();

        $sortCallback = function (SettingModel $a, SettingModel$b) {
            $v = $a->getName() <=> $b->getName();
            return $v !== 0 ? $v * -1 : $v;
        };

        $settings = $this->provider->getSettings(['default']);
        usort($settings, $sortCallback);

        $this->assertCount(2, $settings);
        $settingToDelete = end($settings);
        $this->assertEquals('bazinga', $settingToDelete->getName());

        $this->assertTrue($this->provider->delete($settingToDelete));

        $domains = $this->buildDomainMap(...$this->provider->getDomains());
        $this->assertArrayHasKey('default', $domains);
        $settings = $this->provider->getSettings(['default']);
        usort($settings, $sortCallback);
        $this->assertCount(1, $settings);

        /** @var SettingModel $setting */
        $setting = array_shift($settings);
        $this->assertEquals('foo', $setting->getName());
    }

    public function testDeleteLastSettingFromDomain()
    {
        $this->loadSettings();

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
        $this->loadSettings();
        $domains = $this->provider->getDomains();

        $f = function (array $domainModels) {
            $domains =  array_map(function (DomainModel $model) {
                return $model->getName();
            }, $domainModels);

            sort($domains);
            return $domains;
        };

        $this->assertEquals(['default', 'sea'], $f($domains));
    }

    public function testUpdateDomain()
    {
        $this->loadSettings();

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
        $this->loadSettings();

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

    private function loadSettings()
    {
        $tag1 = new Tag();
        $tag1->setName('fixture');

        $domain1 = new DomainModel();
        $domain1->setName('default');

        $domain2 = new DomainModel();
        $domain2->setName('sea');

        $setting0 = new Setting();
        $setting0
            ->setName('bazinga')
            ->setDescription('fixture bool baz setting')
            ->setType(Type::BOOL())
            ->setDomain($domain1)
            ->setData(false)
            ->addTag($tag1);

        $setting1 = new Setting();
        $setting1
            ->setName('foo')
            ->setDescription('fixture bool foo setting')
            ->setType(Type::BOOL())
            ->setDomain($domain1)
            ->setData(true)
            ->addTag($tag1);

        $setting2 = new Setting();
        $setting2
            ->setName('tuna')
            ->setDescription('fixture bool tuna setting')
            ->setType(Type::STRING())
            ->setDomain($domain2)
            ->setData('fishing')
            ->addTag($tag1);

        $this->assertTrue($this->provider->save($setting0), 'Setting 1 failed to save');
        $this->assertTrue($this->provider->save($setting1), 'Setting 2 failed to save');
        $this->assertTrue($this->provider->save($setting2), 'Setting 3 failed to save');
    }

    private function buildSettingHashmap(SettingModel ...$models): array
    {
        $map = [];
        foreach ($models as $model) {
            $map[$model->getDomain()->getName()][$model->getName()] = $model;
        }

        ksort($map);

        return $map;
    }

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
