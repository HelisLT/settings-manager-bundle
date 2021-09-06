<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\Traits\WritableProviderTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractBaseCookieSettingsProvider extends SimpleSettingsProvider implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use WritableProviderTrait;

    protected $serializer;
    protected $cookieName;
    protected $ttl;
    protected $issuer;
    protected $subject;
    protected $cookiePath;
    protected $cookieDomain;
    protected $changed;

    public function __construct(
        SerializerInterface $serializer,
        string $cookieName,
        int $ttl = 86400,
        string $issuer = 'settings_manager',
        string $subject = 'settings',
        string $cookiePath = '/'
    ) {
        $this->serializer = $serializer;
        $this->cookieName = $cookieName;
        $this->ttl = $ttl;
        $this->issuer = $issuer;
        $this->subject = $subject;
        $this->cookiePath = $cookiePath;
        $this->changed = false;

        parent::__construct([]);
    }

    /**
     * @return SettingModel[]
     */
    abstract protected function parseToken(string $rawToken): array;

    /**
     * @param SettingModel[] $settings
     */
    abstract protected function buildToken(array $settings): string;

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMasterRequest()
            || ($rawToken = $event->getRequest()->cookies->get($this->cookieName)) === null
        ) {
            return;
        }

        $this->settings = $this->parseToken($rawToken);
    }

    public function onKernelResponse(ResponseEvent $event): void
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

        $token = $this->buildToken($this->settings);

        if (empty($token)) {
            $this->logger && $this->logger->error(
                sprintf('%s: failed to build token', (new \ReflectionObject($this))->getShortName())
            );

            return;
        }

        $event
            ->getResponse()
            ->headers
            ->setCookie(new Cookie(
                $this->cookieName,
                $token,
                time() + $this->ttl,
                $this->cookiePath,
                $this->cookieDomain,
                false,
                true,
                false,
                null
            ));
    }

    public function save(SettingModel $settingModel): bool
    {
        $output = parent::save($settingModel);
        $output && $this->changed = true;

        return $output;
    }

    public function delete(SettingModel $settingModel): bool
    {
        $output = parent::delete($settingModel);
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

    public function setCookiePath(string $cookiePath): void
    {
        $this->cookiePath = $cookiePath;
    }

    public function setCookieDomain(?string $cookieDomain): void
    {
        $this->cookieDomain = $cookieDomain;
    }
}
