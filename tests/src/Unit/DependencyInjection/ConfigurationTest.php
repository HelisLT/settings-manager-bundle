<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\DependencyInjection;

use Helis\SettingsManagerBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new Processor();
    }

    public function configurationProcessDataProvider(): array
    {
        return [
            // case 1: empty
            [
                [],
                [
                    'settings_config' => ['lazy' => true, 'priority' => -10],
                    'settings_files' => [],
                    'settings' => [],
                    'profiler' => ['enabled' => false],
                    'logger' => ['enabled' => false, 'service_id' => null],
                    'listeners' => ['controller' => ['enabled' => false], 'command' => ['enabled' => false]],
                    'enqueue_extension' => ['enabled' => false, 'divider' => 1, 'priority' => 100],
                    'settings_router' => ['treat_as_default_providers' => []],
                ],
            ],
            // case 1: settings files
            [
                [
                    'helis_settings_manager' => [
                        'settings_files' => [
                            'some/file1.yml',
                            'some/file2.yml',
                            'some/file3.yml',
                        ],
                    ],
                ],
                [
                    'settings_config' => ['lazy' => true, 'priority' => -10],
                    'settings_files' => [
                        'some/file1.yml',
                        'some/file2.yml',
                        'some/file3.yml',
                    ],
                    'settings' => [],
                    'profiler' => ['enabled' => false],
                    'logger' => ['enabled' => false, 'service_id' => null],
                    'listeners' => ['controller' => ['enabled' => false], 'command' => ['enabled' => false]],
                    'enqueue_extension' => ['enabled' => false, 'divider' => 1, 'priority' => 100],
                    'settings_router' => ['treat_as_default_providers' => []],
                ],
            ],
            // case 3: settings files, settings
            [
                [
                    'helis_settings_manager' => [
                        'enqueue_extension' => true,
                        'settings_files' => [
                            'some/file1.yml',
                            'some/file2.yml',
                            'some/file3.yml',
                        ],
                        'settings' => [
                            [
                                'name' => 'settings_view',
                                'domain' => [
                                    'name' => 'banana',
                                    'enabled' => false,
                                    'read_only' => true,
                                ],
                                'type' => 'bool',
                                'data' => false,
                            ],
                            [
                                'name' => 'cammel',
                                'type' => 'bool',
                                'data' => false,
                            ],
                            [
                                'name' => 'fix',
                                'domain' => 'default',
                                'type' => 'string',
                                'data' => 'haa',
                                'tags' => ['foo', 'bar'],
                            ],
                        ],
                    ],
                ],
                [
                    'settings_config' => ['lazy' => true, 'priority' => -10],
                    'settings_files' => [
                        'some/file1.yml',
                        'some/file2.yml',
                        'some/file3.yml',
                    ],
                    'settings' => [
                        [
                            'name' => 'settings_view',
                            'domain' => [
                                'name' => 'banana',
                                'enabled' => false,
                                'read_only' => true,
                            ],
                            'type' => 'bool',
                            'data' => [
                                'value' => false,
                            ],
                            'tags' => [],
                            'choices' => []
                        ],
                        [
                            'name' => 'cammel',
                            'domain' => [
                                'name' => 'default',
                                'enabled' => true,
                                'read_only' => true,
                            ],
                            'type' => 'bool',
                            'data' => [
                                'value' => false,
                            ],
                            'tags' => [],
                            'choices' => []
                        ],
                        [
                            'name' => 'fix',
                            'domain' => [
                                'name' => 'default',
                                'enabled' => true,
                                'read_only' => true,
                            ],
                            'type' => 'string',
                            'data' => [
                                'value' => 'haa',
                            ],
                            'tags' => [
                                [
                                    'name' => 'foo',
                                ],
                                [
                                    'name' => 'bar',
                                ],
                            ],
                            'choices' => []
                        ],
                    ],
                    'profiler' => ['enabled' => false],
                    'logger' => ['enabled' => false, 'service_id' => null],
                    'listeners' => ['controller' => ['enabled' => false], 'command' => ['enabled' => false]],
                    'enqueue_extension' => ['enabled' => true, 'divider' => 1, 'priority' => 100],
                    'settings_router' => ['treat_as_default_providers' => []],
                ],
            ],
        ];
    }

    /**
     * @dataProvider configurationProcessDataProvider
     */
    public function testConfigurationProcess(array $configToProcess, array $expected): void
    {
        $this->assertEquals(
            $expected,
            $this->processor->processConfiguration(new Configuration(), $configToProcess)
        );
    }
}
