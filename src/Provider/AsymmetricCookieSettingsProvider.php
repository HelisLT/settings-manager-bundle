<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;


use ParagonIE\Paseto\Keys\AsymmetricPublicKey;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Serializer\SerializerInterface;
use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Exception\PasetoException;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version2;
use ParagonIE\Paseto\ProtocolCollection;
use ParagonIE\Paseto\Rules\IssuedBy;
use ParagonIE\Paseto\Rules\NotExpired;
use ParagonIE\Paseto\Rules\Subject;
use Psr\Log\LoggerAwareTrait;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\Traits\WritableProviderTrait;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\KernelEvents;

class AsymmetricCookieSettingsProvider extends SimpleSettingsProvider implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait, WritableProviderTrait;

    private $serializer;
    private $publicKeyMaterial;
    private $publicKey;
    private $privateKeyMaterial;
    private $privateKey;
    private $cookieName;

    private $cookiePath;
    private $cookieDomain;
    private $ttl;
    private $issuer;
    private $subject;
    private $footer;

    private $changed;

    public function __construct(
        SerializerInterface $serializer,
        string $privateKeyMaterial,
        string $publicKeyMaterial,
        string $cookieName = 'stn'
    ) {
        $this->serializer = $serializer;
        $this->privateKeyMaterial = $privateKeyMaterial;
        $this->publicKeyMaterial = $publicKeyMaterial;
        $this->cookieName = $cookieName;

        $this->ttl = 86400;
        $this->issuer = 'settings_manager';
        $this->subject = 'asymmetric_cookie_provider';
        $this->cookiePath = '/';

        $this->changed = false;

        parent::__construct([]);
    }

    public function save(SettingModel $settingModel): bool
    {
        $output = parent::save($settingModel);
        $output && $this->changed = true;

        return $output;
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        $output = parent::updateDomain($domainModel);
        $output && $this->changed = true;

        return $output;
    }

    public function deleteDomain(string $domainName): bool
    {
        $output = parent::deleteDomain($domainName);
        $output && $this->changed = true;

        return $output;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 15]],
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }

    public function onKernelRequest(GetResponseEvent $event): void
    {
        if (!$event->isMasterRequest()
            || ($rawToken = $event->getRequest()->cookies->get($this->cookieName)) === null
        ) {
            return;
        }

        $parser = Parser::getPublic($this->getPublicKey());
        $parser
            ->addRule(new IssuedBy($this->issuer))
            ->addRule(new Subject($this->subject))
            ->addRule(new NotExpired())
            ->setAllowedVersions(ProtocolCollection::v2());

        try {
            $token = $parser->parse($rawToken);
        } catch (PasetoException $e) {
            $this->logger && $this->logger->warning('AsymmetricCookieSettingsProvider: failed to parse token', [
                'sRawToken' => $rawToken,
                'sErrorMessage' => $e->getMessage(),
            ]);

            return;
        }

        try {
            $this->settings = $this
                ->serializer
                ->deserialize($token->get('dt'), SettingModel::class . '[]', 'json');
        } catch (PasetoException $e) {
            $this->logger && $this->logger->warning('AsymmetricCookieSettingsProvider: ' . strtolower($e), [
                'sRawToken' => $rawToken,
            ]);
        }
    }

    public function onKernelResponse(FilterResponseEvent $event): void
    {
        if (!$event->isMasterRequest() || $event->getResponse() === null || !$this->changed) {
            return;
        }

        // cache is still warm
        if (!$this->changed) {
            return;
        }

        // no settings to save
        if (empty($this->settings)) {
            // also check for a cookie if needs to be cleared
            if ($event->getRequest()->cookies->has($this->cookieName)) {
                $event->getResponse()->headers->clearCookie($this->cookieName, $this->cookiePath, $this->cookieDomain);
            }

            return;
        }

        $now = new \DateTime();
        $token = Builder::getPublic($this->getPrivateKey(), new Version2());

        $token
            ->setIssuedAt($now)
            ->setNotBefore($now)
            ->setIssuer($this->issuer)
            ->setSubject($this->subject)
            ->setExpiration($now->add(new \DateInterval('PT' . $this->ttl . 'S')))
            ->setClaims([
                'dt' => $this->serializer->serialize($this->settings, 'json')
            ]);

        $this->footer !== null && $token->setFooter($this->footer);

        $event
            ->getResponse()
            ->headers
            ->setCookie(new Cookie($this->cookieName, (string) $token, time() + $this->ttl, $this->cookiePath, $this->cookieDomain));
    }

    private function getPublicKey(): AsymmetricPublicKey
    {
        if (null === $this->publicKey) {
            $this->publicKey = new AsymmetricPublicKey($this->publicKeyMaterial);
        }

        return $this->publicKey;
    }

    private function getPrivateKey(): AsymmetricSecretKey
    {
        if (null === $this->privateKey) {
            $this->privateKey = new AsymmetricSecretKey($this->privateKeyMaterial);
        }

        return $this->privateKey;
    }

    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    public function setIssuer(string $issuer): void
    {
        $this->issuer = $issuer;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    public function setFooter(string $footer): void
    {
        $this->footer = $footer;
    }

    public function setCookiePath(string $cookiePath): void
    {
        $this->cookiePath = $cookiePath;
    }

    public function setCookieDomain(?string $cookieDomain): void
    {
        $this->cookieDomain = $cookieDomain;
    }

}