<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Settings\Traits\DomainNameExtractTrait;

class ProvidersManager
{
    use DomainNameExtractTrait;

    public function __construct(private readonly SettingsManager $settingsManager)
    {
    }

    /**
     * @param string[] $targetProviders
     * @param string[] $domains
     */
    public function warmUpProviders(string $sourceProvider, array $targetProviders, array $domains): void
    {
        $sourceSettings = $this->getSourceSettings($sourceProvider, $domains);

        foreach ($this->settingsManager->getProviders() as $name => $provider) {
            if (!in_array($name, $targetProviders, true)) {
                continue;
            }

            $this->warmUpProvider($provider, $sourceSettings);
        }
    }

    /**
     * @param string[] $domains
     *
     * @return SettingModel[]
     */
    private function getSourceSettings(string $provider, array $domains): array
    {
        $configProvider = $this->settingsManager->getProvider($provider);

        if ($domains === []) {
            $domainNames = $this->extractDomainNames($configProvider->getDomains());
        }

        return $configProvider->getSettings($domainNames ?? $domains);
    }

    /**
     * @param SettingModel[] $sourceSettings
     */
    private function warmUpProvider(SettingsProviderInterface $provider, array $sourceSettings): void
    {
        $domainNames = $this->extractDomainNames($provider->getDomains());
        $settings = $provider->getSettings($domainNames);

        $missingSettings = $this->getDiff($sourceSettings, $settings);

        foreach ($missingSettings as $settings) {
            $provider->save($settings);
        }
    }

    /**
     * @param SettingModel[] $sourceSettings
     * @param SettingModel[] $settings
     *
     * @return SettingModel[]
     */
    private function getDiff(array $sourceSettings, array $settings): array
    {
        $diff = [];
        foreach ($sourceSettings as $a) {
            $found = false;
            foreach ($settings as $b) {
                if ($a->getName() === $b->getName() && $a->getDomain()->getName() === $b->getDomain()->getName()) {
                    $found = true;
                    break;
                }
            }
            if ($found === false) {
                $diff[] = $a;
            }
        }

        return $diff;
    }
}
