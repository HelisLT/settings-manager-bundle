<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Settings;

use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Settings\ProvidersManager;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Helis\SettingsManagerBundle\Tests\Functional\Entity\Setting;
use Liip\FunctionalTestBundle\Test\WebTestCase;

class ProvidersManagerTest extends WebTestCase
{
    /**
     * @var ProvidersManager
     */
    private $settingsWarmUpService;

    /**
     * @var SettingsManager
     */
    private $settingsManager;

    /**
     * @before
     */
    public function loadServices(): void
    {
        $this->settingsManager = $this->getContainer()->get(SettingsManager::class);
        $this->settingsWarmUpService = new ProvidersManager($this->settingsManager);
    }

    public function warmUpDataProvider(): array
    {
        return [
            [
                'default',
                'foo',
                false,
                ['orm'],
            ],
            [
                'default',
                'baz',
                true,
                ['orm'],
            ],
            [
                'default',
                'tuna',
                'fish',
                ['orm'],
            ],
            [
                'default',
                'wth_yaml',
                [
                    'amazing' => ['foo', 'foo', 'foo', 'yee'],
                    'cool' => [
                        'yes' => ['yes', 'no'],
                    ],
                    'damn' => 5,
                ],
                ['orm'],
            ],
        ];
    }

    /**
     * @dataProvider warmUpDataProvider
     */
    public function testWarmUpProviders(string $domain, string $name, $value, array $targetProviders): void
    {
        $this->loadFixtures([]);
        $this->assertSettingDoesNotExists($name, $domain, $targetProviders);

        $this->settingsWarmUpService->warmUpProviders(
            SettingsProviderInterface::DEFAULT_PROVIDER,
            $targetProviders,
            [$domain]
        );

        $this->assertSettingExists($name, $value, $domain, $targetProviders);
    }

    private function assertSettingDoesNotExists(string $name, string $domain, array $targetProviders): void
    {
        foreach ($this->settingsManager->getProviders() as $providerName => $provider) {
            if (!in_array($providerName, $targetProviders, true)) {
                continue;
            }

            $result = $provider->getSettingsByName([$domain], [$name]);
            $this->assertEmpty($result);
        }
    }

    private function assertSettingExists(string $name, $value, string $domain, array $targetProviders): void
    {
        foreach ($this->settingsManager->getProviders() as $providerName => $provider) {
            if (!in_array($providerName, $targetProviders, true)) {
                continue;
            }
            $result = $provider->getSettingsByName([$domain], [$name]);
            $this->assertCount(1, $result);
            /** @var Setting $setting */
            $setting = reset($result);
            $this->assertEquals($value, $setting->getData());
        }
    }
}
