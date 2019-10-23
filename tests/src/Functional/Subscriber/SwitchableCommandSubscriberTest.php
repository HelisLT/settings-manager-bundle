<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Subscriber;

use App\DataFixtures\ORM\LoadSwitchableCommandData;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Test\FixturesTrait;

class SwitchableCommandSubscriberTest extends WebTestCase
{
    use FixturesTrait;

    public function testSkippedCommand()
    {
        $this->markTestSkipped('CommandTester do not dispatch events.');

        $this->loadFixtures([]);

        $output = $this->runCommand('switchable:print', ['value' => 'batman']);

        $this->assertEquals(0, $output->getStatusCode());
        $this->assertContains("Command is disabled\n", $output->getDisplay());
    }

    public function testRunCommand()
    {
        $this->loadFixtures([LoadSwitchableCommandData::class]);

        $output = $this->runCommand('switchable:print', ['value' => 'batman']);

        $this->assertEquals(0, $output->getStatusCode());
        $this->assertContains('batman', $output->getDisplay(), 'Enabled command output does not match');
    }
}
