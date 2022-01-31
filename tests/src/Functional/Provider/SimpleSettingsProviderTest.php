<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Provider\SimpleSettingsProvider;

class SimpleSettingsProviderTest extends AbstractSettingsProviderTest
{
    protected function createProvider(): SettingsProviderInterface
    {
        return new SimpleSettingsProvider();
    }
}
