<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use App\Entity\Setting;
use App\Entity\Tag;
use Helis\SettingsManagerBundle\Provider\DecoratingCacheSettingsProvider;
use Helis\SettingsManagerBundle\Provider\DecoratingRedisSettingsProvider;
use Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;

class DecoratingCacheSettingsProviderTest extends DecoratingPredisSettingsProviderTest
{
    /** @var AdapterInterface */
    private $cache;

    protected function setUp()
    {
        $kernel = static::bootKernel();

        $namespace = 'settings_cache';
        $cacheDir = $kernel->getContainer()->getParameter('kernel.cache_dir') . DIRECTORY_SEPARATOR . 'pools';
        $this->cache = new FilesystemAdapter($namespace, 0, $cacheDir);

        parent::setUp();

        // make sure cache is empty before each test
        $this->cache->clear();
    }

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
            $this->cache,
            new Factory(new FlockStore()),
            0
        );
    }

    /**
     * @dataProvider dataProviderTestGetSettingsByName
     */
    public function testGetSettingsByName(array $domainNames, array $settingNames, array $expectedSettingsMap)
    {
        parent::testGetSettingsByName($domainNames, $settingNames, $expectedSettingsMap);

        $expectedNonNullKeys = [];

        foreach ($expectedSettingsMap as $settingData) {
            $cacheKey = sprintf('setting[%s][%s]', $settingData[1], $settingData[0]);
            $expectedNonNullKeys[$cacheKey] = true;
        }

        // check if all domainName, settingName pairs cached
        foreach ($domainNames as $domainName) {
            foreach ($settingNames as $settingName) {
                $cacheKey = sprintf('setting[%s][%s]', $domainName, $settingName);
                $cacheItem = $this->cache->getItem($cacheKey);

                $this->assertTrue($cacheItem->isHit());

                if (isset($expectedNonNullKeys[$cacheKey])) {
                    $this->assertNotNull($cacheItem->get());
                } else {
                    $this->assertNull($cacheItem->get());
                }
            }
        }
    }
}
