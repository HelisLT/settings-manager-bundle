<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Subscriber;

use App\DataFixtures\ORM\LoadSwitchableControllerData;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Test\FixturesTrait;

class SwitchableControllerSubscriberTest extends WebTestCase
{
    use FixturesTrait;

    public function testControllerDisabled()
    {
        $client = $this->makeClient();
        $this->loadFixtures([]);

        $client->request('GET', '/print/batman');

        $this->assertStatusCode(404, $client);
    }

    public function testControllerEnabled()
    {
        $client = $this->makeClient();
        $this->loadFixtures([LoadSwitchableControllerData::class]);

        $client->request('GET', '/print/batman');

        $this->assertStatusCode(200, $client);
        $this->assertEquals('batman', $client->getResponse()->getContent());
    }
}
