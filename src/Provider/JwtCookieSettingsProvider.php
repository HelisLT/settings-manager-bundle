<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use ReflectionObject;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

class JwtCookieSettingsProvider extends AbstractBaseCookieSettingsProvider
{
    public function __construct(
        SerializerInterface $serializer,
        private readonly string $publicKey,
        private readonly ?string $privateKey = null,
        string $cookieName = 'stn'
    ) {
        parent::__construct($serializer, $cookieName);
    }

    protected function parseToken(string $rawToken): array
    {
        $validator = new Validator();
        $constraints = [
            new LooseValidAt(new FrozenClock(new DateTimeImmutable())),
            new SignedWith(new Sha256(), $this->getKey($this->publicKey)),
            new IssuedBy($this->issuer),
            new RelatedTo($this->subject),
        ];

        $parser = new Parser(new JoseEncoder());

        try {
            /** @var Plain $token */
            $token = $parser->parse($rawToken);

            if (!$validator->validate($token, ...$constraints)) {
                throw new Exception('Cookie token validation failed');
            }
        } catch (Throwable $e) {
            $this->logger && $this->logger->warning(sprintf('%s: failed to parse token', (new ReflectionObject($this))->getShortName()), [
                'sRawToken' => $rawToken,
                'sErrorMessage' => $e->getMessage(),
            ]);

            return [];
        }

        try {
            return $this
                ->serializer
                ->deserialize($token->claims()->get('dt'), SettingModel::class.'[]', 'json');
        } catch (Throwable $e) {
            $this->logger && $this->logger->warning(sprintf('%s: '.strtolower($e->getMessage()), (new ReflectionObject($this))->getShortName()), [
                'sRawToken' => $rawToken,
            ]);

            return [];
        }
    }

    protected function buildToken(array $settings): string
    {
        if (null === $this->privateKey) {
            return '';
        }

        $now = new DateTimeImmutable();
        $expiresAt = $now->add(new DateInterval('PT'.$this->ttl.'S'));

        $token = (new Builder(new JoseEncoder(), ChainedFormatter::default()))
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->issuedBy($this->issuer)
            ->relatedTo($this->subject)
            ->expiresAt($expiresAt)
            ->withClaim('dt', $this->serializer->serialize($settings, 'json'))
            ->getToken(new Sha256(), $this->getKey($this->privateKey));

        return $token->toString();
    }

    private function getKey(string $content): InMemory
    {
        if (str_starts_with($content, 'file://')) {
            return InMemory::file(substr($content, 7));
        }

        return InMemory::plainText($content);
    }
}
