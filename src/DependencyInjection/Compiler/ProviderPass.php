<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\DependencyInjection\Compiler;

use Helis\SettingsManagerBundle\Settings\SettingsManager;
use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ProviderPass implements CompilerPassInterface
{
    public function __construct(private readonly string $tag = 'settings_manager.provider')
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $services = [];

        foreach ($container->findTaggedServiceIds($this->tag) as $id => $attributes) {
            foreach ($attributes as $attribute) {
                if (!isset($attribute['provider'])) {
                    throw new LogicException($this->tag.' tag must be set with provider name');
                }

                $services[$attribute['priority'] ?? 0][$attribute['provider']] = new Reference($id);
            }
        }

        if ($services !== []) {
            ksort($services);
            $services = array_merge(...$services);
        }

        $container->getDefinition(SettingsManager::class)->setArgument('$providers', $services);
    }
}
