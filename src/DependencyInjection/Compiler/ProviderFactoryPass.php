<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\DependencyInjection\Compiler;

use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class ProviderFactoryPass implements CompilerPassInterface
{
    public function __construct(private readonly string $tag = 'settings_manager.provider_factory')
    {
    }

    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds($this->tag) as $id => $attributes) {
            foreach ($attributes as $attribute) {
                if (!isset($attribute['provider'])) {
                    throw new LogicException($this->tag.' tag is missing provider');
                }

                $container
                    ->register($id.'.service', SettingsProviderInterface::class)
                    ->setPublic(false)
                    ->setFactory([new Reference($id), 'get'])
                    ->addTag('settings_manager.provider', [
                        'provider' => $attribute['provider'],
                        'priority' => $attribute['priority'] ?? 0,
                    ]);
            }
        }
    }
}
