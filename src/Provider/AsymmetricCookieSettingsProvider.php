<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;


use ParagonIE\Paseto\Keys\AsymmetricPublicKey;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use Symfony\Component\Serializer\SerializerInterface;
use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version2;
use ParagonIE\Paseto\ProtocolCollection;
use Psr\Log\LoggerAwareTrait;
use Helis\SettingsManagerBundle\Provider\Traits\WritableProviderTrait;

class AsymmetricCookieSettingsProvider extends AbstractCookieSettingsProvider
{
    use LoggerAwareTrait, WritableProviderTrait;

    private $serializer;
    private $publicKeyMaterial;
    private $privateKeyMaterial;
    private $cookieName;

    public function __construct(
        SerializerInterface $serializer,
        string $privateKeyMaterial,
        string $publicKeyMaterial,
        string $cookieName = 'stn'
    ) {
        $this->privateKeyMaterial = $privateKeyMaterial;
        $this->publicKeyMaterial = $publicKeyMaterial;

        parent::__construct($serializer, $cookieName, 86400, 'settings_manager', 'asymmetric_cookie_provider', '/');
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