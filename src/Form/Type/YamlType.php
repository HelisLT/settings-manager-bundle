<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class YamlType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new CallbackTransformer(
            fn ($array) => $array !== null ? json_encode($array) : $array,
            function ($json) {
                if ($json === null) {
                    return [];
                }

                $array = json_decode($json, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($array)) {
                    $array = [$array ?? $json];
                }

                return $array;
            }
        ));
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }
}
