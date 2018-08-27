<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings\Switchable;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;

interface SwitchableControllerInterface
{
    public static function isControllerEnabled(SettingsRouter $router): bool;
}
