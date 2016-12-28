<?php

namespace Helis\SettingsManagerBundle\Tests;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    /**
     * {@inheritdoc}
     */
    public function registerBundles()
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Symfony\Bundle\MonologBundle\MonologBundle(),
            new \Symfony\Bundle\TwigBundle\TwigBundle(),
            new \Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new \Symfony\Bundle\WebProfilerBundle\WebProfilerBundle(),
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new \Knp\Bundle\MenuBundle\KnpMenuBundle(),
            new \Helis\SettingsManagerBundle\HelisSettingsManagerBundle(),

            // for testing
            new \Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle(),
            new \Liip\FunctionalTestBundle\LiipFunctionalTestBundle(),
        ];
    }

    public function getRootDir()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return dirname(__DIR__).'/tests/var/cache/'.$this->getEnvironment();
    }

    public function getLogDir()
    {
        return dirname(__DIR__).'/tests/var/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getRootDir().'/config/config_'.$this->getEnvironment().'.yml');
    }

    /**
     * {@inheritdoc}
     */
    protected function build(ContainerBuilder $container)
    {
        if ($container->getParameter('kernel.environment') === 'test') {
            // Until liip/functional-test-bundle figures it out
            $container->addCompilerPass(new class implements CompilerPassInterface {
                public function process(ContainerBuilder $container)
                {
                    if ($container->hasDefinition('test.client')) {
                        $container
                            ->getDefinition('test.client')
                            ->setPublic(true);
                    }

                    if ($container->hasAlias('test.client')) {
                        $container
                            ->getAlias('test.client')
                            ->setPublic(true);
                    }

                    if ($container->getDefinition('settings_manager.serializer')) {
                        $container
                            ->setAlias('test.settings_manager.serializer', 'settings_manager.serializer')
                            ->setPublic(true);
                    }
                }
            });
        }
    }
}
