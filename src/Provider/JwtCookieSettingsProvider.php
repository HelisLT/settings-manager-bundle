<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\LocalFileReference;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\JWT\Validation\Validator;
use Symfony\Component\Serializer\SerializerInterface;

class JwtCookieSettingsProvider extends AbstractBaseCookieSettingsProvider
{
    private $publicKeyPath;
    private $privateKeyPath;

    public function __construct(
        SerializerInterface $serializer,
        string $publicKeyPath,
        string $privateKeyPath = null,
        string $cookieName = 'stn'
    ) {
        $this->publicKeyPath = $publicKeyPath;
        $this->privateKeyPath = $privateKeyPath;

        parent::__construct($serializer, $cookieName);
    }

    protected function parseToken(string $rawToken): array
    {
        $validator = new Validator();
        $constraints = [
            new ValidAt(new FrozenClock(new \DateTimeImmutable())),
            new SignedWith(new Sha256(), LocalFileReference::file($this->publicKeyPath)),
            new IssuedBy($this->issuer),
            new RelatedTo($this->subject),
        ];

        $parser = new Parser();

        try {
            $token = $parser->parse($rawToken);

            if (!$validator->validate($token, ...$constraints)) {
                throw new \Exception('Cookie token validation failed');
            }
        } catch (\Throwable $e) {
            $this->logger && $this->logger->warning(sprintf('%s: failed to parse token', (new \ReflectionObject($this))->getShortName()), [
                'sRawToken' => $rawToken,
                'sErrorMessage' => $e->getMessage(),
            ]);

            return [];
        }

        try {
            return  $this
                ->serializer
                ->deserialize($token->claims()->get('dt'), SettingModel::class.'[]', 'json');
        } catch (\Throwable $e) {
            $this->logger && $this->logger->warning(sprintf('%s: '.strtolower($e), (new \ReflectionObject($this))->getShortName()), [
                'sRawToken' => $rawToken,
            ]);

            return [];
        }
    }

    protected function buildToken(array $settings): string
    {
        if (null === $this->privateKeyPath) {
            return '';
        }

        $now = new \DateTimeImmutable();
        $expiresAt = $now->add(new \DateInterval('PT'.$this->ttl.'S'));

        $token = (new Builder())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->issuedBy($this->issuer)
            ->relatedTo($this->subject)
            ->expiresAt($expiresAt)
            ->withClaim('dt', $this->serializer->serialize($settings, 'json'))
            ->getToken(new Sha256(), LocalFileReference::file($this->privateKeyPath));

        return (string)$token;
    }
}
