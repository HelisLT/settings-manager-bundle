<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use App\Entity\Setting;
use App\Entity\Tag;
use Helis\SettingsManagerBundle\Provider\DecoratingCacheSettingsProvider;
use Helis\SettingsManagerBundle\Provider\DecoratingRedisSettingsProvider;
use Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;

class DecoratingCacheSettingsProviderTest extends DecoratingPredisSettingsProviderTest
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

        $namespace = 'settings_cache';
        $cacheDir = $container->getParameter('kernel.cache_dir') . DIRECTORY_SEPARATOR . 'pools';

        return new DecoratingCacheSettingsProvider(
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
            new FilesystemAdapter($namespace, 0, $cacheDir),
            new Factory(new FlockStore()),
            0
        );
    }
}
