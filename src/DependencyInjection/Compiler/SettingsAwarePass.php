<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\DependencyInjection\Compiler;

use Helis\SettingsManagerBundle\Settings\SettingsAwareServiceFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class SettingsAwarePass implements CompilerPassInterface
{
    private $tag;

    public function __construct(string $tag = 'settings_manager.setting_aware')
    {
        $this->tag = $tag;
    }

    public function process(ContainerBuilder $container)
    {
        $definitions = $container->findTaggedServiceIds($this->tag);

        foreach ($definitions as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $initialTags = $definition->getTags();
            $definition->clearTags();
            $container->setDefinition("{$serviceId}_base", $definition);

            $callMap = [];
            foreach ($tags as $tag) {
                if (!isset($tag['method'])) {
                    throw new LogicException($serviceId . ' tag ' . $this->tag . ' is missing method property');
                }
                $callMap[$tag['setting']] = $tag['method'];
            }

            unset($initialTags[$this->tag]);

            $container
                ->register($serviceId, $definition->getClass())
                ->setArguments([$callMap, new Reference("{$serviceId}_base")])
                ->setFactory([new Reference(SettingsAwareServiceFactory::class), 'get'])
                ->setTags($initialTags);
        }
    }
}
