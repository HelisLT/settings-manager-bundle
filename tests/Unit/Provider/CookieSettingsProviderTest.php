<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Provider\CookieSettingsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Serializer\SerializerInterface;

class CookieSettingsProviderTest extends TestCase
{
    private $provider;
    private $serializer;
    private $symmetricKeyMaterial;
    private $cookieName;

    protected function setUp()
    {
        $this->serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $this->symmetricKeyMaterial = 'YELLOW SUBMARINE, BLACK WIZARDRY';
        $this->cookieName = 'Orange';

        $this->provider = new CookieSettingsProvider($this->serializer, $this->symmetricKeyMaterial, $this->cookieName);
    }

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
        $eventMock = $this
            ->getMockBuilder(FilterResponseEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock->expects($this->once())->method('isMasterRequest')->willReturn(true);
        $eventMock->expects($this->exactly(2))->method('getResponse')->willReturn($response = new Response());
        $eventMock->expects($this->never())->method('getRequest');
        
        $domain = new DomainModel();
        $domain->setName('woo');
        $domain->setEnabled(true);

        $this
            ->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with([$domain->getName() => $domain], 'json')
            ->willReturn('serialized_domains');

        $this->provider->updateDomain($domain);
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
        $domain = new DomainModel();
        $domain->setName('woo');
        $domain->setEnabled(true);

        $eventMock->expects($this->once())->method('isMasterRequest')->willReturn(true);
        $eventMock->expects($this->once())->method('getRequest')->willReturn($request);
        $this
            ->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with('serialized_domains', DomainModel::class . '[]', 'json')
            ->willReturn([$domain->getName() => $domain]);

        $this->provider->onKernelRequest($eventMock);

        $this->assertCount(1, $domains = $this->provider->getDomains());
        $this->assertArrayHasKey('woo', $domains);
        $this->assertEquals('woo', $domains['woo']->getName());
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
