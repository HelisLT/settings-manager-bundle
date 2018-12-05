<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\SymmetricKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version2;
use ParagonIE\Paseto\ProtocolCollection;
use Psr\Log\LoggerAwareTrait;
use Helis\SettingsManagerBundle\Provider\Traits\WritableProviderTrait;
use Symfony\Component\Serializer\SerializerInterface;

class CookieSettingsProvider extends AbstractCookieSettingsProvider
{
    use LoggerAwareTrait, WritableProviderTrait;

    private $serializer;
    private $symmetricKeyMaterial;
    private $cookieName;

    public function __construct(
        SerializerInterface $serializer,
        string $symmetricKeyMaterial = 'GuxH2igWOvGBSk3cpeL300Fzv9JiAtvC',
        string $cookieName = 'stn'
    ) {
        $this->serializer = $serializer;
        $this->symmetricKeyMaterial = $symmetricKeyMaterial;

        parent::__construct($serializer, $cookieName, 86400, 'settings_manager', 'cookie_provider', '/');
    }

    protected function getTokenParser(): Parser
    {
        return Parser::getLocal(new SymmetricKey($this->symmetricKeyMaterial), ProtocolCollection::v2());
    }

    protected function getTokenBuilder(): Builder
    {
        return Builder::getLocal(new SymmetricKey($this->symmetricKeyMaterial), new Version2());
    }
}
