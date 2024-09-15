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
    public function __construct(
        private readonly SettingsManager $settingsManager,
        private readonly SettingsStore $settingsStore,
        private readonly EventManagerInterface $eventManager,
        private readonly array $treatAsDefaultProviders = [],
    ) {
    }

    /**
     * Retrieves setting data or default value with string typehint.
     */
    public function getString(string $settingName, string $defaultValue = ''): string
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with string typehint.
     *
     * @throws SettingNotFoundException
     */
    public function mustGetString(string $settingName): string
    {
        return $this->mustGet($settingName);
    }

    /**
     * Retrieves setting data or default value with bool typehint.
     */
    public function getBool(string $settingName, bool $defaultValue = false): bool
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with bool typehint.
     *
     * @throws SettingNotFoundException
     */
    public function mustGetBool(string $settingName): bool
    {
        return $this->mustGet($settingName);
    }

    /**
     * Retrieves setting data or default value with int typehint.
     */
    public function getInt(string $settingName, int $defaultValue = 0): int
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with int typehint.
     *
     * @throws SettingNotFoundException
     */
    public function mustGetInt(string $settingName): int
    {
        return $this->mustGet($settingName);
    }

    /**
     * Retrieves setting data or default value with float typehint.
     */
    public function getFloat(string $settingName, float $defaultValue = .0): float
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with float typehint.
     *
     * @throws SettingNotFoundException
     */
    public function mustGetFloat(string $settingName): float
    {
        return $this->mustGet($settingName);
    }

    /**
     * Retrieves setting data or default value with array typehint.
     */
    public function getArray(string $settingName, array $defaultValue = []): array
    {
        return $this->get($settingName, $defaultValue);
    }

    /**
     * Retrieves setting data with array typehint.
     *
     * @throws SettingNotFoundException
     */
    public function mustGetArray(string $settingName): array
    {
        return $this->mustGet($settingName);
    }

    /**
     * Returns data from setting or default value if setting not found.
     */
    public function get(string $settingName, mixed $defaultValue = false): mixed
    {
        $setting = $this->getSetting($settingName);

        return $setting instanceof SettingModel ? $setting->getData() : $defaultValue;
    }

    /**
     * Returns data from setting.
     *
     * @throws SettingNotFoundException
     */
    public function mustGet(string $settingName)
    {
        return $this->mustGetSetting($settingName)->getData();
    }

    /**
     * Returns setting model.
     */
    public function getSetting(string $settingName): ?SettingModel
    {
        return $this->doGetSetting($settingName);
    }

    /**
     * Returns setting model.
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
     * @return SettingModel[]
     *
     * @throws TaggedSettingsNotFoundException
     */
    public function mustGetSettingsByTag(string $tagName): array
    {
        $settings = $this->doGetSettingsByTag($tagName);

        if ($settings === []) {
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
            if ($this->settingsStore->getDomainNames() === []) {
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
        if ($this->settingsStore->getDomainNames(false) === [] || $force) {
            $this->settingsStore->setDomainNames(array_map(
                fn (DomainModel $domainModel) => $domainModel->getName(),
                $this->settingsManager->getDomains(null, true)
            ));
        }
    }

    /**
     * Warmup settings from providers.
     */
    private function warmupSettings(array $settingNames): void
    {
        if ($this->settingsStore->getDomainNames() !== []) {
            $this->settingsStore->setSettings(
                $this->settingsManager->getSettingsByName($this->settingsStore->getDomainNames(), $settingNames)
            );
        }
    }

    private function warmupSettingsByTag(string $tagName): void
    {
        if ($this->settingsStore->getDomainNames() !== []) {
            $this->settingsStore->setSettingsByTag(
                $tagName,
                $this->settingsManager->getSettingsByTag(array_values($this->settingsStore->getDomainNames()), $tagName)
            );
        }
    }
}
