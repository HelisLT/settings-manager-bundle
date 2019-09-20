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
    private $tag;

    public function __construct(string $tag = 'settings_manager.provider')
    {
        $this->tag = $tag;
    }

    public function process(ContainerBuilder $container)
    {
        $services = [];

        foreach ($container->findTaggedServiceIds($this->tag) as $id => $attributes) {
            foreach ($attributes as $attribute) {
                if (!isset($attribute['provider'])) {
                    throw new LogicException($this->tag . ' tag must be set with provider name');
                }

                $services[$attribute['priority'] ?? 0][$attribute['provider']] = new Reference($id);
            }
        }

        if (count($services) > 0) {
            ksort($services);
            $services = array_merge(...$services);
        }

        $container->getDefinition(SettingsManager::class)->setArgument('$providers', $services);
    }
}
