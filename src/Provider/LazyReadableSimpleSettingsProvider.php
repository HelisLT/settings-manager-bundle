<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\Traits\ReadOnlyProviderTrait;
use Symfony\Component\Serializer\SerializerInterface;

class LazyReadableSimpleSettingsProvider extends ReadableSimpleSettingsProvider
{
    use ReadOnlyProviderTrait;

    private $serializer;
    private $settingModelsByDomain;
    private $domainModels;
    private $enabledDomainModels;
    private $normalizedSettingsByDomain;
    private $normalizedDomains;

    public function __construct(
        SerializerInterface $serializer,
        array $normalizedSettingsByDomain,
        array $normalizedDomains
    ) {
        parent::__construct([]);

        $this->serializer = $serializer;
        $this->settingModelsByDomain = [];
        $this->domainModels = [];
        $this->enabledDomainModels = [];
        $this->normalizedSettingsByDomain = $normalizedSettingsByDomain;
        $this->normalizedDomains = $normalizedDomains;
    }

    public function getSettings(array $domainNames): array
    {
        $out = [];

        foreach ($domainNames as $domainName) {
            if (isset($this->settingModelsByDomain[$domainName])) {
                // has some models
                if (count($this->settingModelsByDomain[$domainName])
                    !== count($this->normalizedSettingsByDomain[$domainName])
                ) {
                    // denormalize missing models
                    $missingSettings = array_diff_key(
                        $this->normalizedSettingsByDomain[$domainName],
                        $this->settingModelsByDomain[$domainName]
                    );
                    $this->settingModelsByDomain[$domainName] = array_replace(
                        $this->settingModelsByDomain[$domainName],
                        $this->serializer->denormalize($missingSettings, SettingModel::class . '[]')
                    );
                }

                $out = array_merge($out, array_values($this->settingModelsByDomain[$domainName]));
            } elseif (isset($this->normalizedSettingsByDomain[$domainName])) {
                // has normalized models
                $this->settingModelsByDomain[$domainName] = $this
                    ->serializer
                    ->denormalize($this->normalizedSettingsByDomain[$domainName], SettingModel::class . '[]');
                $out = array_merge($out, array_values($this->settingModelsByDomain[$domainName]));
            }
        }

        return $out;
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $out = [];

        foreach ($domainNames as $domainName) {
            foreach ($settingNames as $settingName) {
                if (isset($this->settingModelsByDomain[$domainName][$settingName])) {
                    // already has a model
                    $out[] = $this->settingModelsByDomain[$domainName][$settingName];
                } elseif (isset($this->normalizedSettingsByDomain[$domainName][$settingName])) {
                    // normalized data exists, make a model
                    $normalizedSetting = $this->normalizedSettingsByDomain[$domainName][$settingName];
                    $settingModel = $this->serializer->denormalize($normalizedSetting, SettingModel::class);
                    $this->settingModelsByDomain[$domainName][$settingName] = $settingModel;
                    $out[] = $settingModel;
                }
            }
        }

        return $out;
    }

    public function getDomains(bool $onlyEnabled = false, bool $invalidate = false): array
    {
        if (count($this->normalizedDomains) > 0 && count($this->domainModels) === 0) {
            $this->domainModels = $this
                ->serializer
                ->denormalize(array_values($this->normalizedDomains), DomainModel::class . '[]');
        }

        if ($onlyEnabled && count($this->enabledDomainModels) === 0 && count($this->domainModels) > 0) {
            $this->enabledDomainModels = array_filter($this->domainModels, function (DomainModel $domainModel) {
                return $domainModel->isEnabled();
            });
        }

        return $onlyEnabled ? $this->enabledDomainModels : $this->domainModels;
    }
}
