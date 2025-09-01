<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\AbstractBaseCookieSettingsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractCookieSettingsProviderTest extends TestCase
{
    /**
     * @var AbstractBaseCookieSettingsProvider
     */
    protected $provider;
    protected $serializer;
    protected $cookieName;

    protected function setUp(): void
    {
        $this->cookieName = 'Orange';
        $this->serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $this->provider = $this->createProvider();
    }

    abstract protected function createProvider(): AbstractBaseCookieSettingsProvider;

    public function testOnKernelResponseNothingChanged(): void
    {
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            1,
            $response = new Response(),
        );

        $this->provider->onKernelResponse($event);
        $this->assertCount(0, $response->headers->getCookies());
    }

    public function testOnKernelResponse(): Cookie
    {
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            1,
            $response = new Response(),
        );

        $settingStub = $this->createMock(SettingModel::class);

        $this
            ->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with([$settingStub], 'json')
            ->willReturn('serialized_settings');

        $this->provider->save($settingStub);
        $this->provider->onKernelResponse($event);

        $this->assertCount(1, $cookies = $response->headers->getCookies());

        $cookie = $cookies[0];
        $this->assertEquals($this->cookieName, $cookie->getName(), 'Cookie name does not match');
        $this->assertNotEmpty($cookie->getValue(), 'Cookie value should not be empty');
        $this->assertGreaterThan(time(), $cookie->getExpiresTime());

        return $cookie;
    }

    /**
     * @depends testOnKernelResponse
     */
    public function testOnKernelRequest(Cookie $cookie): void
    {
        $eventMock = $this
            ->getMockBuilder(RequestEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request([], [], [], [$cookie->getName() => $cookie->getValue()]);

        $domainStub = $this->createMock(DomainModel::class);
        $domainStub->method('getName')->willReturn('woo');
        $domainStub->method('isEnabled')->willReturn(true);

        $settingStub = $this->createMock(SettingModel::class);
        $settingStub->method('getDomain')->willReturn($domainStub);

        $eventMock->expects($this->once())->method('isMainRequest')->willReturn(true);
        $eventMock->expects($this->once())->method('getRequest')->willReturn($request);
        $this
            ->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with('serialized_settings', SettingModel::class.'[]', 'json')
            ->willReturn([$settingStub]);

        $this->provider->onKernelRequest($eventMock);

        $this->assertCount(1, $domains = $this->provider->getDomains());
        $this->assertEquals('woo', $domains[0]->getName());
    }

    public function testOnKernelRequestWithoutCookie(): void
    {
        $eventMock = $this
            ->getMockBuilder(RequestEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock->expects($this->once())->method('isMainRequest')->willReturn(true);
        $eventMock->expects($this->once())->method('getRequest')->willReturn(new Request());
        $this->serializer->expects($this->never())->method('deserialize');

        $this->provider->onKernelRequest($eventMock);
    }

    public function testReset(): void
    {
        $domainStub = $this->createMock(DomainModel::class);
        $domainStub->method('getName')->willReturn('woo');
        $domainStub->method('isEnabled')->willReturn(true);

        $settingStub = $this->createMock(SettingModel::class);
        $settingStub->method('getDomain')->willReturn($domainStub);
        $this->provider->save($settingStub);

        $this->assertNotEmpty($this->provider->getDomains(), 'Provider should have domains before reset');

        $this->provider->reset();

        $this->assertEmpty($this->provider->getDomains(), 'Provider domains should be empty after reset');
    }
}
