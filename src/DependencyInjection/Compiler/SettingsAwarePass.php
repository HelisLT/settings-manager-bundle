<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\DependencyInjection\Compiler;

use Helis\SettingsManagerBundle\Settings\SettingsAwareServiceFactory;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class SettingsAwarePass implements CompilerPassInterface
{
    public function __construct(private readonly string $tag = 'settings_manager.setting_aware')
    {
    }

    public function process(ContainerBuilder $container): void
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
                    throw new LogicException($serviceId.' tag '.$this->tag.' is missing method property');
                }
                $callMap[$tag['setting']] = [
                    'setter' => $tag['method'],
                    'must' => $tag['must'] ?? false,
                ];
            }

            unset($initialTags[$this->tag]);

            $container
                ->register($serviceId, $definition->getClass())
                ->setArguments([$callMap, new Reference("{$serviceId}_base")])
                ->setFactory([new Reference(SettingsAwareServiceFactory::class), 'get'])
                ->setPublic($definition->isPublic())
                ->setLazy(!(new ReflectionClass($definition->getClass()))->isFinal())
                ->setTags($initialTags);
        }
    }
}
