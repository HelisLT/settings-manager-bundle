<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use App\Entity\Setting;
use App\Entity\Tag;
use Helis\SettingsManagerBundle\Provider\DecoratingCacheSettingsProvider;
use Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class DecoratingCacheSettingsProviderTest extends DecoratingPredisSettingsProviderTest
{
    /** @var AdapterInterface */
    private $cache;

    private $countingProvider;

    protected function setUp(): void
    {
        $kernel = static::bootKernel();

        $namespace = 'settings_cache';
        $cacheDir = $kernel->getContainer()->getParameter('kernel.cache_dir').DIRECTORY_SEPARATOR.'pools';
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
            if (!@$this->redis->connect(getenv('REDIS_HOST'), (int)getenv('REDIS_PORT'), 1.0)) {
                $this->markTestSkipped('Running redis server required');
            }
        } catch (\RedisException $e) {
            $this->markTestSkipped('Running redis server required');
        }

        $container = $this->getContainer();

        $this->countingProvider = new CountingCallsSettingsProvider(
            new DoctrineOrmSettingsProvider(
                $container->get('doctrine.orm.default_entity_manager'),
                Setting::class,
                Tag::class
            ),
            $this->redis,
            $container->get('test.settings_manager.serializer')
        );

        return new DecoratingCacheSettingsProvider(
            $this->countingProvider,
            $container->get('test.settings_manager.serializer'),
            $this->cache,
            new LockFactory(new FlockStore()),
            0
        );
    }

    /**
     * @dataProvider dataProviderTestGetSettings
     */
    public function testGetSettings(array $domainNames, array $expectedSettingsMap)
    {
        parent::testGetSettings($domainNames, $expectedSettingsMap);

        $calls = $this->countingProvider->getCalls();

        $this->assertEquals(['getSettings' => 1], $calls);

        foreach ($expectedSettingsMap as $settingData) {
            $settingNamesCacheKey = sprintf('setting_names[%s]', $settingData[1]);
            $settingCacheKey = sprintf('setting[%s][%s]', $settingData[1], $settingData[0]);

            $settingNamesCacheItem = $this->cache->getItem($settingNamesCacheKey);
            $settingCacheItem = $this->cache->getItem($settingCacheKey);

            $this->assertTrue($settingNamesCacheItem->isHit());
            $this->assertTrue($settingCacheItem->isHit());
            $this->assertNotNull($settingNamesCacheItem->get());
            $this->assertNotNull($settingCacheItem->get());

            foreach ($settingData[4] as $tagName) {
                $taggedSettingNamesCacheKey = sprintf('tagged_setting_names[%s][%s]', $settingData[1], $tagName);
                $taggedSettingNamesCacheItem = $this->cache->getItem($taggedSettingNamesCacheKey);

                $this->assertTrue($taggedSettingNamesCacheItem->isHit());
                $this->assertNotNull($taggedSettingNamesCacheItem->get());
            }
        }
    }

    /**
     * @dataProvider dataProviderTestGetSettingsByName
     */
    public function testGetSettingsByName(array $domainNames, array $settingNames, array $expectedSettingsMap)
    {
        parent::testGetSettingsByName($domainNames, $settingNames, $expectedSettingsMap);

        $calls = $this->countingProvider->getCalls();

        $this->assertEquals(['getSettingsByName' => 1], $calls);

        $expectedNonNullKeys = [];

        foreach ($expectedSettingsMap as $settingData) {
            $cacheKey = sprintf('setting[%s][%s]', $settingData[1], $settingData[0]);
            $expectedNonNullKeys[$cacheKey] = true;

            // setting names by domain are not cached
            $settingNamesCacheKey = sprintf('setting_names[%s]', $settingData[1]);
            $settingNamesCacheItem = $this->cache->getItem($settingNamesCacheKey);

            $this->assertFalse($settingNamesCacheItem->isHit());

            // setting name by tag are not cached
            foreach ($settingData[4] as $tagName) {
                $taggedSettingNamesCacheKey = sprintf('tagged_setting_names[%s][%s]', $settingData[1], $tagName);
                $taggedSettingNamesCacheItem = $this->cache->getItem($taggedSettingNamesCacheKey);

                $this->assertFalse($taggedSettingNamesCacheItem->isHit());
            }
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

    /**
     * @dataProvider dataProviderTestGetSettingsByTag
     */
    public function testGetSettingsByTag(array $domainNames, string $tagName, array $expectedSettingsMap)
    {
        parent::testGetSettingsByTag($domainNames, $tagName, $expectedSettingsMap);

        $calls = $this->countingProvider->getCalls();

        $this->assertEquals(1, $calls['getSettingsByTag']);

        // check if cache keys built
        foreach ($expectedSettingsMap as $settingData) {
            $settingNamesCacheKey = sprintf('setting_names[%s]', $settingData[1]);
            $settingCacheKey = sprintf('setting[%s][%s]', $settingData[1], $settingData[0]);
            $taggedSettingNamesCacheKey = sprintf('tagged_setting_names[%s][%s]', $settingData[1], $tagName);

            $settingNamesCacheItem = $this->cache->getItem($settingNamesCacheKey);
            $settingCacheItem = $this->cache->getItem($settingCacheKey);
            $taggedSettingNamesCacheItem = $this->cache->getItem($taggedSettingNamesCacheKey);

            $this->assertFalse($settingNamesCacheItem->isHit());
            $this->assertTrue($settingCacheItem->isHit());
            $this->assertTrue($taggedSettingNamesCacheItem->isHit());
            $this->assertNotNull($settingCacheItem->get());
            $this->assertNotNull($taggedSettingNamesCacheItem->get());
        }
    }
}
