<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Doctrine\Common\Collections\ArrayCollection;
use Helis\SettingsManagerBundle\Model\SettingModel;

class SettingsStore extends ArrayCollection
{
    /**
     * @var SettingModel[][]
     */
    private $settingsByProvider;

    /**
     * @var string[]
     */
    private $domainNames;

    public function __construct(array $elements = [])
    {
        parent::__construct($elements);

        $this->settingsByProvider = [];
        $this->domainNames = [];
    }

    /**
     * @param SettingModel[] $settings
     */
    public function setSettings(array $settings): void
    {
        foreach ($settings as $setting) {
            $this->addSetting($setting->getName(), $setting);
        }
    }

    public function addSetting(string $settingName, ?SettingModel $settingModel): void
    {
        if ($settingModel !== null) {
            if ($settingName !== $settingModel->getName()) {
                throw new \LogicException('SettingModel name does not match provided name');
            }

            $this->settingsByProvider[$settingModel->getProviderName()][$settingName] = $settingModel;
        }

        $this->set($settingName, $settingModel);
    }

    /**
     * @param string $providerName
     *
     * @return SettingModel[]
     */
    public function getByProvider(string $providerName): array
    {
        return $this->settingsByProvider[$providerName] ?? [];
    }

    public function isWarm(): bool
    {
        return $this->count() > 0;
    }

    public function getDomainNames(): array
    {
        return $this->domainNames;
    }

    public function setDomainNames(array $domainNames): void
    {
        $this->domainNames = $domainNames;
    }

    public function clear()
    {
        $this->settingsByProvider = [];
        $this->domainNames = [];

        return parent::clear();
    }
}
