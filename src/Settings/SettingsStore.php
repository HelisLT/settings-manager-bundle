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
     * @var SettingModel[][]
     */
    private $settingsByTag;

    /**
     * @var string[]
     */
    private $domainNames;

    /**
     * @var string[]
     */
    private $additionalDomainNames;

    public function __construct(array $elements = [])
    {
        parent::__construct($elements);

        $this->settingsByProvider = [];
        $this->domainNames = [];
        $this->additionalDomainNames = [];
        $this->settingsByTag = [];
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
     * @param SettingModel[] $settings
     */
    public function setSettingsByTag(string $tagName, array $settings): void
    {
        if (!isset($this->settingsByTag[$tagName])) {
            $this->settingsByTag[$tagName] = [];
        }

        foreach ($settings as $setting) {
            if ($setting !== null) {
                if (!$setting->hasTag($tagName)) {
                    throw new \LogicException('SettingModel does not have provided tag');
                }

                $this->settingsByTag[$tagName][$setting->getName()] = $setting;
            }
        }
    }

    public function hasSettingsByTag(string $tagName): bool
    {
        return isset($this->settingsByTag[$tagName]);
    }

    /**
     * @return SettingModel[]
     */
    public function getSettingsByTag(string $tagName): array
    {
        return $this->settingsByTag[$tagName] ?? [];
    }

    /**
     * @return SettingModel[]
     */
    public function getByProvider(string $providerName): array
    {
        return $this->settingsByProvider[$providerName] ?? [];
    }

    /**
     * Checks if settings store is not empty.
     */
    public function isWarm(): bool
    {
        return $this->count() > 0 || !empty($this->settingsByTag);
    }

    public function getDomainNames(bool $includeAdditional = true): array
    {
        if (empty($this->additionalDomainNames) || $includeAdditional === false) {
            return $this->domainNames;
        }

        return array_values(array_unique(array_merge($this->domainNames, $this->additionalDomainNames)));
    }

    public function setDomainNames(array $domainNames): void
    {
        $this->domainNames = $domainNames;
    }

    public function setAdditionalDomainNames(array $additionalDomainNames): void
    {
        $this->additionalDomainNames = $additionalDomainNames;
    }

    public function addAdditionalDomainName(string $domainName): void
    {
        if (!in_array($domainName, $this->additionalDomainNames)) {
            $this->additionalDomainNames[] = $domainName;
        }
    }

    public function clear()
    {
        $this->settingsByProvider = [];
        $this->domainNames = [];
        $this->settingsByTag = [];

        parent::clear();
    }
}
