<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Exception;

use LogicException;

class ProviderNotFoundException extends LogicException implements SettingsException
{
    public function __construct(string $providerName)
    {
        parent::__construct("Settings provider named '$providerName' not found");
    }
}
