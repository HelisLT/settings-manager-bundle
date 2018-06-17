<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use Helis\SettingsManagerBundle\Provider\DecoratingRedisSettingsProvider;
use Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider;
use Helis\SettingsManagerBundle\Tests\Functional\Entity\Setting;
use Helis\SettingsManagerBundle\Tests\Functional\Entity\Tag;

class RedisDoctrineOrmSettingsProviderTest extends DecoratingPredisSettingsProviderTest
{
    protected function setUp()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('phpredis extension required');
        }

        $this->redis = new \Redis();

        if (@$this->redis->connect(getenv('REDIS_HOST'), (int) getenv('REDIS_PORT'), 1.0) === false) {
            $this->markTestSkipped('Running redis server required');
        }

        $container = $this->getContainer();

        $this->provider = new DecoratingRedisSettingsProvider(
            new DoctrineOrmSettingsProvider(
                $container->get('doctrine.orm.default_entity_manager'),
                Setting::class,
                Tag::class
            ),
            $this->redis,
            $container->get('test.settings_manager.serializer')
        );
    }
}
