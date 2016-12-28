<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\Traits\WritableProviderTrait;

class SimpleSettingsProvider implements SettingsProviderInterface
{
    use WritableProviderTrait;

    protected $settings;

    /**
     * @param SettingModel[] $settings
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function getSettings(array $domainNames): array
    {
        $settings = [];

        foreach ($this->settings as $setting) {
            if (in_array($setting->getDomain()->getName(), $domainNames, true)) {
                $settings[] = $setting;
            }
        }

        return $settings;
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $out = [];

        foreach ($this->settings as $setting) {
            if (in_array($setting->getDomain()->getName(), $domainNames, true)
                && in_array($setting->getName(), $settingNames, true)
            ) {
                $out[] = $setting;
            }
        }

        return $out;
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        $out = [];

        foreach ($this->settings as $setting) {
            if (!isset($domains[$setting->getDomain()->getName()])
                && (!$onlyEnabled || ($onlyEnabled && $setting->getDomain()->isEnabled()))
            ) {
                $out[] = $setting->getDomain();
            }
        }

        return $out;
    }

    public function save(SettingModel $settingModel): bool
    {
        $updated = false;

        foreach ($this->settings as $key => $setting) {
            if ($setting->getName() === $settingModel->getName()
                && $setting->getDomain()->getName() === $settingModel->getDomain()->getName()
            ) {
                $this->settings[$key] = $settingModel;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $this->settings[] = $settingModel;
        }

        return true;
    }

    public function delete(SettingModel $settingModel): bool
    {
        foreach ($this->settings as $key => $setting) {
            if ($setting->getName() === $settingModel->getName()
                && $setting->getDomain()->getName() === $settingModel->getDomain()->getName()
            ) {
                unset($this->settings[$key]);

                return true;
            }
        }

        return false;
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        $updated = false;

        foreach ($this->settings as $k => &$setting) {
            if ($setting->getDomain()->getName() === $domainModel->getName()) {
                $setting->setDomain($domainModel);
                $updated = true;
            }
        }

        return $updated;
    }

    public function deleteDomain(string $domainName): bool
    {
        $deleted = false;

        foreach ($this->settings as $k => $setting) {
            if ($setting->getDomain()->getName() === $domainName) {
                unset($this->settings[$k]);
                $deleted = true;
            }
        }

        return $deleted;
    }
}
