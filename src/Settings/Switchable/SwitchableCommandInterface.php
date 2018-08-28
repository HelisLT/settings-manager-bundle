<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings\Switchable;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;

interface SwitchableCommandInterface
{
    public static function isCommandEnabled(SettingsRouter $router): bool;
}
