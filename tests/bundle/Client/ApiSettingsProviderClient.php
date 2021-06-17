<?php

declare(strict_types=1);

namespace App\Client;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\ApiSettingsProviderClientInterface;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;

class ApiSettingsProviderClient implements ApiSettingsProviderClientInterface
{
    private $client;

    /** @var int */
    private $modificationTime;

    public function __construct(SettingsProviderInterface $client)
    {
        $this->client = $client;
        $this->setModificationTime();
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        return $this->client->getDomains($onlyEnabled);
    }

    public function getSettings(array $domainNames): array
    {
        return $this->client->getSettings($domainNames);
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        return $this->client->getSettingsByName($domainNames, $settingNames);
    }

    public function deleteDomain(string $domainName): bool
    {
        $this->setModificationTime();

        return $this->client->deleteDomain($domainName);
    }

    public function deleteSetting(SettingModel $setting): bool
    {
        $this->setModificationTime();

        return $this->client->delete($setting);
    }

    public function saveSetting(SettingModel $setting): bool
    {
        $this->setModificationTime();

        return $this->client->save($setting);
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        $this->setModificationTime();

        return $this->client->updateDomain($domainModel);
    }

    public function getModificationTimeMicroseconds(): int
    {
        return $this->modificationTime;
    }

    private function setModificationTime(): void
    {
        $this->modificationTime = (int)round(microtime(true) * 1000000);
    }
}
