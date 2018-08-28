<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HelisSettingsManagerBundleTest extends WebTestCase
{
    public function testContainerLoad()
    {
        /** @var Container $container */
        $container = $this->getContainer();
        $services = $container->getServiceIds();

        $services = array_filter($services, function (string $id): bool {
            return strpos($id, 'settings_manager.') === 0
                || strpos($id, 'Helis\SettingsManagerBundle') === 0;
        });

        foreach ($services as $id) {
            $this->assertNotNull($container->get($id, ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
    }
}
