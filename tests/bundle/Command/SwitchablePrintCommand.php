<?php

declare(strict_types=1);


namespace App\Command;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Helis\SettingsManagerBundle\Settings\Switchable\SwitchableCommandInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SwitchablePrintCommand extends Command implements SwitchableCommandInterface
{
    public static function isCommandEnabled(SettingsRouter $router): bool
    {
        return $router->getBool('switchable_command_enabled');
    }

    protected function configure(): void
    {
        $this
            ->setName('switchable:print')
            ->addArgument('value', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write($input->getArgument('value'));

        return 0;
    }
}
