<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Provider;

use Helis\SettingsManagerBundle\Provider\AbstractCookieSettingsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractCookieSettingsProviderTest extends TestCase
{
    /**
     * @var AbstractCookieSettingsProvider
     */
    protected $provider;
    protected $serializer;
    protected $cookieName;

    protected function setUp()
    {
        $this->cookieName = 'Orange';
        $this->serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $this->provider = $this->createProvider();
    }

    abstract protected function createProvider(): AbstractCookieSettingsProvider;

    public function testOnKernelResponseNothingChanged()
    {
        $eventMock = $this
            ->getMockBuilder(FilterResponseEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock->expects($this->once())->method('isMasterRequest')->willReturn(true);
        $eventMock->expects($this->once())->method('getResponse')->willReturn(new Response());
        $eventMock->expects($this->never())->method('getRequest');

        $this->provider->onKernelResponse($eventMock);
    }

    public function testOnKernelResponse()
    {
        $eventMock = $this->getMockBuilder(FilterResponseEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->once())->method('isMasterRequest')->willReturn(true);
        $eventMock->expects($this->exactly(2))->method('getResponse')->willReturn($response = new Response());
        $eventMock->expects($this->never())->method('getRequest');

        $settingStub = $this->createMock(SettingModel::class);

        $this
            ->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with([$settingStub], 'json')
            ->willReturn('serialized_settings');

        $this->provider->save($settingStub);
        $this->provider->onKernelResponse($eventMock);

        $this->assertCount(1, $cookies = $response->headers->getCookies());

        /** @var Cookie $cookie */
        $cookie = $cookies[0];
        $this->assertEquals($this->cookieName, $cookie->getName(), 'Cookie name does not match');
        $this->assertNotEmpty($cookie->getValue(), 'Cookie value should not be empty');
        $this->assertGreaterThan(time(), $cookie->getExpiresTime());

        return $cookie;
    }

    /**
     * @depends testOnKernelResponse
     */
    public function testOnKernelRequest(Cookie $cookie)
    {
        $eventMock = $this
            ->getMockBuilder(GetResponseEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request([], [], [], [$cookie->getName() => $cookie->getValue()]);

        $domainStub = $this->createMock(DomainModel::class);
        $domainStub->method('getName')->willReturn('woo');
        $domainStub->method('isEnabled')->willReturn(true);

        $settingStub = $this->createMock(SettingModel::class);
        $settingStub->method('getDomain')->willReturn($domainStub);

        $eventMock->expects($this->once())->method('isMasterRequest')->willReturn(true);
        $eventMock->expects($this->once())->method('getRequest')->willReturn($request);
        $this
            ->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with('serialized_settings', SettingModel::class . '[]', 'json')
            ->willReturn([$settingStub]);

        $this->provider->onKernelRequest($eventMock);

        $this->assertCount(1, $domains = $this->provider->getDomains());
        $this->assertEquals('woo', $domains[0]->getName());
    }

    public function testOnKernelRequestWithoutCookie()
    {
        $eventMock = $this
            ->getMockBuilder(GetResponseEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock->expects($this->once())->method('isMasterRequest')->willReturn(true);
        $eventMock->expects($this->once())->method('getRequest')->willReturn(new Request());
        $this->serializer->expects($this->never())->method('deserialize');

        $this->provider->onKernelRequest($eventMock);
    }
}
