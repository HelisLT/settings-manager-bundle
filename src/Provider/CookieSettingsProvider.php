<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use ParagonIE\Paseto\Exception\PasetoException;
use ParagonIE\Paseto\JsonToken;
use ParagonIE\Paseto\Keys\AsymmetricPublicKey;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version1;
use ParagonIE\Paseto\Rules\IssuedBy;
use ParagonIE\Paseto\Rules\NotExpired;
use ParagonIE\Paseto\Rules\Subject;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\Traits\WritableProviderTrait;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

class CookieSettingsProvider implements SettingsProviderInterface, EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait, WritableProviderTrait;

    private const COOKIE_NAME = 'stn';
    private const COOKIE_ID = 'sti';

    private $serializer;
    private $adapter;
    private $ttl;
    private $issuer = 'settings_manager';
    private $subject = 'cookie_provider';

    private $changed = false;
    private $domains;

    public function __construct(SerializerInterface $serializer, AdapterInterface $adapter)
    {
        $this->serializer = $serializer;
        $this->adapter = $adapter;
        $this->ttl = 86400;
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
        $this->changed = true;

        return true;
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        $this->domains[$domainModel->getName()] = $domainModel;
        $this->changed = true;

        return true;
    }

    public function deleteDomain(string $domainName): bool
    {
        if (!isset($this->domains[$domainName])) {
            return false;
        }

        unset($this->domains[$domainName]);
        $this->changed = true;

        return true;
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
            || ($rawToken = $event->getRequest()->cookies->get(self::COOKIE_NAME)) === null
        ) {
            return;
        }

        $id = $this->tryGetCookieId($event->getRequest());
        if ($id === null) {
            return;
        }

        $item = $this->adapter->getItem($id);
        if (!$item->isHit()) {
            return;
        }

        $parser = Parser::getPublic(new AsymmetricPublicKey($item->get(), 'v1'));
        $parser
            ->addRule(new IssuedBy($this->issuer))
            ->addRule(new Subject($this->subject))
            ->addRule(new NotExpired());

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

        $currentTime = time();
        $id = $this->getCookieId($event->getRequest(), $event->getResponse(), $currentTime);
        $item = $this->adapter->getItem($id);

        // cache is still warm
        if (!$this->changed && $item->isHit()) {
            return;
        }

        // no settings to save
        if (empty($this->domains)) {
            // also check for a cookie if needs to be cleared
            if ($event->getRequest()->cookies->has(self::COOKIE_NAME)) {
                $event->getResponse()->headers->clearCookie(self::COOKIE_NAME);
            }

            return;
        }

        $keypair = Version1::getRsa()->createKey(2048);

        // cookie setup
        $privateKey = new AsymmetricSecretKey($keypair['privatekey'], Version1::HEADER);
        $token = JsonToken::getPublic($privateKey, 'v1');
        $token
            ->setIssuedAt(new \DateTime())
            ->setNotBefore(new \DateTime())
            ->setIssuer($this->issuer)
            ->setSubject($this->subject)
            ->set('dt', $this->serializer->serialize($this->domains, 'json'))
            ->setExpiration((new \DateTime())->add(new \DateInterval('PT' . $this->ttl . 'S')));

        $event
            ->getResponse()
            ->headers
            ->setCookie(new Cookie(self::COOKIE_NAME, (string) $token, $currentTime + $this->ttl));

        $item->set($keypair['publickey'])->expiresAfter($this->ttl);
        $this->adapter->save($item);
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

    private function tryGetCookieId(Request $request): ?string
    {
        return $request->cookies->get(self::COOKIE_ID, null);
    }

    private function getCookieId(Request $request, Response $response, int $currentTime): string
    {
        $id = $this->tryGetCookieId($request);

        if ($id === null) {
            $id = bin2hex(random_bytes(6));
            $response
                ->headers
                ->setCookie(new Cookie(self::COOKIE_ID, (string) $id, $currentTime + $this->ttl));
        }

        return $id;
    }
}
