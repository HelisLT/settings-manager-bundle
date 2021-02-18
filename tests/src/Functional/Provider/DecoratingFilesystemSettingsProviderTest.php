<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use App\Entity\Setting;
use App\Entity\Tag;
use Helis\SettingsManagerBundle\Provider\DecoratingFilesystemSettingsProvider;
use Helis\SettingsManagerBundle\Provider\DecoratingRedisSettingsProvider;
use Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;

class DecoratingFilesystemSettingsProviderTest extends DecoratingPredisSettingsProviderTest
{
    protected function createProvider(): SettingsProviderInterface
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('phpredis extension required');
        }

        $this->redis = new \Redis();

        try {
            if (!@$this->redis->connect(getenv('REDIS_HOST'), (int) getenv('REDIS_PORT'), 1.0)) {
                $this->markTestSkipped('Running redis server required');
            }
        } catch (\RedisException $e) {
            $this->markTestSkipped('Running redis server required');
        }

        $container = $this->getContainer();

        return new DecoratingFilesystemSettingsProvider(
            new DecoratingRedisSettingsProvider(
                new DoctrineOrmSettingsProvider(
                    $container->get('doctrine.orm.default_entity_manager'),
                    Setting::class,
                    Tag::class
                ),
                $this->redis,
                $container->get('test.settings_manager.serializer')
            ),
            $container->get('test.settings_manager.serializer'),
            'test_settings_cache',
            0,
            false
        );
    }
}