<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider\Traits;

use Helis\SettingsManagerBundle\Exception\ReadOnlyProviderException;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;

trait ReadOnlyProviderTrait
{
    public function isReadOnly(): bool
    {
        return true;
    }

    public function save(SettingModel $settingModel): bool
    {
        throw new ReadOnlyProviderException(get_class($this));
    }

    public function delete(SettingModel $settingModel): bool
    {
        throw new ReadOnlyProviderException(get_class($this));
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        throw new ReadOnlyProviderException(get_class($this));
    }

    public function deleteDomain(string $domainName): bool
    {
        throw new ReadOnlyProviderException(get_class($this));
    }
}
