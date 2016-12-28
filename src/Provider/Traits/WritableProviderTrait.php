<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider\Traits;

trait WritableProviderTrait
{
    public function isReadOnly(): bool
    {
        return false;
    }
}
