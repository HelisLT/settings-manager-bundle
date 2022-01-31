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

    /**
     * @return mixed
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $object = $this->extractObjectToPopulate($class, $context) ?? new $class();

        isset($data['name']) && $object->setName($data['name']);

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
}
