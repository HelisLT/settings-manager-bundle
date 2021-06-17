<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;

class DecoratingApiSettingsProvider implements ModificationAwareSettingsProviderInterface
{
    protected $decoratingProvider;
    protected $apiClient;

    public function __construct(
        SettingsProviderInterface $decoratingProvider,
        ApiSettingsProviderClientInterface $apiClient
    ) {
        $this->decoratingProvider = $decoratingProvider;
        $this->apiClient = $apiClient;
    }

    public function isReadOnly(): bool
    {
        return $this->decoratingProvider->isReadOnly();
    }

    public function getSettings(array $domainNames): array
    {
        return $this->apiClient->getSettings($domainNames);
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        return $this->apiClient->getSettingsByName($domainNames, $settingNames);
    }

    public function save(SettingModel $settingModel): bool
    {
        $this->decoratingProvider->save($settingModel);

        return $this->apiClient->saveSetting($settingModel);
    }

    public function delete(SettingModel $settingModel): bool
    {
        $this->decoratingProvider->delete($settingModel);

        return $this->apiClient->deleteSetting($settingModel);
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        return $this->apiClient->getDomains($onlyEnabled);
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        $this->decoratingProvider->updateDomain($domainModel);

        return $this->apiClient->updateDomain($domainModel);
    }

    public function deleteDomain(string $domainName): bool
    {
        $this->decoratingProvider->deleteDomain($domainName);

        return $this->apiClient->deleteDomain($domainName);
    }

    public function setModificationTimeKey(string $modificationTimeKey): void
    {
    }

    public function getModificationTime(): int
    {
        return (int)round($this->apiClient->getModificationTimeMicroseconds() / 100);
    }
}
