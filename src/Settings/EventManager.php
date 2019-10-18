<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Event\SettingEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventManager implements EventManagerInterface
{
    protected $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function dispatch(string $eventName, SettingEvent $event): void
    {
        $this->eventDispatcher->dispatch($eventName, $event);
        $this->eventDispatcher->dispatch($eventName.'.'.strtolower($event->getSetting()->getName()), $event);
    }
}
