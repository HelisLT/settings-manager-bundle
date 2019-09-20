<?php

use App\AppBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Helis\SettingsManagerBundle\HelisSettingsManagerBundle;
use Knp\Bundle\MenuBundle\KnpMenuBundle;
use Liip\FunctionalTestBundle\LiipFunctionalTestBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
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
            new FrameworkBundle(),
            new MonologBundle(),
            new TwigBundle(),
            new SecurityBundle(),
            new WebProfilerBundle(),
            new DoctrineBundle(),
            new KnpMenuBundle(),
            new HelisSettingsManagerBundle(),

            // for testing
            new DoctrineFixturesBundle(),
            new LiipFunctionalTestBundle(),
            new AppBundle(),
        ];
    }

    public function getRootDir()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return dirname(__DIR__) . '/app/var/cache/' . $this->getEnvironment();
    }

    public function getLogDir()
    {
        return dirname(__DIR__) . '/app/var/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getRootDir() . '/config/config_' . $this->getEnvironment() . '.yml');
    }

    protected function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface
        {
            public function process(ContainerBuilder $container)
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if ($this->supports($id)) {
                        $definition->setPublic(true);
                    }
                }

                foreach ($container->getAliases() as $id => $alias) {
                    if ($this->supports($id)) {
                        $alias->setPublic(true);
                    }
                }
            }

            private function supports(string $id): bool
            {
                return strpos($id, 'settings_manager.') === 0
                    || strpos($id, 'Helis\SettingsManagerBundle') === 0;
            }
        });
    }
}
