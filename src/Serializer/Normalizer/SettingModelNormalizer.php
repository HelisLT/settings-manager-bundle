<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Serializer\Normalizer;

use Doctrine\Common\Collections\ArrayCollection;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\TagModel;
use Helis\SettingsManagerBundle\Model\Type;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;

class SettingModelNormalizer implements NormalizerInterface, DenormalizerInterface, DenormalizerAwareInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;
    use ObjectToPopulateTrait;

    /**
     * @return mixed
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $object = $this->extractObjectToPopulate($class, $context) ?? new $class();

        isset($data['name']) && $object->setName($data['name']);
        isset($data['description']) && $object->setDescription($data['description']);
        isset($data['domain']) && $object->setDomain(
            $this->denormalizer->denormalize($data['domain'], DomainModel::class, $format, $context)
        );
        isset($data['type']) && $object->setType(new Type($data['type']));
        isset($data['data']) && $object->setDataValue($data['data']);
        isset($data['tags']) && $object->setTags(new ArrayCollection(
            $this->denormalizer->denormalize($data['tags'], TagModel::class.'[]', $format, $context)
        ));
        isset($data['choices']) && $object->setChoices($data['choices']);

        return $object;
    }

    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return is_a($type, SettingModel::class, true);
    }

    /**
     * @param SettingModel $object
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        return [
            'name' => $object->getName(),
            'description' => $object->getDescription(),
            'domain' => $this->normalizer->normalize($object->getDomain(), $format, $context),
            'type' => $object->getType()->getValue(),
            'data' => $object->getDataValue(),
            'tags' => $this->normalizer->normalize($object->getTags(), $format, $context),
            'choices' => $object->getChoices(),
        ];
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof SettingModel;
    }
}
