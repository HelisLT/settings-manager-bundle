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
    public function __construct(
        private readonly DenormalizerInterface $serializer,
        private readonly array $normalizedData,
        private readonly bool $readOnly = true
    ) {
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
