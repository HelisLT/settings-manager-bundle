<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Event\SettingChangeEvent;
use Helis\SettingsManagerBundle\Exception\ProviderNotFoundException;
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
     * @var SettingsProviderInterface[]
     */
    private $providers;

    /**
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     * @param SettingsProviderInterface[] $providers
     */
    public function __construct(array $providers, EventManagerInterface $eventManager)
    {
        $this->providers = $providers;
        $this->eventManager = $eventManager;
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
    public function getDomains(string $providerName = null, bool $onlyEnabled = false): array
    {
        $domains = [];
        $providers = $providerName !== null ? [$providerName => $this->getProvider($providerName)] : $this->providers;

        foreach ($providers as $provider) {
            foreach ($provider->getDomains($onlyEnabled) as $domainModel) {
                $domains[$domainModel->getName()][$domainModel->getPriority()] = $domainModel;
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
            foreach ($provider->getSettingsByName($domainNames, $settingNames) as $settingModel) {
                if ($settingModel instanceof SettingModel) {
                    $settingModel->setProviderName($pName);

                    if ((isset($providerSettings[$settingModel->getName()])
                        && $providerSettings[$settingModel->getName()]->getDomain()->getPriority() < $settingModel->getDomain()->getPriority())
                        || !isset($providerSettings[$settingModel->getName()])
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
            if (count($settingNames) === 0) {
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
            $providerSettings = [];
            foreach ($provider->getSettings($domainNames) as $settingModel) {
                $settingModel->setProviderName($pName);

                if ((isset($providerSettings[$settingModel->getName()])
                        && $providerSettings[$settingModel->getName()]->getDomain()->getPriority() < $settingModel->getDomain()->getPriority())
                    || !isset($providerSettings[$settingModel->getName()])
                ) {
                    $providerSettings[$settingModel->getName()] = $settingModel;
                }
            }

            $settings[] = $providerSettings;
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
            $providerSettings = [];
            foreach ($provider->getSettings($domainNames) as $settingModel) {
                if ($settingModel->hasTag($tagName)) {
                    $settingModel->setProviderName($pName);

                    if ((isset($providerSettings[$settingModel->getName()])
                            && $providerSettings[$settingModel->getName()]->getDomain()->getPriority() < $settingModel->getDomain()->getPriority())
                        || !isset($providerSettings[$settingModel->getName()])
                    ) {
                        $providerSettings[$settingModel->getName()] = $settingModel;
                    }
                }
            }

            $settings[] = $providerSettings;
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
            } catch (ReadOnlyProviderException $e) {
                $result = false;
            }

            if ($result === true) {
                $this->logger && $this->logger->info('SettingsManager: setting updated', [
                    'sSettingName' => $settingModel->getName(),
                    'sSettingType' => $settingModel->getType()->getValue(),
                    'sSettingValue' => json_encode($settingModel->getDataValue()),
                    'sDomainName' => $settingModel->getDomain()->getName(),
                    'sDomainEnabled' => $settingModel->getDomain()->isReadOnly(),
                    'sProviderName' => $settingModel->getProviderName(),
                ]);
                $this->eventManager->dispatch(
                    SettingsManagerEvents::SAVE_SETTING,
                    new SettingChangeEvent($settingModel)
                );

                return $result;
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
                if (!$provider->isReadOnly() && $provider->save($settingModel) !== false) {
                    $this->logger && $this->logger->info('SettingsManager: setting saved', [
                        'sSettingName' => $settingModel->getName(),
                        'sSettingType' => $settingModel->getType()->getValue(),
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
            } catch (ReadOnlyProviderException $e) {
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

    public function updateDomain(DomainModel $domainModel, string $providerName = null): void
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

    public function deleteDomain(string $domainName, string $providerName = null): void
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
}
