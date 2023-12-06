<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Subscriber;

use App\AbstractWebTestCase;
use App\DataFixtures\ORM\LoadSwitchableCommandData;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

class SwitchableCommandSubscriberTest extends AbstractWebTestCase
{
    public function testSkippedCommand(): void
    {
        $this->loadFixtures([]);

        $input = new ArrayInput([
            'command' => 'switchable:print',
            'value' => 'batman',
        ]);
        $output = new StreamOutput(fopen('php://memory', 'w', false));

        $application = new Application(static::$kernel);
        $statusCode = $application->doRun($input, $output);
        $this->assertEquals(113, $statusCode);

        rewind($output->getStream());
        $display = stream_get_contents($output->getStream());
        $this->assertStringContainsString('Command is disabled', $display);
    }

    public function testRunCommand(): void
    {
        $this->loadFixtures([LoadSwitchableCommandData::class]);

        $application = new Application(static::$kernel);
        $command = $application->find('switchable:print');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['value' => 'batman']);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('batman', $commandTester->getDisplay(), 'Enabled command output does not match');
    }
}
