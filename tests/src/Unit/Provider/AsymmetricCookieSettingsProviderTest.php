<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Provider;

use Helis\SettingsManagerBundle\Provider\AbstractCookieSettingsProvider;
use Helis\SettingsManagerBundle\Provider\AsymmetricCookieSettingsProvider;
use ParagonIE\Paseto\Protocol\Version2;

class AsymmetricCookieSettingsProviderTest extends AbstractCookieSettingsProviderTest
{
    private static $asymmetricKey = null;

    protected function createProvider(): AbstractCookieSettingsProvider
    {
        //make separate encoding and decoding tests reuse same key pair
        if (null === self::$asymmetricKey) {
            self::$asymmetricKey = Version2::generateAsymmetricSecretKey();
        }

        return new AsymmetricCookieSettingsProvider(
            $this->serializer,
            self::$asymmetricKey->raw(),
            self::$asymmetricKey->getPublicKey()->raw(),
            $this->cookieName
        );
    }
}
