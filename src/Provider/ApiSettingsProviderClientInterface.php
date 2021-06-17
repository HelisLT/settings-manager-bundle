<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;

interface ApiSettingsProviderClientInterface
{
    /**
     * @param bool $onlyEnabled
     * @return DomainModel[]
     */
    public function getDomains(bool $onlyEnabled = false): array;

    /**
     * @param string[] $domainNames
     * @return SettingModel[]
     */
    public function getSettings(array $domainNames): array;

    /**
     * @param string[] $domainNames
     * @param string[] $settingNames
     * @return SettingModel[]
     */
    public function getSettingsByName(array $domainNames, array $settingNames): array;

    public function deleteDomain(string $domainName): bool;

    public function deleteSetting(SettingModel $setting): bool;

    public function saveSetting(SettingModel $setting): bool;

    public function updateDomain(DomainModel $domainModel): bool;

    public function getModificationTimeMicroseconds(): int;
}
