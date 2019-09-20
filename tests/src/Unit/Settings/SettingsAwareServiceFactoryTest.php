<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Settings;

use Helis\SettingsManagerBundle\Settings\SettingsAwareServiceFactory;
use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

class SettingsAwareServiceFactoryTest extends TestCase
{
    /**
     * @var SettingsRouter|MockObject
     */
    private $settingsRouter;

    public function testGet()
    {
        $factory = new SettingsAwareServiceFactory($this->settingsRouter);
        $object = $this
            ->getMockBuilder(stdClass::class)
            ->setMethods(['setEnabled', 'setGGCount'])
            ->getMock();

        $object->expects($this->once())->method('setEnabled')->with(false);
        $object->expects($this->once())->method('setGGCount')->with(69);

        $this
            ->settingsRouter
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(['zomg_active'], ['gg_count'])
            ->willReturnOnConsecutiveCalls(false, 69);

        $factory->get(['zomg_active' => 'setEnabled', 'gg_count' => 'setGGCount'], $object);
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->settingsRouter = $this
            ->getMockBuilder(SettingsRouter::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
