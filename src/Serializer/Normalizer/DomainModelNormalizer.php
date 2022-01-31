<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Serializer\Normalizer;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;

class DomainModelNormalizer implements NormalizerInterface, DenormalizerInterface
{
    use ObjectToPopulateTrait;

    /**
     * @return mixed
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $object = $this->extractObjectToPopulate($class, $context) ?? new $class();

        isset($data['name']) && $object->setName($data['name']);
        isset($data['enabled']) && $object->setEnabled($data['enabled']);
        isset($data['read_only']) && $object->setReadOnly($data['read_only']);
        isset($data['priority']) && $object->setPriority($data['priority']);

        return $object;
    }

    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return is_a($type, DomainModel::class, true);
    }

    /**
     * @param DomainModel $object
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        return [
            'name' => $object->getName(),
            'enabled' => $object->isEnabled(),
            'read_only' => $object->isReadOnly(),
            'priority' => $object->getPriority(),
        ];
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof DomainModel;
    }
}
