<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\DependencyInjection;

use Helis\SettingsManagerBundle\DataCollector\SettingsCollector;
use Helis\SettingsManagerBundle\Enqueue\Consumption\WarmupSettingsManagerExtension;
use Helis\SettingsManagerBundle\Provider\DecoratingInMemorySettingsProvider;
use Helis\SettingsManagerBundle\Provider\Factory\SimpleSettingsProviderFactory;
use Helis\SettingsManagerBundle\Provider\LazyReadableSimpleSettingsProvider;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Settings\EventManagerInterface;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Helis\SettingsManagerBundle\Settings\SettingsStore;
use Helis\SettingsManagerBundle\Subscriber\SwitchableCommandSubscriber;
use Helis\SettingsManagerBundle\Subscriber\SwitchableControllerSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;

class HelisSettingsManagerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('command.yaml');
        $loader->load('services.yaml');
        $loader->load('serializer.yaml');

        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['TwigBundle'])
            && isset($bundles['FrameworkBundle'])
            && class_exists(Form::class)
            && class_exists(Validation::class)) {
            $loader->load('controllers.yaml');
        }

        if (isset($bundles['TwigBundle'])) {
            $loader->load('twig.yaml');
        }

        if (isset($bundles['KnpMenuBundle'])) {
            $loader->load('menu.yaml');
        }

        if ($config['profiler']['enabled']) {
            $this->loadDataCollector($container);
        }

        if ($config['logger']['enabled']) {
            $container->setAlias('settings_manager.logger', $config['logger']['service_id']);
        }

        if (interface_exists(ValidatorInterface::class)) {
            $loader->load('validators.yaml');
        }

        $this->loadSettingsManager($container);
        $this->loadSettingsRouter($config, $container);
        $this->loadSimpleProvider($config, $container);
        $this->loadListeners($config['listeners'], $container);
        $this->loadEnqueueExtension($config['enqueue_extension'], $container);
    }

    public function loadSettingsRouter(array $config, ContainerBuilder $container): void
    {
        $container
            ->register(SettingsRouter::class, SettingsRouter::class)
            ->setPublic(true)
            ->setArgument(0, new Reference(SettingsManager::class))
            ->setArgument(1, new Reference(SettingsStore::class))
            ->setArgument(2, new Reference(EventManagerInterface::class))
            ->setArgument(3, $config['settings_router']['treat_as_default_providers']);
    }

    private function loadEnqueueExtension(array $config, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $container
            ->register(WarmupSettingsManagerExtension::class, WarmupSettingsManagerExtension::class)
            ->addMethodCall('setSettingsRouter', [new Reference(SettingsRouter::class)])
            ->addMethodCall('setDivider', [$config['divider']])
            ->addTag('enqueue.transport.consumption_extension', ['priority' => $config['priority'], 'transport' => 'all']);
    }

    private function loadSettingsManager(ContainerBuilder $container): void
    {
        $container
            ->register(SettingsManager::class, SettingsManager::class)
            ->setPublic(true)
            ->setLazy(true)
            ->setArgument('$eventManager', new Reference(EventManagerInterface::class))
            ->addMethodCall('setLogger', [
                new Reference(
                    'settings_manager.logger',
                    ContainerInterface::IGNORE_ON_INVALID_REFERENCE
                ),
            ]);
    }

    private function loadSimpleProvider(array $config, ContainerBuilder $container): void
    {
        $settings = $this->mergeSettings(
            $config['settings'],
            $this->loadSettingsFromFiles($config['settings_files'], $container)
        );

        if (!$config['settings_config']['lazy']) {
            $container->register('settings_manager.provider.config.factory', SimpleSettingsProviderFactory::class)
                ->setArguments([new Reference('settings_manager.serializer'), $settings, true])
                ->setPublic(false)
                ->addTag('settings_manager.provider_factory', [
                    'provider' => SettingsProviderInterface::DEFAULT_PROVIDER,
                    'priority' => $config['settings_config']['priority'],
                ]);
            ;

            $container
                ->register('settings_manager.provider.config', SettingsProviderInterface::class)
                ->setFactory(new Reference('settings_manager.provider.config.factory'));
        } else {
            $normDomains = [];
            $normSettings = [];
            $settingsKeyMap = [];
            $domainsKeyMap = [];

            foreach ($settings as $setting) {
                $domainName = $setting['domain']['name'];
                $settingName = $setting['name'];
                $settingKey = $domainName.'_'.$settingName;

                $normDomains[$domainName] = $setting['domain'];
                $normSettings[$settingKey] = $setting;
                $settingsKeyMap[$settingName][] = $domainsKeyMap[$domainName][] = $settingKey;
            }

            $container
                ->register('settings_manager.provider.config', LazyReadableSimpleSettingsProvider::class)
                ->setArguments([
                    new Reference('settings_manager.serializer'),
                    $normDomains,
                    $normSettings,
                    $settingsKeyMap,
                    $domainsKeyMap,
                ])
                ->setPublic(false)
                ->addTag('settings_manager.provider', [
                    'provider' => SettingsProviderInterface::DEFAULT_PROVIDER,
                    'priority' => $config['settings_config']['priority'],
                ]);
        }

        $container
            ->register('settings_manager.provider.config.decorating', DecoratingInMemorySettingsProvider::class)
            ->setArguments([new Reference('settings_manager.provider.config.decorating.inner')])
            ->setDecoratedService('settings_manager.provider.config')
            ->setPublic(false);
    }

    private function loadSettingsFromFiles(array $files, ContainerBuilder $container): array
    {
        $configuration = new Configuration();
        $settings = [];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $fileContents = Yaml::parseFile(
                    $file,
                    Yaml::PARSE_CONSTANT | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
                );
                $processedContents = $this->processConfiguration(
                    $configuration,
                    ['helis_settings_manager' => ['settings' => $fileContents]]
                );

                $settings = $this->mergeSettings($settings, $processedContents['settings']);
                $container->addResource(new FileResource($file));
            }
        }

        return $settings;
    }

    private function loadDataCollector(ContainerBuilder $container): void
    {
        $container
            ->register(SettingsCollector::class, SettingsCollector::class)
            ->setArgument('$settingsStore', new Reference(SettingsStore::class))
            ->setPublic(false)
            ->addTag('data_collector', [
                'id' => 'settings_manager.settings_collector',
                'template' => '@HelisSettingsManager/profiler/profiler.html.twig',
            ]);
    }

    private function loadListeners(array $config, ContainerBuilder $container): void
    {
        if ($config['controller']['enabled']) {
            $container
                ->register(SwitchableControllerSubscriber::class, SwitchableControllerSubscriber::class)
                ->setArgument('$settingsRouter', new Reference(SettingsRouter::class))
                ->setPublic(false)
                ->addTag('kernel.event_subscriber');
        }

        if ($config['command']['enabled']) {
            $container
                ->register(SwitchableCommandSubscriber::class, SwitchableCommandSubscriber::class)
                ->setArgument('$settingsRouter', new Reference(SettingsRouter::class))
                ->setPublic(false)
                ->addTag('kernel.event_subscriber');
        }
    }

    private function mergeSettings(array $settingsA, array $settingsB): array
    {
        $settingsA = array_column($settingsA, null, 'name');
        $settingsB = array_column($settingsB, null, 'name');
        $settings = array_merge($settingsA, $settingsB);

        return array_values($settings);
    }
}
