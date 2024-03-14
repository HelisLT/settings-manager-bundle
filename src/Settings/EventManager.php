<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Event\ConfigureMenuEvent;
use Helis\SettingsManagerBundle\Event\SettingEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventManager implements EventManagerInterface
{
    public function __construct(protected EventDispatcherInterface $eventDispatcher)
    {
    }

    public function dispatch(string $eventName, SettingEvent $event): void
    {
        $this->eventDispatcher->dispatch($event, $eventName);
        $this->eventDispatcher->dispatch($event, $eventName.'.'.strtolower((string) $event->getSetting()->getName()));
    }

    public function dispatchConfigureMenu(string $eventName, ConfigureMenuEvent $event): void
    {
        $this->eventDispatcher->dispatch($event, $eventName);
    }
}
