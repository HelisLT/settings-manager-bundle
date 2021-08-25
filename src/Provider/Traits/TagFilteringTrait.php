<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider\Traits;

use Helis\SettingsManagerBundle\Model\SettingModel;

trait TagFilteringTrait
{
    /**
     * @param array|SettingModel[] $settings
     * @param string               $tagName
     *
     * @return array|SettingModel[]
     */
    protected function filterSettingsByTag(array $settings, string $tagName): array
    {
        $taggedSettings = [];

        foreach ($settings as $setting) {
            if ($setting->hasTag($tagName)) {
                $taggedSettings[] = $setting;
            }
        }

        return $taggedSettings;
    }
}
