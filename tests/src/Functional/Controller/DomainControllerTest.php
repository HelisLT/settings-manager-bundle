<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Controller;

use App\AbstractWebTestCase;

class DomainControllerTest extends AbstractWebTestCase
{
    public function testIndexAction()
    {
        $client = static::createClient();
        $this->loadFixtures([]);

        $client->request('GET', '/domains');

        $this->assertStatusCode(200, $client);
    }
}
