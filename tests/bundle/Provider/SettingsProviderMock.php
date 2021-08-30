<?php

declare(strict_types=1);

namespace App\Provider;

use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Provider\Traits\ReadOnlyProviderTrait;

class SettingsProviderMock implements SettingsProviderInterface
{
    use ReadOnlyProviderTrait;

    const ANY = 'any';

    private $calls = [];

    public function on(string $method, $response, ...$arguments)
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                asort($argument);
            }
        }

        $key = $this->getCallKey($arguments, $method);

        $this->calls[$key] = $response;
    }

    public function clear()
    {
        $this->calls = [];
    }

    public function getSettings(array $domainNames): array
    {
        asort($domainNames);
        $key = $this->getCallKey([$domainNames], __FUNCTION__);

        if (array_key_exists($key, $this->calls)) {
            return $this->calls[$key];
        }

        $keyAnything = $this->getCallKey([[self::ANY]], __FUNCTION__);

        return $this->calls[$keyAnything] ?? [];
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        asort($domainNames);
        asort($settingNames);
        $key = $this->getCallKey([$domainNames, $settingNames], __FUNCTION__);

        if (array_key_exists($key, $this->calls)) {
            return $this->calls[$key];
        }

        $keyAnyDomains = $this->getCallKey([[self::ANY], $settingNames], __FUNCTION__);

        if (array_key_exists($keyAnyDomains, $this->calls)) {
            return $this->calls[$keyAnyDomains];
        }

        $keyAnything = $this->getCallKey([[self::ANY], [self::ANY]], __FUNCTION__);

        return $this->calls[$keyAnything] ?? [];
    }

    public function getSettingsByTag(array $domainNames, string $tagName): array
    {
        asort($domainNames);
        $key = $this->getCallKey([$domainNames, $tagName], __FUNCTION__);

        if (array_key_exists($key, $this->calls)) {
            return $this->calls[$key];
        }

        $keyAnything = $this->getCallKey([[self::ANY], $tagName], __FUNCTION__);

        return $this->calls[$keyAnything] ?? [];
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        $key = $this->getCallKey([$onlyEnabled], __FUNCTION__);

        return $this->calls[$key] ?? [];
    }

    private function getCallKey(array $arguments, string $methodName): string
    {
        return md5(json_encode($arguments)) . $methodName;
    }
}
