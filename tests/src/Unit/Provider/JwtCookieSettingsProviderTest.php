<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\AbstractBaseCookieSettingsProvider;
use Helis\SettingsManagerBundle\Provider\JwtCookieSettingsProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class JwtCookieSettingsProviderTest extends AbstractCookieSettingsProviderTest
{
    protected function createProvider(): AbstractBaseCookieSettingsProvider
    {
        return $this->createProviderWithKeys(
            'file://'.__DIR__.'/Fixtures/public.key',
            'file://'.__DIR__.'/Fixtures/private.key'
        );
    }

    public function testNoPrivateKey(): void
    {
        // No private key provided => no cookie will be set
        $provider = $this->createProviderWithKeys('file://'.__DIR__.'/Fixtures/public.key');
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), new Request(), 1, $response = new Response());

        $settingStub = $this->createMock(SettingModel::class);

        $provider->save($settingStub);
        $provider->onKernelResponse($event);

        $this->assertCount(0, $response->headers->getCookies());
    }

    public function testInvalidPublicKey(): void
    {
        // Different public key => cookie invalid => no settings parsed
        $provider = $this->createProviderWithKeys('invalid_key_content');
        $eventMock = $this
            ->getMockBuilder(RequestEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request([], [], [], [
            $this->cookieName => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE2MjkxNzg5NDcsIm5iZiI6MTYyOTE3ODk0N'.
                'ywiaXNzIjoic2V0dGluZ3NfbWFuYWdlciIsInN1YiI6InNldHRpbmdzIiwiZXhwIjoxNjI5MjY1MzQ3LCJkdCI6InNlcmlhbGl6'.
                'ZWRfc2V0dGluZ3MifQ.U_bpV8Jwj-ja1fNnHuCOEK0CuCebR8cc3ytDpdIqJL5Iy_Sqb2Kpp7q0rsIcRaiXIIoDJu7vFVUpH9c6'.
                '4xCPxLahIvQLEICJmBp0B7AZ9Ddtw1mL7uf83VJqv_hg97DOTs1sB3jEwkoSTwr_jhVsyth1CkOzzy3IyoDKTwLWEv-m_WqV-nZ'.
                'sdT856ujCvCyzeCX43Z4NWjW4o_s1mjTbtuyn0vXJD4cQlNqPoqYcXJsTCRCERj6tZ_BjZ3IFbNBCq20OjIh24Vs1U8wdgXbIz8'.
                'zfc3rTq4YzKDUksF17Sy1f8_kfAKuHbCHB-kr4fIcJ2VIOvg7R5iWbAsvzO8XGgw',
        ]);

        $domainStub = $this->createMock(DomainModel::class);
        $domainStub->method('getName')->willReturn('woo');
        $domainStub->method('isEnabled')->willReturn(true);

        $settingStub = $this->createMock(SettingModel::class);
        $settingStub->method('getDomain')->willReturn($domainStub);

        $eventMock->expects($this->once())->method('isMainRequest')->willReturn(true);
        $eventMock->expects($this->once())->method('getRequest')->willReturn($request);

        $provider->onKernelRequest($eventMock);

        $this->assertCount(0, $provider->getDomains());
    }

    public function testPrivateKeyContent()
    {
        $previousProvider = $this->provider;
        $this->provider = $this->createProviderWithKeys(
            'file://'.__DIR__.'/Fixtures/public.key',
            file_get_contents(__DIR__.'/Fixtures/private.key')
        );

        $this->testOnKernelResponse();

        $this->provider = $previousProvider;
    }

    private function createProviderWithKeys(string $publicKey, ?string $privateKey = null): JwtCookieSettingsProvider
    {
        return new JwtCookieSettingsProvider(
            $this->serializer,
            $publicKey,
            $privateKey ?? null,
            $this->cookieName
        );
    }
}
