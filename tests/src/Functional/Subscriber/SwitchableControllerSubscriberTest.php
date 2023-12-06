<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Subscriber;

use App\AbstractWebTestCase;
use App\DataFixtures\ORM\LoadSwitchableControllerData;

class SwitchableControllerSubscriberTest extends AbstractWebTestCase
{
    public function testControllerDisabled(): void
    {
        $client = static::createClient();
        $this->loadFixtures([]);

        $client->request('GET', '/print/batman');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testControllerEnabled(): void
    {
        $client = static::createClient();
        $this->loadFixtures([LoadSwitchableControllerData::class]);

        $client->request('GET', '/print/batman');

        $this->assertResponseStatusCodeSame(200);
        $this->assertEquals('batman', $client->getResponse()->getContent());
    }
}
