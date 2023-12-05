<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HelisSettingsManagerBundleTest extends KernelTestCase
{
    public function testContainerLoad(): void
    {
        $container = static::getContainer();
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
