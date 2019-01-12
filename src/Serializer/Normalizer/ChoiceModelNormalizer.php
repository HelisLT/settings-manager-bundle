<?php

namespace Helis\SettingsManagerBundle\Serializer\Normalizer;

use Helis\SettingsManagerBundle\Model\ChoiceModel;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

class ChoiceModelNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{

    use SerializerAwareTrait, ObjectToPopulateTrait;

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $object = $this->extractObjectToPopulate($class, $context) ?? new $class();

        isset($data['value']) && $object->setValue($data['value']);

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return is_a($type, ChoiceModel::class, true);
    }

    /**
     * {@inheritdoc}
     *
     * @param ChoiceModel $object
     */
    public function normalize($object, $format = null, array $context = [])
    {
        return [
            'value' => $object->getValue(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof ChoiceModel;
    }
}