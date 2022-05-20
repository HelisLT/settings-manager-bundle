<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Controller;

use App\AbstractWebTestCase;

class SettingsControllerTest extends AbstractWebTestCase
{
    public function testIndexAction()
    {
        $client = $this->makeClient();
        $this->loadFixtures([]);

        $client->request('GET', '/');

        $this->assertStatusCode(200, $client);
    }
}
