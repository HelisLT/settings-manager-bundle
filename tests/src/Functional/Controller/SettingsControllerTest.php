<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Controller;

use App\AbstractWebTestCase;

class SettingsControllerTest extends AbstractWebTestCase
{
    public function testIndexAction(): void
    {
        $client = static::createClient();
        $this->loadFixtures([]);

        $client->request('GET', '/');

        $this->assertResponseStatusCodeSame(200);
    }

    public function testEditAction(): void
    {
        $client = static::createClient();
        $this->loadFixtures([]);

        foreach (['foo', 'tuna', 'wth_yaml', 'choice', 'integer'] as $settingName) {
            $client->request('GET', '/default/'.$settingName);
            $this->assertResponseStatusCodeSame(200);
        }
    }
}
