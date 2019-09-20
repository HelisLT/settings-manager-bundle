<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use Helis\SettingsManagerBundle\Provider\LazyReadableSimpleSettingsProvider;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;

class LazyReadableSimpleSettingsProviderTest extends AbstractReadableSettingsProviderTest
{
    protected function createProvider(): SettingsProviderInterface
    {
        $serializer = $this->getContainer()->get('settings_manager.serializer');
        $normDomains = [];
        $normSettings = [];
        $settingsKeyMap = [];
        $domainsKeyMap = [];

        foreach ($serializer->normalize($this->getSettingFixtures()) as $setting) {
            $domainName = $setting['domain']['name'];
            $settingName = $setting['name'];
            $settingKey = $domainName . '_' . $settingName;

            $normDomains[$domainName] = $setting['domain'];
            $normSettings[$settingKey] = $setting;
            $settingsKeyMap[$settingName][] = $domainsKeyMap[$domainName][] = $settingKey;
        }

        return new LazyReadableSimpleSettingsProvider($serializer, $normDomains, $normSettings, $settingsKeyMap,
            $domainsKeyMap);
    }
}
