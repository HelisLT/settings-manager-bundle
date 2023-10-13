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

    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        $object = $this->extractObjectToPopulate($type, $context) ?? new $type();

        if (isset($data['name'])) {
            $object->setName($data['name']);
        }
        if (isset($data['description'])) {
            $object->setDescription($data['description']);
        }
        if (isset($data['domain'])) {
            $object->setDomain(
                $this->denormalizer->denormalize($data['domain'], DomainModel::class, $format, $context)
            );
        }
        if (isset($data['type'])) {
            $object->setType(new Type($data['type']));
        }
        if (isset($data['data'])) {
            $object->setDataValue($data['data']);
        }
        if (isset($data['tags'])) {
            $object->setTags(new ArrayCollection(
                $this->denormalizer->denormalize($data['tags'], TagModel::class.'[]', $format, $context)
            ));
        }
        if (isset($data['choices'])) {
            $object->setChoices($data['choices']);
        }

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

    public function getSupportedTypes(?string $format): array
    {
        return [
            SettingModel::class => true,
        ];
    }
}
