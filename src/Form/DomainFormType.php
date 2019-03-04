<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Form;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DomainFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'disabled' => true,
                'translation_domain' => 'HelisSettingsManager',
                'label' => 'edit.form.name',
            ])
            ->add('priority', IntegerType::class, [
                'required' => true,
                'translation_domain' => 'HelisSettingsManager',
                'label' => 'edit.form.priority',
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'translation_domain' => 'HelisSettingsManager',
                'label' => 'edit.form.is_enabled',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => DomainModel::class,
                'method' => 'POST',
            ]);
    }
}
