<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Subscriber;

use App\DataFixtures\ORM\LoadSwitchableCommandData;
use Liip\FunctionalTestBundle\Test\WebTestCase;

class SwitchableCommandSubscriberTest extends WebTestCase
{
    public function testSkippedCommand()
    {
        $this->loadFixtures([]);

        $output = $this->runCommand('switchable:print', ['value' => 'batman']);
        $this->assertEquals("Command is disabled\n", $output);
    }

    public function testRunCommand()
    {
        $this->loadFixtures([LoadSwitchableCommandData::class]);

        $output = $this->runCommand('switchable:print', ['value' => 'batman']);
        $this->assertEquals('batman', $output, 'Enabled command output does not match');
    }
}
