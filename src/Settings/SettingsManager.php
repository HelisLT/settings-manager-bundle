<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Event\SettingChangeEvent;
use Helis\SettingsManagerBundle\Exception\ProviderNotFoundException;
use Helis\SettingsManagerBundle\Exception\ProviderUnavailableException;
use Helis\SettingsManagerBundle\Exception\ReadOnlyProviderException;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\SettingsManagerEvents;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class SettingsManager implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param SettingsProviderInterface[] $providers
     */
    public function __construct(private array $providers, private readonly EventManagerInterface $eventManager)
    {
    }

    /**
     * @return SettingsProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * @return DomainModel[]
     */
    public function getDomains(?string $providerName = null, bool $onlyEnabled = false): array
    {
        $domains = [];
        $providers = $providerName !== null ? [$providerName => $this->getProvider($providerName)] : $this->providers;

        foreach ($providers as $provider) {
            try {
                foreach ($provider->getDomains($onlyEnabled) as $domainModel) {
                    $domains[$domainModel->getName()][$domainModel->getPriority()] = $domainModel;
                }
            } catch (ProviderUnavailableException $e) {
                $this->logger && $this->logger->error(sprintf('SettingsManager:%s(): Settings provider "%s" is unavailable, skipping.', __METHOD__, $provider::class), ['exception' => $e]);
            }
        }

        foreach ($domains as &$domainGroup) {
            $domainGroup = $domainGroup[max(array_keys($domainGroup))];
        }

        return $domains;
    }

    /**
     * @param string[] $domainNames
     * @param string[] $settingNames
     *
     * @return SettingModel[]
     */
    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $settings = [[]];

        /** @var SettingsProviderInterface $provider */
        foreach (array_reverse($this->providers) as $pName => $provider) {
            $providerSettings = [];
            try {
                $settingsByName = $provider->getSettingsByName($domainNames, $settingNames);
            } catch (ProviderUnavailableException $e) {
                $this->logger && $this->logger->error(sprintf('SettingsManager:%s(): Settings provider "%s" is unavailable, skipping.', __METHOD__, $provider::class), ['exception' => $e]);
                continue;
            }

            foreach ($settingsByName as $settingModel) {
                if ($settingModel instanceof SettingModel) {
                    $settingModel->setProviderName($pName);

                    if (!isset($providerSettings[$settingModel->getName()])
                        || $providerSettings[$settingModel->getName()]->getDomain()->getPriority() < $settingModel->getDomain()->getPriority()
                    ) {
                        $providerSettings[$settingModel->getName()] = $settingModel;
                    }

                    if (($k = array_search($settingModel->getName(), $settingNames, true)) !== false) {
                        unset($settingNames[$k]);
                    }
                } else {
                    $this->logger && $this->logger->warning('SettingsManager: received null setting', [
                        'sProviderName' => $pName,
                        'sSettingName' => $settingNames,
                    ]);
                }
            }

            $settings[] = array_values($providerSettings);

            // check if already has enough
            if ($settingNames === []) {
                break;
            }
        }

        return array_merge(...$settings);
    }

    /**
     * @param string[] $domainNames
     *
     * @return SettingModel[]
     */
    public function getSettingsByDomain(array $domainNames): array
    {
        $settings = [[]];

        foreach ($this->providers as $pName => $provider) {
            try {
                $settings[] = $this->collectSettings($provider->getSettings($domainNames), $pName);
            } catch (ProviderUnavailableException $e) {
                $this->logger && $this->logger->error(sprintf('SettingsManager:%s(): Settings provider "%s" is unavailable, skipping.', __METHOD__, $provider::class), ['exception' => $e]);
            }
        }

        return array_replace(...$settings);
    }

    /**
     * @param string[] $domainNames
     *
     * @return SettingModel[]
     */
    public function getSettingsByTag(array $domainNames, string $tagName): array
    {
        $settings = [[]];

        foreach ($this->providers as $pName => $provider) {
            try {
                $settings[] = $this->collectSettings($provider->getSettingsByTag($domainNames, $tagName), $pName);
            } catch (ProviderUnavailableException $e) {
                $this->logger && $this->logger->error(sprintf('SettingsManager:%s(): Settings provider "%s" is unavailable, skipping.', __METHOD__, $provider::class), ['exception' => $e]);
            }
        }

        return array_replace(...$settings);
    }

    /**
     * Tries to update an existing provider or saves to a new provider.
     */
    public function save(SettingModel $settingModel): bool
    {
        if ($settingModel->getProviderName()) {
            try {
                $result = $this->providers[$settingModel->getProviderName()]->save($settingModel);
            } catch (ReadOnlyProviderException) {
                $result = false;
            }

            if ($result) {
                $this->logger && $this->logger->info('SettingsManager: setting updated', [
                    'sSettingName' => $settingModel->getName(),
                    'sSettingType' => $settingModel->getType()->value,
                    'sSettingValue' => json_encode($settingModel->getDataValue()),
                    'sDomainName' => $settingModel->getDomain()->getName(),
                    'sDomainEnabled' => $settingModel->getDomain()->isReadOnly(),
                    'sProviderName' => $settingModel->getProviderName(),
                ]);
                $this->eventManager->dispatch(
                    SettingsManagerEvents::SAVE_SETTING,
                    new SettingChangeEvent($settingModel)
                );

                return true;
            }
        }

        $closed = $settingModel->getProviderName() !== null;

        foreach ($this->providers as $name => $provider) {
            if ($closed) {
                if ($settingModel->getProviderName() === $name) {
                    $closed = false;
                } else {
                    continue;
                }
            }

            try {
                if (!$provider->isReadOnly() && $provider->save($settingModel)) {
                    $this->logger && $this->logger->info('SettingsManager: setting saved', [
                        'sSettingName' => $settingModel->getName(),
                        'sSettingType' => $settingModel->getType()->value,
                        'sSettingValue' => json_encode($settingModel->getDataValue()),
                        'sDomainName' => $settingModel->getDomain()->getName(),
                        'sDomainEnabled' => $settingModel->getDomain()->isReadOnly(),
                        'sProviderName' => $settingModel->getProviderName(),
                    ]);
                    $this->eventManager->dispatch(
                        SettingsManagerEvents::SAVE_SETTING,
                        new SettingChangeEvent($settingModel)
                    );

                    return true;
                }
            } catch (ReadOnlyProviderException) {
                // go to next provider
            }
        }

        return false;
    }

    public function delete(SettingModel $settingModel): bool
    {
        $changed = false;

        if ($settingModel->getProviderName()) {
            $changed = $this
                ->providers[$settingModel->getProviderName()]
                ->delete($settingModel);
        } else {
            foreach ($this->providers as $provider) {
                if ($provider->delete($settingModel)) {
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $this->eventManager->dispatch(
                SettingsManagerEvents::DELETE_SETTING,
                new SettingChangeEvent($settingModel)
            );
        }

        return $changed;
    }

    /**
     * Saves settings from domain to specific provider. Mostly used for setting population.
     */
    public function copyDomainToProvider(string $domainName, string $providerName): void
    {
        $provider = $this->getProvider($providerName);
        $settings = $this->getSettingsByDomain([$domainName]);

        foreach ($settings as $setting) {
            $provider->save($setting);
        }

        $this->logger && $this->logger->info('SettingsManager: domain copied', [
            'sDomainName' => $domainName,
            'sProviderName' => $providerName,
        ]);
    }

    public function updateDomain(DomainModel $domainModel, ?string $providerName = null): void
    {
        if ($providerName !== null) {
            $provider = $this->getProvider($providerName);
            $provider->updateDomain($domainModel);
        } else {
            foreach ($this->providers as $provider) {
                if (!$provider->isReadOnly()) {
                    $provider->updateDomain($domainModel);
                }
            }
        }

        $this->logger && $this->logger->info('SettingsManager: domain updated', [
            'sProviderName' => $providerName,
            'sDomainName' => $domainModel->getName(),
            'bDomainEnabled' => $domainModel->isEnabled(),
            'iDomainPriority' => $domainModel->getPriority(),
        ]);
    }

    public function deleteDomain(string $domainName, ?string $providerName = null): void
    {
        if ($providerName !== null) {
            $provider = $this->getProvider($providerName);
            $provider->deleteDomain($domainName);
        } else {
            foreach ($this->providers as $provider) {
                if (!$provider->isReadOnly()) {
                    $provider->deleteDomain($domainName);
                }
            }
        }

        $this->logger && $this->logger->info('SettingsManager: domain deleted', [
            'sProviderName' => $providerName,
            'sDomainName' => $domainName,
        ]);
    }

    public function getProvider(string $providerName): SettingsProviderInterface
    {
        if (!isset($this->providers[$providerName])) {
            throw new ProviderNotFoundException($providerName);
        }

        return $this->providers[$providerName];
    }

    /**
     * @param SettingModel[] $settings
     *
     * @return SettingModel[]
     */
    private function collectSettings(array $settings, string $providerName): array
    {
        $result = [];

        foreach ($settings as $settingModel) {
            $settingModel->setProviderName($providerName);

            if (!isset($result[$settingModel->getName()])
                || $result[$settingModel->getName()]->getDomain()->getPriority() < $settingModel->getDomain()->getPriority()
            ) {
                $result[$settingModel->getName()] = $settingModel;
            }
        }

        return $result;
    }
}
