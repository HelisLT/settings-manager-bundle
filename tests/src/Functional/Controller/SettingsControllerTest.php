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

    public function testEditAction()
    {
        $client = $this->makeClient();
        $this->loadFixtures([]);

        foreach (['foo', 'tuna', 'wth_yaml', 'choice', 'integer'] as $settingName) {
            $client->request('GET', '/default/' . $settingName);
            $this->assertStatusCode(200, $client);
        }
    }
}
