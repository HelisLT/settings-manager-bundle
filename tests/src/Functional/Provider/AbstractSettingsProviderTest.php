<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type;

abstract class AbstractSettingsProviderTest extends AbstractReadableSettingsProviderTest
{
    public function testSave()
    {
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

    public function testSaveExistingSettingNameNewDomain()
    {
        $settings = $this->provider->getSettings(['sea']);
        $this->assertCount(1, $settings);
        /** @var SettingModel $setting */
        $setting = reset($settings);
        $this->assertInstanceOf(SettingModel::class, $setting);

        $clonedSetting = clone $setting;
        $clonedSetting->getDomain()->setName('salt_sea');
        $clonedSetting->getDomain()->setPriority(1);

        $this->provider->save($clonedSetting);
        $settings = $this->provider->getSettingsByName(['sea', 'salt_sea'], ['tuna']);
        $this->assertCount(2, $settings);

        $settingMap = $this->buildSettingHashmap(...$settings);
        $this->assertArrayHasKey('salt_sea', $settingMap);
        $this->assertArrayHasKey('tuna', $settingMap['salt_sea']);
        $this->assertArrayHasKey('sea', $settingMap);
        $this->assertArrayHasKey('tuna', $settingMap['sea']);
    }

    public function testSaveWithNewDomain()
    {
        $settings = $this->provider->getSettings(['water']);
        $this->assertCount(0, $settings);

        $newDomain = new DomainModel();
        $newDomain->setName('water');
        $newDomain->setEnabled(true);

        $newSetting = new SettingModel();
        $newSetting
            ->setName('whale')
            ->setType(Type::BOOL())
            ->setData(false)
            ->setDomain($newDomain);

        $this->assertTrue($this->provider->save($newSetting));

        $settings = $this->provider->getSettings(['water']);
        $this->assertCount(1, $settings);
        $map = $this->buildSettingHashmap(...$settings)['water'];

        $expected = ['whale'];
        sort($expected);
        $actual = array_keys($map);
        sort($actual);
        $this->assertEquals($expected, $actual);

        /** @var SettingModel $setting */
        $setting = $map['whale'];
        $this->assertEquals('whale', $setting->getName());
        $this->assertTrue($setting->getType()->equals(Type::BOOL()));
        $this->assertFalse($setting->getData());
        $this->assertEquals('water', $setting->getDomain()->getName());
        $this->assertTrue($setting->getDomain()->isEnabled());
    }

    public function testDelete()
    {
        $sortCallback = function (SettingModel $a, SettingModel $b) {
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
        $settings = $this->provider->getSettings(['sea']);
        $this->assertCount(1, $settings);
        $setting = array_shift($settings);
        $this->assertEquals('tuna', $setting->getName());

        $this->assertTrue($this->provider->delete($setting));

        $domains = $this->buildDomainMap(...$this->provider->getDomains());
        $this->assertArrayNotHasKey('sea', $domains);
    }

    public function testUpdateDomain()
    {
        $domains = $this->buildDomainMap(...$this->provider->getDomains());

        // assert before update

        $this->assertArrayHasKey('apples', $domains);
        $domainToUpdate = $domains['apples'];
        $settings = $this->provider->getSettings(['apples']);
        $this->assertCount(3, $settings);

        $this->assertFalse($domainToUpdate->isEnabled());
        $this->assertEquals(0, $domainToUpdate->getPriority());
        foreach ($settings as $setting) {
            $this->assertFalse($setting->getDomain()->isEnabled());
            $this->assertEquals(0, $setting->getDomain()->getPriority());
        }

        // update
        $domainToUpdate->setEnabled(true);
        $domainToUpdate->setPriority(11);
        $this->provider->updateDomain($domainToUpdate);

        // asserts after update
        $domains = $this->buildDomainMap(...$this->provider->getDomains());
        $this->assertArrayHasKey('apples', $domains);
        $domainToUpdate = $domains['apples'];
        $settings = $this->provider->getSettings(['apples']);
        $this->assertCount(3, $settings);

        $this->assertTrue($domainToUpdate->isEnabled());
        $this->assertEquals(11, $domainToUpdate->getPriority());
        foreach ($settings as $setting) {
            $this->assertTrue($setting->getDomain()->isEnabled());
            $this->assertEquals(11, $setting->getDomain()->getPriority());
        }
    }

    public function testDeleteDomain()
    {
        $domainNames = array_map(
            function (DomainModel $model) {
                return $model->getName();
            },
            $this->provider->getDomains()
        );

        $settings = $this->provider->getSettings($domainNames);
        $this->assertArrayHasKey('default', $this->buildSettingHashmap(...$settings));

        $this->provider->deleteDomain('default');

        // check if settings from deleted domain is missing
        $settings = $this->provider->getSettings($domainNames);
        $this->assertArrayNotHasKey('default', $this->buildSettingHashmap(...$settings));

        // check if domain is missing
        $this->assertArrayNotHasKey('default', $this->buildDomainMap(...$this->provider->getDomains()));
    }

    protected function setUp()
    {
        parent::setUp();

        foreach ($this->getSettingFixtures() as $i => $setting) {
            $this->assertTrue($this->provider->save($setting), sprintf('Setting %s failed to save', $i));
        }
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
