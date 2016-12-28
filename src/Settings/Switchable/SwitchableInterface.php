<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings\Switchable;

interface SwitchableInterface
{
    public function setEnabled(bool $enabled): void;

    public function isEnabled(): bool;
}
