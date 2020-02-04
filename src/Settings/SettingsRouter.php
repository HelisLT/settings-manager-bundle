<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Event\GetSettingEvent;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\SettingsManagerEvents;

class SettingsRouter
{
    private $settingsManager;
    private $settingsStore;
    private $eventManager;

    public function __construct(
        SettingsManager $settingsManager,
        SettingsStore $settingsStore,
        EventManagerInterface $eventManager
    ) {
        $this->settingsManager = $settingsManager;
        $this->settingsStore = $settingsStore;
        $this->eventManager = $eventManager;
    }

    /**
     * Retrieves setting data with string typehint.
     *
     * @param string $defaultValue
     */
    public function getString(string $settingName, $defaultValue = ''): string
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with bool typehint.
     *
     * @param bool $defaultValue
     */
    public function getBool(string $settingName, $defaultValue = false): bool
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with int typehint.
     *
     * @param int $defaultValue
     */
    public function getInt(string $settingName, $defaultValue = 0): int
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting with float typehint.
     *
     * @param float $defaultValue
     */
    public function getFloat(string $settingName, $defaultValue = .0): float
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with array typehint.
     *
     * @param array $defaultValue
     */
    public function getArray(string $settingName, $defaultValue = []): array
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Returns data from setting.
     *
     * @param mixed $defaultValue
     *
     * @return mixed
     */
    public function get(string $settingName, $defaultValue = false)
    {
        $setting = $this->getSetting($settingName);

        return $setting instanceof SettingModel ? $setting->getData() : $defaultValue;
    }

    /**
     * Returns setting model.
     *
     * @return SettingModel
     */
    public function getSetting(string $settingName): ?SettingModel
    {
        if ($this->settingsStore->containsKey($settingName)) {
            $setting = $this->settingsStore->get($settingName);
        } else {
            $this->warmupDomains();
            if (empty($this->settingsStore->getDomainNames())) {
                return null;
            }

            $this->warmupSettings([$settingName]);
            $setting = $this->settingsStore->get($settingName);

            if ($setting === null) {
                $this->settingsStore->addSetting($settingName, null);
            }
        }

        if ($setting instanceof SettingModel) {
            $this->eventManager->dispatch(SettingsManagerEvents::GET_SETTING, new GetSettingEvent($setting));
        }

        return $setting;
    }

    /**
     * Check if settings store is warmed up.
     */
    public function isWarm(): bool
    {
        return $this->settingsStore->isWarm();
    }

    /**
     * Warms up settings from manager.
     */
    public function warmup(): void
    {
        if ($this->settingsStore->count() > 0) {
            $settingNamesToWarmup = array_keys(array_filter($this->settingsStore->toArray()));
            $this->settingsStore->clear();
            $this->warmupDomains(true);
            $this->warmupSettings($settingNamesToWarmup);
        }
    }

    /**
     * Warm up domains from providers.
     */
    private function warmupDomains(bool $force = false): void
    {
        if (empty($this->settingsStore->getDomainNames(false)) || $force) {
            $this->settingsStore->setDomainNames(array_map(
                function (DomainModel $domainModel) {
                    return $domainModel->getName();
                },
                $this->settingsManager->getDomains(null, true)
            ));
        }
    }

    /**
     * Warmup settings from providers.
     */
    private function warmupSettings(array $settingNames): void
    {
        if (!empty($this->settingsStore->getDomainNames())) {
            $this->settingsStore->setSettings(
                $this->settingsManager->getSettingsByName($this->settingsStore->getDomainNames(), $settingNames)
            );
        }
    }
}
