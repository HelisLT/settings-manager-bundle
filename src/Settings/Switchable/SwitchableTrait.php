<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings\Switchable;

trait SwitchableTrait
{
    protected $enabled = false;

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
