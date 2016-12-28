<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider\Factory;

use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;

interface ProviderFactoryInterface
{
    public function get(): SettingsProviderInterface;
}
