<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Event\SettingEvent;

interface EventManagerInterface
{
    public function dispatch(string $eventName, SettingEvent $event): void;
}
