<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Event\GetSettingEvent;
use Helis\SettingsManagerBundle\Exception\SettingNotFoundException;
use Helis\SettingsManagerBundle\Exception\TaggedSettingsNotFoundException;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\SettingsManagerEvents;

class SettingsRouter
{
    private $settingsManager;
    private $settingsStore;
    private $eventManager;
    private $treatAsDefaultProviders;

    public function __construct(
        SettingsManager $settingsManager,
        SettingsStore $settingsStore,
        EventManagerInterface $eventManager,
        $treatAsDefaultProviders = []
    ) {
        $this->settingsManager = $settingsManager;
        $this->settingsStore = $settingsStore;
        $this->eventManager = $eventManager;
        $this->treatAsDefaultProviders = $treatAsDefaultProviders;
    }

    /**
     * Retrieves setting data or default value with string typehint.
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
     * Retrieves setting data with string typehint.
     *
     * @param string $settingName
     *
     * @return string
     *
     * @throws SettingNotFoundException
     */
    public function mustGetString(string $settingName): string
    {
        return $this->mustGet($settingName);
    }

    /**
     * Retrieves setting data or default value with bool typehint.
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
     * Retrieves setting data with bool typehint.
     *
     * @param string $settingName
     *
     * @return bool
     *
     * @throws SettingNotFoundException
     */
    public function mustGetBool(string $settingName): bool
    {
        return $this->mustGet($settingName);
    }

    /**
     * Retrieves setting data or default value with int typehint.
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
     * Retrieves setting data with int typehint.
     *
     * @param string $settingName
     *
     * @return int
     *
     * @throws SettingNotFoundException
     */
    public function mustGetInt(string $settingName): int
    {
        return $this->mustGet($settingName);
    }

    /**
     * Retrieves setting data or default value with float typehint.
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
     * Retrieves setting data with float typehint.
     *
     * @param string $settingName
     *
     * @return float
     *
     * @throws SettingNotFoundException
     */
    public function mustGetFloat(string $settingName): float
    {
        return $this->mustGet($settingName);
    }

    /**
     * Retrieves setting data or default value with array typehint.
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
     * Retrieves setting data with array typehint.
     *
     * @param string $settingName
     *
     * @return array
     *
     * @throws SettingNotFoundException
     */
    public function mustGetArray(string $settingName): array
    {
        return $this->mustGet($settingName);
    }

    /**
     * Returns data from setting or default value if setting not found.
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
     * Returns data from setting.
     *
     * @param string $settingName
     *
     * @return mixed
     *
     * @throws SettingNotFoundException
     */
    public function mustGet(string $settingName)
    {
        return $this->mustGetSetting($settingName)->getData();
    }

    /**
     * Returns setting model.
     *
     * @param string $settingName
     *
     * @return SettingModel|null
     */
    public function getSetting(string $settingName): ?SettingModel
    {
        return $this->doGetSetting($settingName);
    }

    /**
     * Returns setting model.
     *
     * @param string $settingName
     *
     * @return SettingModel
     *
     * @throws SettingNotFoundException
     */
    public function mustGetSetting(string $settingName): SettingModel
    {
        $setting = $this->doGetSetting($settingName);
        if (!$setting instanceof SettingModel
            || in_array($setting->getProviderName(), $this->treatAsDefaultProviders)
        ) {
            throw new SettingNotFoundException($settingName);
        }

        return $setting;
    }

    public function getSettingsByTag(string $tagName): array
    {
        return $this->doGetSettingsByTag($tagName);
    }

    /**
     * @param string $tagName
     *
     * @return SettingModel[]
     *
     * @throws TaggedSettingsNotFoundException
     */
    public function mustGetSettingsByTag(string $tagName): array
    {
        $settings = $this->doGetSettingsByTag($tagName);

        if (count($settings) == 0) {
            throw new TaggedSettingsNotFoundException($tagName);
        }

        return $settings;
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
        if ($this->isWarm()) {
            $settingNamesToWarmup = array_keys(array_filter($this->settingsStore->toArray()));
            $this->settingsStore->clear();
            $this->warmupDomains(true);
            $this->warmupSettings($settingNamesToWarmup);
        }
    }

    private function doGetSetting(string $settingName): ?SettingModel
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

    private function doGetSettingsByTag(string $tagName): array
    {
        if (!$this->settingsStore->hasSettingsByTag($tagName)) {
            $this->warmupDomains();
            $this->warmupSettingsByTag($tagName);
        }

        return $this->settingsStore->getSettingsByTag($tagName);
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

    private function warmupSettingsByTag(string $tagName): void
    {
        if (!empty($this->settingsStore->getDomainNames())) {
            $this->settingsStore->setSettingsByTag(
                $tagName,
                $this->settingsManager->getSettingsByTag(array_values($this->settingsStore->getDomainNames()), $tagName)
            );
        }
    }
}
