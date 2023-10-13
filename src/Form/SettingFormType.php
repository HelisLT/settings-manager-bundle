<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Form;

use Helis\SettingsManagerBundle\Form\Type\DomainType;
use Helis\SettingsManagerBundle\Form\Type\YamlType;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('domain', DomainType::class)
            ->add('name', null, [
                'disabled' => true,
                'translation_domain' => 'HelisSettingsManager',
                'label' => 'edit.form.name',
            ])
            ->add('type', EnumType::class, [
                'class' => Type::class,
                'disabled' => true,
                'translation_domain' => 'HelisSettingsManager',
                'choice_translation_domain' => 'HelisSettingsManager',
                'choice_label' => fn(Type $type) => 'type.'.strtolower($type->value),
                'label' => 'edit.form.type',
            ])
            ->add('description', TextareaType::class, [
                'translation_domain' => 'HelisSettingsManager',
                'label' => 'edit.form.description',
                'required' => false,
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) {
            /** @var SettingModel $model */
            $model = $event->getData();
            if ($model === null) {
                return;
            }

            if ($model->getType() === Type::BOOL) {
                $event
                    ->getForm()
                    ->add('data', CheckboxType::class, [
                        'translation_domain' => 'HelisSettingsManager',
                        'label' => 'edit.form.is_enabled',
                        'required' => false,
                    ]);
            } elseif ($model->getType() === Type::INT) {
                $event
                    ->getForm()
                    ->add('data', IntegerType::class, [
                        'translation_domain' => 'HelisSettingsManager',
                        'label' => 'edit.form.value',
                    ]);
            } elseif ($model->getType() === Type::FLOAT) {
                $event
                    ->getForm()
                    ->add('data', NumberType::class, [
                        'translation_domain' => 'HelisSettingsManager',
                        'label' => 'edit.form.value',
                        'scale' => 2,
                    ]);
            } elseif ($model->getType() === Type::YAML) {
                $event
                    ->getForm()
                    ->add('data', YamlType::class, [
                        'translation_domain' => 'HelisSettingsManager',
                        'label' => 'edit.form.value',
                        'attr' => ['rows' => 12],
                    ]);
            } elseif ($model->getType() === Type::CHOICE) {
                $event
                    ->getForm()
                    ->add('data', ChoiceType::class, [
                        'translation_domain' => 'HelisSettingsManager',
                        'label' => 'edit.form.value',
                        'placeholder' => 'edit.form.choice_placeholder',
                        'choices' => array_values($model->getChoices()) === $model->getChoices()
                            ? array_combine($model->getChoices(), $model->getChoices())
                            : $model->getChoices(),
                    ]);
            } else {
                $event
                    ->getForm()
                    ->add('data', TextType::class, [
                        'translation_domain' => 'HelisSettingsManager',
                        'label' => 'edit.form.value',
                        'required' => false,
                    ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => SettingModel::class,
                'method' => 'POST',
            ]);
    }
}
