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
        $normalizedSettingsByDomain = [];
        $normalizedDomains = [];

        foreach ($serializer->normalize($this->getSettingFixtures()) as $normalizedSetting) {
            $normalizedSettingsByDomain[$normalizedSetting['domain']['name']][$normalizedSetting['name']] = $normalizedSetting;
            $normalizedDomains[$normalizedSetting['domain']['name']] = $normalizedSetting['domain'];
        }

        return new LazyReadableSimpleSettingsProvider($serializer, $normalizedSettingsByDomain, $normalizedDomains);
    }
}
