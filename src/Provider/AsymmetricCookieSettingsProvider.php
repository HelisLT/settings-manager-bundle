<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\AsymmetricPublicKey;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version2;
use ParagonIE\Paseto\ProtocolCollection;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @deprecated Please use AsymmetricPasetoCookieSettingsProvider instead
 */
class AsymmetricCookieSettingsProvider extends AbstractPasetoCookieSettingsProvider
{
    protected $publicKeyMaterial;
    protected $privateKeyMaterial;

    public function __construct(
        SerializerInterface $serializer,
        string $privateKeyMaterial,
        string $publicKeyMaterial,
        string $cookieName = 'stn'
    ) {
        $this->privateKeyMaterial = $privateKeyMaterial;
        $this->publicKeyMaterial = $publicKeyMaterial;

        parent::__construct($serializer, $cookieName);
    }

    protected function getTokenParser(): Parser
    {
        return Parser::getPublic(new AsymmetricPublicKey($this->publicKeyMaterial), ProtocolCollection::v2());
    }

    protected function getTokenBuilder(): Builder
    {
        return Builder::getPublic(new AsymmetricSecretKey($this->privateKeyMaterial), new Version2());
    }
}
