<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Monolog\Processor;

use Helis\SettingsManagerBundle\Settings\SettingsStore;

class SettingsProcessor
{
    private $settingsStore;
    private $providerNames;

    public function __construct(SettingsStore $settingsManager, array $providerNames)
    {
        $this->settingsStore = $settingsManager;
        $this->providerNames = $providerNames;
    }

    public function __invoke(array $record): array
    {
        foreach ($this->providerNames as $providerName) {
            foreach ($this->settingsStore->getByProvider($providerName) as $setting) {
                $record['extra']['settings'][] = [
                    'name' => $setting->getName(),
                    'value' => json_encode($setting->getDataValue()),
                    'provider' => $setting->getProviderName(),
                ];
            }
        }

        return $record;
    }
}
