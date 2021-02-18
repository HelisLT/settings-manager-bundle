<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Event\ConfigureMenuEvent;
use Helis\SettingsManagerBundle\Event\SettingEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;

class EventManager implements EventManagerInterface
{
    protected $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function dispatch(string $eventName, SettingEvent $event): void
    {
        if ($this->eventDispatcher instanceof ContractsEventDispatcherInterface) {
            // sf >= 4.3
            $this->eventDispatcher->dispatch($event, $eventName);
            $this->eventDispatcher->dispatch($event, $eventName.'.'.strtolower($event->getSetting()->getName()));
        } else {
            // sf < 4.2 legacy
            $this->eventDispatcher->dispatch($eventName, $event);
            $this->eventDispatcher->dispatch($eventName.'.'.strtolower($event->getSetting()->getName()), $event);
        }
    }

    public function dispatchConfigureMenu(string $eventName, ConfigureMenuEvent $event): void
    {
        if ($this->eventDispatcher instanceof ContractsEventDispatcherInterface) {
            // sf >= 4.3
            $this->eventDispatcher->dispatch($event, $eventName);
        } else {
            // sf < 4.2 legacy
            $this->eventDispatcher->dispatch($eventName, $event);
        }
    }
}
