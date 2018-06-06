<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Exception\PasetoException;
use ParagonIE\Paseto\Keys\SymmetricKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version2;
use ParagonIE\Paseto\ProtocolCollection;
use ParagonIE\Paseto\Purpose;
use ParagonIE\Paseto\Rules\IssuedBy;
use ParagonIE\Paseto\Rules\NotExpired;
use ParagonIE\Paseto\Rules\Subject;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\Traits\WritableProviderTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

class CookieSettingsProvider implements SettingsProviderInterface, EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait, WritableProviderTrait;

    private $serializer;
    private $symmetricKeyMaterial;
    private $cookieName;
    private $symmetricKey;
    private $ttl;
    private $issuer;
    private $subject;
    private $footer;

    private $changed;
    private $domains;

    public function __construct(
        SerializerInterface $serializer,
        string $symmetricKeyMaterial = 'GuxH2igWOvGBSk3cpeL300Fzv9JiAtvC',
        string $cookieName = 'stn'
    ) {
        $this->serializer = $serializer;
        $this->symmetricKeyMaterial = $symmetricKeyMaterial;
        $this->cookieName = $cookieName;
        $this->ttl = 86400;
        $this->issuer = 'settings_manager';
        $this->subject = 'cookie_provider';

        $this->changed = false;
        $this->domains = [];
    }

    public function getSettings(array $domainNames): array
    {
        return [];
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        return [];
    }

    public function delete(SettingModel $settingModel): bool
    {
        return false;
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        if ($onlyEnabled) {
            return array_filter($this->domains, function (DomainModel $model) {
                return $model->isEnabled();
            });
        }

        return $this->domains;
    }

    public function save(SettingModel $settingModel): bool
    {
        $this->domains[$settingModel->getDomain()->getName()] = $settingModel->getDomain();
        return $this->changed = true;
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        $this->domains[$domainModel->getName()] = $domainModel;
        return $this->changed = true;
    }

    public function deleteDomain(string $domainName): bool
    {
        if (!isset($this->domains[$domainName])) {
            return false;
        }

        unset($this->domains[$domainName]);
        return $this->changed = true;
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

        $parser = Parser::getLocal($this->getSharedKey(), ProtocolCollection::v2());
        $parser
            ->addRule(new IssuedBy($this->issuer))
            ->addRule(new Subject($this->subject))
            ->addRule(new NotExpired())
            ->setKey($this->getSharedKey())
            ->setPurpose(Purpose::local())
            ->setAllowedVersions(ProtocolCollection::v2());

        try {
            $token = $parser->parse($rawToken);
        } catch (PasetoException $e) {
            $this->logger && $this->logger->warning('CookieSettingsProvider: failed to parse token', [
                'sRawToken' => $rawToken,
                'sErrorMessage' => $e->getMessage(),
            ]);

            return;
        }

        try {
            $this->domains = $this
                ->serializer
                ->deserialize($token->get('dt'), DomainModel::class . '[]', 'json');
        } catch (PasetoException $e) {
            $this->logger && $this->logger->warning('CookieSettingsProvider: ' . strtolower($e), [
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
        if (empty($this->domains)) {
            // also check for a cookie if needs to be cleared
            if ($event->getRequest()->cookies->has($this->cookieName)) {
                $event->getResponse()->headers->clearCookie($this->cookieName);
            }

            return;
        }

        $now = new \DateTime();
        $token = Builder::getLocal($this->getSharedKey(), new Version2());
        $token
            ->setIssuedAt($now)
            ->setNotBefore($now)
            ->setIssuer($this->issuer)
            ->setSubject($this->subject)
            ->setExpiration($now->add(new \DateInterval('PT' . $this->ttl . 'S')))
            ->setClaims([
                'dt' => $this->serializer->serialize($this->domains, 'json')
            ]);

        $this->footer !== null && $token->setFooter($this->footer);

        $event
            ->getResponse()
            ->headers
            ->setCookie(new Cookie($this->cookieName, (string) $token, time() + $this->ttl));
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

    private function getSharedKey(): SymmetricKey
    {
        if ($this->symmetricKey === null) {
            $this->symmetricKey = new SymmetricKey($this->symmetricKeyMaterial);
        }

        return $this->symmetricKey;
    }
}
