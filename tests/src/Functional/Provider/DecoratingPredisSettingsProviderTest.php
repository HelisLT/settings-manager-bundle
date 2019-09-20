<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use App\Entity\Setting;
use App\Entity\Tag;
use Helis\SettingsManagerBundle\Provider\DecoratingPredisSettingsProvider;
use Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Predis\Client;
use Predis\CommunicationException;
use Redis;

class DecoratingPredisSettingsProviderTest extends AbstractSettingsProviderTest
{
    /**
     * @var Client|Redis
     */
    protected $redis;

    protected function setUp()
    {
        $this->loadFixtures([]);

        parent::setUp();
    }

    protected function createProvider(): SettingsProviderInterface
    {
        $this->redis = new Client(
            ['host' => getenv('REDIS_HOST'), 'port' => getenv('REDIS_PORT')],
            ['parameters' => ['database' => 0, 'timeout' => 1.0]]
        );

        try {
            $this->redis->ping();
        } catch (CommunicationException $e) {
            $this->markTestSkipped('Running redis server required');
        }

        $container = $this->getContainer();

        return new DecoratingPredisSettingsProvider(
            new DoctrineOrmSettingsProvider(
                $container->get('doctrine.orm.default_entity_manager'),
                Setting::class,
                Tag::class
            ),
            $this->redis,
            $container->get('test.settings_manager.serializer')
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $this->redis->flushdb();
        parent::tearDown();
    }
}
