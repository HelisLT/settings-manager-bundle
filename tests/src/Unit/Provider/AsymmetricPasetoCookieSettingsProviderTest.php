<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Provider;

use Helis\SettingsManagerBundle\Provider\AbstractBaseCookieSettingsProvider;
use Helis\SettingsManagerBundle\Provider\AsymmetricPasetoCookieSettingsProvider;
use ParagonIE\Paseto\Protocol\Version2;

class AsymmetricPasetoCookieSettingsProviderTest extends AbstractCookieSettingsProviderTest
{
    private static $asymmetricKey = null;

    protected function createProvider(): AbstractBaseCookieSettingsProvider
    {
        //make separate encoding and decoding tests reuse same key pair
        if (null === self::$asymmetricKey) {
            self::$asymmetricKey = Version2::generateAsymmetricSecretKey();
        }

        return new AsymmetricPasetoCookieSettingsProvider(
            $this->serializer,
            self::$asymmetricKey->raw(),
            self::$asymmetricKey->getPublicKey()->raw(),
            $this->cookieName
        );
    }
}
