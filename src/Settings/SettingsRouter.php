<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Event\GetSettingEvent;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\SettingsManagerEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SettingsRouter
{
    private $settingsManager;
    private $settingsStore;
    private $eventDispatcher;

    public function __construct(
        SettingsManager $settingsManager,
        SettingsStore $settingsStore,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->settingsManager = $settingsManager;
        $this->settingsStore = $settingsStore;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Retrieves setting data with string typehint.
     *
     * @param string $settingName
     * @param string $defaultValue
     *
     * @return string
     */
    public function getString(string $settingName, $defaultValue = ''): string
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with bool typehint.
     *
     * @param string $settingName
     * @param bool   $defaultValue
     *
     * @return bool
     */
    public function getBool(string $settingName, $defaultValue = false): bool
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with int typehint.
     *
     * @param string $settingName
     * @param int    $defaultValue
     *
     * @return int
     */
    public function getInt(string $settingName, $defaultValue = 0): int
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting with float typehint.
     *
     * @param string $settingName
     * @param float  $defaultValue
     *
     * @return float
     */
    public function getFloat(string $settingName, $defaultValue = .0): float
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with array typehint.
     *
     * @param string $settingName
     * @param array  $defaultValue
     *
     * @return array
     */
    public function getArray(string $settingName, $defaultValue = []): array
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Returns data from setting.
     *
     * @param string $settingName
     * @param mixed  $defaultValue
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
     * @param string $settingName
     *
     * @return SettingModel
     */
    public function getSetting(string $settingName): ?SettingModel
    {
        if ($this->settingsStore->containsKey($settingName)) {
            $setting = $this->settingsStore->get($settingName);
        } else {
            if (count($this->settingsStore->getDomains()) === 0) {
                $domains = $this->settingsManager->getEnabledDomains();
                if (count($domains) === 0) {
                    return null;
                }
                $this->settingsStore->setDomains($domains);
            }

            $settings = $this
                ->settingsManager
                ->getSettingsByName($this->settingsStore->getDomainNames(), [$settingName]);
            $setting = array_shift($settings);
            $this->settingsStore->addSetting($settingName, $setting);
        }

        if ($setting instanceof SettingModel) {
            $this->eventDispatcher->dispatch(SettingsManagerEvents::GET_SETTING, new GetSettingEvent($setting));
            $this->eventDispatcher->dispatch(
                SettingsManagerEvents::GET_SETTING . '.' . strtolower($setting->getName()),
                new GetSettingEvent($setting)
            );
        }

        return $setting;
    }

    /**
     * Check if settings store is warmed up.
     *
     * @return bool
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
            $namesToWarmup = array_keys(array_filter($this->settingsStore->toArray()));

            $this->settingsStore->clear();
            $this->settingsStore->setDomains($this->settingsManager->getEnabledDomains());
            $this->settingsStore->setSettings(
                $this->settingsManager->getSettingsByName($this->settingsStore->getDomainNames(), $namesToWarmup)
            );
        }
    }
}
