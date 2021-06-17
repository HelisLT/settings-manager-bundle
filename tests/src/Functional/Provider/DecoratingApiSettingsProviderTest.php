<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use App\Client\ApiSettingsProviderClient;
use App\Entity\Setting;
use App\Entity\Tag;
use Helis\SettingsManagerBundle\Provider\DecoratingApiSettingsProvider;
use Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Provider\SimpleSettingsProvider;

class DecoratingApiSettingsProviderTest extends AbstractSettingsProviderTest
{
    protected function createProvider(): SettingsProviderInterface
    {
        $container = $this->getContainer();

        return new DecoratingApiSettingsProvider(
            new DoctrineOrmSettingsProvider(
                $container->get('doctrine.orm.default_entity_manager'),
                Setting::class,
                Tag::class
            ),
            new ApiSettingsProviderClient(
                new SimpleSettingsProvider()
            )
        );
    }
}
