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
            function (array $array) {
                return json_encode($array);
            },
            function (string $json) {
                return json_decode($json, true);
            }
        ));
    }

    public function getParent()
    {
        return TextareaType::class;
    }
}
