<?php

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Provider\Traits\ReadOnlyProviderTrait;

class DecoratingInMemorySettingsProvider implements SettingsProviderInterface
{
    use ReadOnlyProviderTrait;

    private $settingsProvider;
    private $cacheMap;

    public function __construct(SettingsProviderInterface $settingsProvider)
    {
        if (!$settingsProvider->isReadOnly()) {
            throw new \InvalidArgumentException('DecoratingInMemorySettingsProvider can only decorate read only provider');
        }

        $this->settingsProvider = $settingsProvider;
        $this->cacheMap = [];
    }

    public function getSettings(array $domainNames): array
    {
        $cacheKey = json_encode(['domainNames' => $domainNames]);
        if (!isset($this->cache[$cacheKey])) {
            $this->cacheMap[$cacheKey] = $this->settingsProvider->getSettings($domainNames);
        }

        return $this->cacheMap[$cacheKey];
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $cacheKey = json_encode(['domainNames' => $domainNames, 'settingNames' => $settingNames]);
        if (!isset($this->cache[$cacheKey])) {
            $this->cacheMap[$cacheKey] = $this->settingsProvider->getSettingsByName($domainNames, $settingNames);
        }

        return $this->cacheMap[$cacheKey];
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        $cacheKey = $onlyEnabled ? 'domains_only_enabled' : 'domains';
        if (!isset($this->cache[$cacheKey])) {
            $this->cacheMap[$cacheKey] = $this->settingsProvider->getDomains($onlyEnabled);
        }

        return $this->cacheMap[$cacheKey];
    }
}