<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Settings;

use App\AbstractWebTestCase;
use App\Entity\Setting;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Settings\ProvidersManager;
use Helis\SettingsManagerBundle\Settings\SettingsManager;

/**
 * @IgnoreAnnotation("dataProvider")
 */
class ProvidersManagerTest extends AbstractWebTestCase
{
    private ?ProvidersManager $settingsWarmUpService = null;
    private ?SettingsManager $settingsManager = null;

    /**
     * @before
     */
    public function setUpServices(): void
    {
        $this->settingsManager = static::getContainer()->get(SettingsManager::class);
        $this->settingsWarmUpService = new ProvidersManager($this->settingsManager);
    }

    /**
     * @after
     */
    public function tearDownServices(): void
    {
        $this->settingsManager = null;
        $this->settingsWarmUpService = null;
    }

    public static function warmUpDataProvider(): array
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
