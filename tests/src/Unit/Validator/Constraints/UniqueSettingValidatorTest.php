<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Validator\Constraints;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Helis\SettingsManagerBundle\Validator\Constraints\UniqueSetting;
use Helis\SettingsManagerBundle\Validator\Constraints\UniqueSettingValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class UniqueSettingValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * @var MockObject|SettingsManager
     */
    protected $settingsManager;

    public function testValid()
    {
        $this
            ->settingsManager
            ->expects($this->once())
            ->method('getSettingsByName')
            ->with(['dc'], ['batman'])
            ->willReturn([]);

        $setting = new SettingModel();
        $setting->setName('batman');
        $setting->setDomain((new DomainModel())->setName('dc'));

        $this->validator->validate($setting, new UniqueSetting());
        $this->assertNoViolation();
    }

    public function testInvalid()
    {
        $setting = new SettingModel();
        $setting->setName('batman');
        $setting->setDomain((new DomainModel())->setName('dc'));

        $this
            ->settingsManager
            ->expects($this->once())
            ->method('getSettingsByName')
            ->with(['dc'], ['batman'])
            ->willReturn([$setting]);

        $this->validator->validate($setting, new UniqueSetting());
        $this
            ->buildViolation('{{ domainName }} domain already has setting named {{ settingName }}')
            ->setParameter('{{ domainName }}', 'dc')
            ->setParameter('{{ settingName }}', 'batman')
            ->assertRaised();
    }

    protected function createValidator()
    {
        $this->settingsManager = $this->createMock(SettingsManager::class);

        return new UniqueSettingValidator($this->settingsManager);
    }
}
