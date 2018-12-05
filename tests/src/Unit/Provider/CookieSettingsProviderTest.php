<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Provider;

use Helis\SettingsManagerBundle\Provider\CookieSettingsProvider;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;

class CookieSettingsProviderTest extends AbstractCookieSettingsProviderTest
{
    protected function createProvider(): SettingsProviderInterface
    {
        return new CookieSettingsProvider($this->serializer, 'YELLOW SUBMARINE, BLACK WIZARDRY', $this->cookieName);
    }
}
