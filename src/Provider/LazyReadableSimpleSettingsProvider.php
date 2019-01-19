<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class LazyReadableSimpleSettingsProvider extends ReadableSimpleSettingsProvider
{
    private $serializer;
    private $normSettingsByDomain;
    private $normDomains;

    private $modelSettingsByDomain;
    private $modelDomains;
    private $modelDomainsEnabled;

    public function __construct(
        DenormalizerInterface $serializer,
        array $normSettingsByDomain,
        array $normDomains
    ) {
        parent::__construct([]);

        $this->serializer = $serializer;
        $this->normSettingsByDomain = $normSettingsByDomain;
        $this->normDomains = $normDomains;

        $this->modelSettingsByDomain = [];
        $this->modelDomains = [];
        $this->modelDomainsEnabled = [];
    }

    public function getSettings(array $domainNames): array
    {
        $modelSettingsByDomain = array_intersect_key($this->modelSettingsByDomain, array_flip($domainNames));
        $normSettingsByDomain = array_intersect_key($this->normSettingsByDomain, array_flip($domainNames));

        foreach ($normSettingsByDomain as $domainName => $normSettings) {
            $this->modelSettingsByDomain[$domainName] = array_replace(
                $this->modelSettingsByDomain[$domainName] ?? [],
                $this->serializer->denormalize($normSettings, SettingModel::class . '[]')
            );
            $modelSettingsByDomain[$domainName] = array_replace($modelSettingsByDomain[$domainName] ?? [], $this->modelSettingsByDomain[$domainName]);
            unset($this->normSettingsByDomain[$domainName]);
        }

        return empty($modelSettingsByDomain)
            ? []
            : array_merge_recursive(...array_values($modelSettingsByDomain));
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $normSettingsByDomain = array_intersect_key($this->normSettingsByDomain, array_flip($domainNames));
        $modelSettingsByDomain = array_intersect_key($this->modelSettingsByDomain, array_flip($domainNames));


        foreach ($normSettingsByDomain as $domainName => $normSettings) {
            $normSettings = array_intersect_key($normSettings, array_flip($settingNames));

            if (!empty($normSettings)) {
                $pickedModelSettings = $this->serializer->denormalize($normSettings, SettingModel::class . '[]');
                $modelSettingsByDomain[$domainName] = array_replace($modelSettingsByDomain[$domainName] ?? [], $pickedModelSettings);
                $this->modelSettingsByDomain[$domainName] = array_replace($this->modelSettingsByDomain[$domainName] ?? [], $pickedModelSettings);
                $this->normSettingsByDomain[$domainName] = array_diff_key($this->normSettingsByDomain[$domainName], $pickedModelSettings);

                if (empty($this->normSettingsByDomain[$domainName])) {
                    unset($this->normSettingsByDomain[$domainName]);
                }
            }
        }

        $out = [];
        foreach ($modelSettingsByDomain as $domainName => $modelSettings) {
            $out = array_merge($out, array_values(array_intersect_key($modelSettings, array_flip($settingNames))));
        }

        return $out;
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        if (count($this->normDomains) > 0 && count($this->modelDomains) === 0) {
            foreach ($this->normDomains as $normDomain) {
                /** @var DomainModel $model */
                $model = $this->serializer->denormalize($normDomain, DomainModel::class);
                $this->modelDomains[] = $model;
                $model->isEnabled() && ($this->modelDomainsEnabled[] = $model);
            }
            $this->normDomains = [];
        }

        return $onlyEnabled ? $this->modelDomainsEnabled : $this->modelDomains;
    }
}
