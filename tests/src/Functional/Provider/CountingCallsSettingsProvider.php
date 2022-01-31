<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use Helis\SettingsManagerBundle\Provider\DecoratingRedisSettingsProvider;

class CountingCallsSettingsProvider extends DecoratingRedisSettingsProvider
{
    private $calls = [];

    public function getSettings(array $domainNames): array
    {
        if (!isset($this->calls[__FUNCTION__])) {
            $this->calls[__FUNCTION__] = 0;
        }

        ++$this->calls[__FUNCTION__];

        return parent::getSettings($domainNames);
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        if (!isset($this->calls[__FUNCTION__])) {
            $this->calls[__FUNCTION__] = 0;
        }

        ++$this->calls[__FUNCTION__];

        return parent::getSettingsByName($domainNames, $settingNames);
    }

    public function getSettingsByTag(array $domainNames, string $tagName): array
    {
        if (!isset($this->calls[__FUNCTION__])) {
            $this->calls[__FUNCTION__] = 0;
        }

        ++$this->calls[__FUNCTION__];

        return parent::getSettingsByTag($domainNames, $tagName);
    }

    public function getCalls(): array
    {
        return $this->calls;
    }
}
