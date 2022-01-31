<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider\Traits;

use Helis\SettingsManagerBundle\Model\SettingModel;

/**
 * TODO: implement provider specific filtering by tag name on each provider.
 *       This trait was introduced as a temporary solution to nt enforce all
 *       providers rewrite.
 */
trait TagFilteringTrait
{
    /**
     * @param array|SettingModel[] $settings
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
