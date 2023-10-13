<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Subscriber;

use App\AbstractWebTestCase;
use App\DataFixtures\ORM\LoadSwitchableControllerData;

class SwitchableControllerSubscriberTest extends AbstractWebTestCase
{
    public function testControllerDisabled()
    {
        $client = self::createClient();
        $this->loadFixtures([]);

        $client->request('GET', '/print/batman');

        $this->assertStatusCode(404, $client);
    }

    public function testControllerEnabled()
    {
        $client = self::createClient();
        $this->loadFixtures([LoadSwitchableControllerData::class]);

        $client->request('GET', '/print/batman');

        $this->assertStatusCode(200, $client);
        $this->assertEquals('batman', $client->getResponse()->getContent());
    }
}
