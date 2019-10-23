<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider\Factory;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\ReadableSimpleSettingsProvider;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Provider\SimpleSettingsProvider;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class SimpleSettingsProviderFactory implements ProviderFactoryInterface
{
    private $serializer;
    private $normalizedData;
    private $readOnly;

    public function __construct(DenormalizerInterface $serializer, array $normalizedData, bool $readOnly = true)
    {
        $this->serializer = $serializer;
        $this->normalizedData = $normalizedData;
        $this->readOnly = $readOnly;
    }

    public function get(): SettingsProviderInterface
    {
        /** @var SettingModel[] $settings */
        $settings = $this->serializer->denormalize($this->normalizedData, SettingModel::class.'[]');

        if ($this->readOnly) {
            return new ReadableSimpleSettingsProvider($settings);
        }

        return new SimpleSettingsProvider($settings);
    }
}
