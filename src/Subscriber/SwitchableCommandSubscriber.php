<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Subscriber;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Helis\SettingsManagerBundle\Settings\Switchable\SwitchableCommandInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SwitchableCommandSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SettingsRouter $settingsRouter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::COMMAND => ['onConsoleCommand']];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof SwitchableCommandInterface
            && !$command::isCommandEnabled($this->settingsRouter)
        ) {
            $event->getOutput()->writeln('Command is disabled');
            $event->disableCommand();
        }
    }
}
