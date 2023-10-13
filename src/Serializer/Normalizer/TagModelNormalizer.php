<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Serializer\Normalizer;

use Helis\SettingsManagerBundle\Model\TagModel;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

class TagModelNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;
    use ObjectToPopulateTrait;

    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        $object = $this->extractObjectToPopulate($type, $context) ?? new $type();

        if (isset($data['name'])) {
            $object->setName($data['name']);
        }

        return $object;
    }

    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return is_a($type, TagModel::class, true);
    }

    /**
     * @param TagModel $object
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        return [
            'name' => $object->getName(),
        ];
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof TagModel;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            TagModel::class => true,
        ];
    }
}
