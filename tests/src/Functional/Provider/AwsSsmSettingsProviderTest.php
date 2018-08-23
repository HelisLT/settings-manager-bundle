<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use Aws\Result;
use Aws\Ssm\SsmClient;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type;
use Helis\SettingsManagerBundle\Provider\AwsSsmSettingsProvider;
use Helis\SettingsManagerBundle\Serializer\Normalizer\DomainModelNormalizer;
use Helis\SettingsManagerBundle\Serializer\Normalizer\SettingModelNormalizer;
use Helis\SettingsManagerBundle\Serializer\Normalizer\TagModelNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

class AwsSsmSettingsProviderTest extends TestCase
{
    /**
     * @var SsmClient|MockObject
     */
    private $awsSsmClientMock;

    /**
     * @var Serializer
     */
    private $serializer;

    protected function setUp()
    {
        $this->awsSsmClientMock = $this->createPartialMock(SsmClient::class, ['getParameters', 'putParameter']);
        $this->serializer = new Serializer(
            [
                new ArrayDenormalizer(),
                new SettingModelNormalizer(),
                new DomainModelNormalizer(),
                new TagModelNormalizer(),
            ],
            [
                new JsonEncoder(),
            ]
        );
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

    public function testGetSettingsWithSingleParameter(): void
    {
        $settingsProvider = $this->createSettingsProvider(['parameter_a']);

        $awsResult = $this->createConfiguredMock(
            Result::class,
            [
                'get' => [
                    [
                        'Name'  => 'Parameter A Name',
                        'Value' => 'a_value',
                    ],
                ],
            ]
        );

        $this->awsSsmClientMock
            ->method('getParameters')
            ->willReturnMap([
                [['Names' => ['parameter_a']], $awsResult],
            ]);

        /** @var SettingModel[] $settings */
        $settings = $settingsProvider->getSettings([DomainModel::DEFAULT_NAME]);

        $this->assertCount(1, $settings);

        $this->assertSame('Parameter A Name', $settings[0]->getName());
        $this->assertSame(DomainModel::DEFAULT_NAME, $settings[0]->getDomain()->getName());
        $this->assertSame(Type::STRING, $settings[0]->getType()->getValue());
        $this->assertSame('a_value', $settings[0]->getData());
    }

    public function testGetSettingsWithMultipleParameters(): void
    {
        $settingsProvider = $this->createSettingsProvider(['parameter_a', 'parameter_b']);

        $awsResult = $this->createConfiguredMock(
            Result::class,
            [
                'get' => [
                    [
                        'Name'  => 'Parameter A Name',
                        'Value' => 'a_value',
                    ],
                    [
                        'Name'  => 'Parameter B Name',
                        'Value' => 'b_value',
                    ],
                ],
            ]
        );

        $this->awsSsmClientMock
            ->method('getParameters')
            ->willReturnMap([
                [['Names' => ['parameter_a', 'parameter_b']], $awsResult],
            ]);

        /** @var SettingModel[] $settings */
        $settings = $settingsProvider->getSettings([DomainModel::DEFAULT_NAME]);

        $this->assertCount(2, $settings);

        $this->assertSame('Parameter A Name', $settings[0]->getName());
        $this->assertSame(DomainModel::DEFAULT_NAME, $settings[0]->getDomain()->getName());
        $this->assertSame(Type::STRING, $settings[0]->getType()->getValue());
        $this->assertSame('a_value', $settings[0]->getData());

        $this->assertSame('Parameter B Name', $settings[1]->getName());
        $this->assertSame(DomainModel::DEFAULT_NAME, $settings[1]->getDomain()->getName());
        $this->assertSame(Type::STRING, $settings[1]->getType()->getValue());
        $this->assertSame('b_value', $settings[1]->getData());
    }

    public function testGetSettingsMultipleTimesFetchesOnlyOnce(): void
    {
        $settingsProvider = $this->createSettingsProvider([]);

        $awsResult = $this->createConfiguredMock(
            Result::class,
            [
                'get' => [],
            ]
        );

        $this->awsSsmClientMock
            ->expects($this->once())
            ->method('getParameters')
            ->willReturnMap([
                [['Names' => []], $awsResult],
            ]);

        $settingsProvider->getSettings([DomainModel::DEFAULT_NAME]);
        $settingsProvider->getSettings([DomainModel::DEFAULT_NAME]);
    }

    private function createSettingsProvider(array $parameterNames): AwsSsmSettingsProvider
    {
        return new AwsSsmSettingsProvider(
            $this->awsSsmClientMock,
            $this->serializer,
            $parameterNames
        );
    }
}
