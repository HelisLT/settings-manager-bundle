<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Provider;

use Helis\SettingsManagerBundle\Provider\AbstractCookieSettingsProvider;
use Helis\SettingsManagerBundle\Provider\CookieSettingsProvider;

class CookieSettingsProviderTest extends AbstractCookieSettingsProviderTest
{
    protected function createProvider(): AbstractCookieSettingsProvider
    {
        return new CookieSettingsProvider($this->serializer, 'YELLOW SUBMARINE, BLACK WIZARDRY', $this->cookieName);
    }
}
