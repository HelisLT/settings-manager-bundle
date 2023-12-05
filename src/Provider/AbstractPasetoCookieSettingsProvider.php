<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use DateInterval;
use DateTime;
use Helis\SettingsManagerBundle\Model\SettingModel;
use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Exception\PasetoException;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Rules\IssuedBy;
use ParagonIE\Paseto\Rules\NotExpired;
use ParagonIE\Paseto\Rules\Subject;
use ReflectionObject;

abstract class AbstractPasetoCookieSettingsProvider extends AbstractBaseCookieSettingsProvider
{
    private ?string $footer = null;

    abstract protected function getTokenParser(): Parser;

    abstract protected function getTokenBuilder(): Builder;

    protected function parseToken(string $rawToken): array
    {
        $parser = $this->getTokenParser();
        $parser
            ->addRule(new IssuedBy($this->issuer))
            ->addRule(new Subject($this->subject))
            ->addRule(new NotExpired());

        try {
            $token = $parser->parse($rawToken);
        } catch (PasetoException $e) {
            $this->logger && $this->logger->warning(sprintf('%s: failed to parse token', (new ReflectionObject($this))->getShortName()), [
                'sRawToken' => $rawToken,
                'sErrorMessage' => $e->getMessage(),
            ]);

            return [];
        }

        try {
            return $this
                ->serializer
                ->deserialize($token->get('dt'), SettingModel::class.'[]', 'json');
        } catch (PasetoException $e) {
            $this->logger && $this->logger->warning(sprintf('%s: '.strtolower((string) $e), (new ReflectionObject($this))->getShortName()), [
                'sRawToken' => $rawToken,
            ]);

            return [];
        }
    }

    protected function buildToken(array $settings): string
    {
        $now = new DateTime();

        $token = $this->getTokenBuilder();
        $token
            ->setIssuedAt($now)
            ->setNotBefore($now)
            ->setIssuer($this->issuer)
            ->setSubject($this->subject)
            ->setExpiration($now->add(new DateInterval('PT'.$this->ttl.'S')))
            ->setClaims([
                'dt' => $this->serializer->serialize($settings, 'json'),
            ]);

        if ($this->footer !== null) {
            $token->setFooter($this->footer);
        }

        return (string) $token;
    }

    public function setFooter(string $footer): void
    {
        $this->footer = $footer;
    }
}
