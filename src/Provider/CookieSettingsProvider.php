<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\SymmetricKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version2;
use ParagonIE\Paseto\ProtocolCollection;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @deprecated Please use PasetoCookieSettingsProvider instead
 */
class CookieSettingsProvider extends AbstractPasetoCookieSettingsProvider
{
    protected $symmetricKeyMaterial;

    public function __construct(
        SerializerInterface $serializer,
        string $symmetricKeyMaterial = 'GuxH2igWOvGBSk3cpeL300Fzv9JiAtvC',
        string $cookieName = 'stn'
    ) {
        $this->symmetricKeyMaterial = $symmetricKeyMaterial;

        parent::__construct($serializer, $cookieName);
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
