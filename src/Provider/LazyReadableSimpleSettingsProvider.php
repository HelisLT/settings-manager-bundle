<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class LazyReadableSimpleSettingsProvider extends ReadableSimpleSettingsProvider
{
    private array $modelSettings = [];
    private array $modelDomains = [];
    private array $modelDomainsEnabled = [];

    public function __construct(
        private readonly DenormalizerInterface $serializer,
        private array $normDomains,
        private array $normSettings,
        private readonly array $settingsKeyMap,
        private readonly array $domainsKeyMap,
    ) {
        parent::__construct([]);
    }

    public function getSettings(array $domainNames): array
    {
        $keys = array_intersect_key($this->domainsKeyMap, array_flip($domainNames));
        if ($keys === []) {
            return [];
        }
        $keys = array_merge(...array_values($keys));
        $normSettings = array_intersect_key($this->normSettings, array_flip($keys));

        if ($normSettings !== []) {
            $modelSettings = $this->serializer->denormalize($normSettings, SettingModel::class.'[]');
            $this->modelSettings = array_merge($this->modelSettings, $modelSettings);
            $this->normSettings = array_diff_key($this->normSettings, $modelSettings);
        }

        $modelSettings = array_intersect_key($this->modelSettings, array_flip($keys));

        return $modelSettings === [] ? [] : array_values($modelSettings);
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $settingKeys = array_intersect_key($this->settingsKeyMap, array_flip($settingNames));
        $domainKeys = array_intersect_key($this->domainsKeyMap, array_flip($domainNames));
        if ($settingKeys === [] || $domainKeys === []) {
            return [];
        }

        $keys = array_flip(array_intersect(
            array_merge(...array_values($settingKeys)),
            array_merge(...array_values($domainKeys))
        ));
        $normSettings = array_intersect_key($this->normSettings, $keys);

        if ($normSettings !== []) {
            $modelSettings = $this->serializer->denormalize($normSettings, SettingModel::class.'[]');
            $this->modelSettings = array_merge($this->modelSettings, $modelSettings);
            $this->normSettings = array_diff_key($this->normSettings, $modelSettings);
        }

        $modelSettings = array_intersect_key($this->modelSettings, $keys);

        return $modelSettings === [] ? [] : array_values($modelSettings);
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        if ($this->normDomains !== [] && $this->modelDomains === []) {
            foreach ($this->normDomains as $normDomain) {
                /** @var DomainModel $model */
                $model = $this->serializer->denormalize($normDomain, DomainModel::class);
                $this->modelDomains[] = $model;
                if ($model->isEnabled()) {
                    $this->modelDomainsEnabled[] = $model;
                }
            }
            $this->normDomains = [];
        }

        return $onlyEnabled ? $this->modelDomainsEnabled : $this->modelDomains;
    }
}
