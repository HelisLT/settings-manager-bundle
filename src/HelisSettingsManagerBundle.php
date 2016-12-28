<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle;

use Acelaya\Doctrine\Type\PhpEnumType;
use Doctrine\DBAL\Types\Type as DoctrineType;
use Helis\SettingsManagerBundle\DependencyInjection\Compiler\ProviderFactoryPass;
use Helis\SettingsManagerBundle\DependencyInjection\Compiler\ProviderPass;
use Helis\SettingsManagerBundle\DependencyInjection\Compiler\SettingsAwarePass;
use Helis\SettingsManagerBundle\Model\Type;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HelisSettingsManagerBundle extends Bundle
{
    public function boot()
    {
        parent::boot();

        if (class_exists('Acelaya\Doctrine\Type\PhpEnumType')
            && !DoctrineType::hasType('setting_type_enum')
        ) {
            PhpEnumType::registerEnumType('setting_type_enum', Type::class);
        }
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new ProviderFactoryPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1);
        $container->addCompilerPass(new ProviderPass());
        $container->addCompilerPass(new SettingsAwarePass());
    }
}
