<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Provider;

use Aws\Result;
use Aws\Ssm\SsmClient;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\AwsSsmSettingsProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

class AwsSsmSettingsProviderTest extends TestCase
{
    /**
     * @var SsmClient|MockObject
     */
    private $awsSsmClientMock;

    /**
     * @var SerializerInterface|MockObject
     */
    private $serializerMock;

    protected function setUp()
    {
        $this->awsSsmClientMock = $this->createPartialMock(SsmClient::class, ['getParameters', 'putParameter']);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
    }

    public function testGetSettingsWithNoParameters(): void
    {
        $settingsProvider = $this->createSettingsProvider([]);

        $awsResult = $this->createConfiguredMock(
            Result::class,
            [
                'get' => [],
            ]
        );

        $this->awsSsmClientMock
            ->method('getParameters')
            ->willReturnMap([
                [['Names' => []], $awsResult],
            ]);

        /** @var SettingModel[] $settings */
        $settings = $settingsProvider->getSettings([DomainModel::DEFAULT_NAME]);

        $this->assertCount(0, $settings);
    }

    private function createSettingsProvider(array $parameterNames): AwsSsmSettingsProvider
    {
        return new AwsSsmSettingsProvider(
            $this->awsSsmClientMock,
            $this->serializerMock,
            $parameterNames
        );
    }
}
