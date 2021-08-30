<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Test\Provider;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Provider\Traits\ReadOnlyProviderTrait;
use Helis\SettingsManagerBundle\Provider\Traits\TagFilteringTrait;

final class SettingsProviderMock implements SettingsProviderInterface
{
    private static $settings = [];

    use ReadOnlyProviderTrait;
    use TagFilteringTrait;

    public static function addSetting(SettingModel $setting)
    {
        foreach (SettingsProviderMock::$settings as $key => $existingSetting) {
            if ($existingSetting->getDomain()->getName() === $setting->getDomain()->getName()
                && $existingSetting->getName() === $setting->getName()
            ) {
                SettingsProviderMock::$settings[$key] = $setting;

                return;
            }
        }

        SettingsProviderMock::$settings[] = $setting;
    }

    /**
     * @param SettingModel[] $settings
     */
    public static function setSettings(array $settings)
    {
        SettingsProviderMock::$settings = $settings;
    }

    public static function clear()
    {
        SettingsProviderMock::$settings = [];
    }

    public function getSettings(array $domainNames): array
    {
        $settings = [];

        foreach (SettingsProviderMock::$settings as $setting) {
            if (in_array($setting->getDomain()->getName(), $domainNames, true)) {
                $settings[] = $setting;
            }
        }

        return $settings;
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $out = [];

        foreach (SettingsProviderMock::$settings as $setting) {
            if (in_array($setting->getDomain()->getName(), $domainNames, true)
                && in_array($setting->getName(), $settingNames, true)
            ) {
                $out[] = $setting;
            }
        }

        return $out;
    }

    public function getSettingsByTag(array $domainNames, string $tagName): array
    {
        return $this->filterSettingsByTag($this->getSettings($domainNames), $tagName);
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        $out = [];

        foreach (SettingsProviderMock::$settings as $setting) {
            if (!isset($out[$setting->getDomain()->getName()]) && (!$onlyEnabled || $setting->getDomain()->isEnabled())) {
                $out[$setting->getDomain()->getName()] = $setting->getDomain();
            }
        }

        return array_values($out);
    }
}

