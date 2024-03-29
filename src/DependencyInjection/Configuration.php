<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\DependencyInjection;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\Type;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('helis_settings_manager', 'array');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('enqueue_extension')
                    ->canBeEnabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('divider')->defaultValue(1)->end()
                        ->integerNode('priority')->defaultValue(100)->end()
                    ->end()
                ->end()
                ->arrayNode('settings_router')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('treat_as_default_providers')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('profiler')
                    ->canBeEnabled()
                    ->addDefaultsIfNotSet()
                ->end()
                ->arrayNode('logger')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->validate()
                        ->ifTrue(fn ($v) => $v['enabled'] && !isset($v['service_id']))
                        ->thenInvalid('logger service_id is missing')
                    ->end()
                    ->children()
                        ->scalarNode('service_id')
                            ->example('monolog.logger.settings')
                            ->info('Psr\Log\LoggerInterface service id')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('settings_config')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('lazy')
                            ->defaultTrue()
                            ->info('Settings from config will be lazy denormalized in provider.')
                        ->end()
                        ->integerNode('priority')
                            ->defaultValue(-10)
                            ->info('Priority for settings from configuration')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('settings_files')
                    ->scalarPrototype()->end()
                ->end()
                ->append($this->getSettingsNode())
                ->append($this->getListenersNode())
            ->end();

        return $treeBuilder;
    }

    private function getSettingsNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('settings', 'array');
        $node = $treeBuilder->getRootNode();

        $node
            ->arrayPrototype()
            ->children()
                ->scalarNode('name')->isRequired()->end()
                ->scalarNode('description')->end()
                ->arrayNode('domain')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(fn ($v) => [
                            'name' => $v,
                            'enabled' => true, // domains from config are enabled by default
                            'read_only' => true, // all config domains are read only
                        ])
                    ->end()
                    ->children()
                        ->scalarNode('name')->defaultValue(DomainModel::DEFAULT_NAME)->end()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->booleanNode('read_only')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->arrayPrototype()
                        ->beforeNormalization()
                        ->ifString()
                        ->then(fn ($v) => ['name' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('name')->end()
                        ->end()
                    ->end()
                ->end()
                ->enumNode('type')
                    ->values(array_column(Type::cases(), 'value'))
                    ->isRequired()
                ->end()
                ->arrayNode('data')
                    ->beforeNormalization()
                    ->always()
                    ->then(function ($v) {
                        if (is_string($v) || is_int($v) || is_float($v)) {
                            return ['value' => $v];
                        }

                        if (is_array($v) && isset($v['value'])) {
                            return $v;
                        }

                        return ['value' => $v];
                    })
                    ->end()
                    ->children()
                        ->variableNode('value')
                        ->isRequired()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('choices')
                    ->variablePrototype()->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    private function getListenersNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('listeners', 'array');
        $node = $treeBuilder->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('controller')->canBeEnabled()->end()
                ->arrayNode('command')->canBeEnabled()->end()
            ->end();

        return $node;
    }
}
