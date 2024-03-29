<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Settings;

use App\Controller\MockController;
use Helis\SettingsManagerBundle\Exception\SettingNotFoundException;
use Helis\SettingsManagerBundle\Settings\SettingsAwareServiceFactory;
use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsAwareServiceFactoryTest extends TestCase
{
    /**
     * @var SettingsRouter|MockObject
     */
    private $settingsRouter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRouter = $this
            ->getMockBuilder(SettingsRouter::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testGet()
    {
        $factory = new SettingsAwareServiceFactory($this->settingsRouter);
        $object = $this
            ->getMockBuilder(MockController::class)
            ->onlyMethods(['setEnabled', 'setGGCount'])
            ->getMock();

        $object->expects($this->once())->method('setEnabled')->with(false);
        $object->expects($this->once())->method('setGGCount')->with(69);

        $this
            ->settingsRouter
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['zomg_active', null, false],
                ['gg_count', null, 69],
            ])
            /*->withConsecutive(['zomg_active'], ['gg_count'])
            ->willReturnOnConsecutiveCalls(false, 69)*/;

        $factory->get(['zomg_active' => 'setEnabled', 'gg_count' => 'setGGCount'], $object);
    }

    public function testGetWithMust()
    {
        $factory = new SettingsAwareServiceFactory($this->settingsRouter);
        $object = $this
            ->getMockBuilder(MockController::class)
            ->onlyMethods(['setEnabled', 'setGGCount'])
            ->getMock();

        $object->expects($this->once())->method('setEnabled')->with(false);
        $object->expects($this->once())->method('setGGCount')->with(69);

        $this
            ->settingsRouter
            ->expects($this->exactly(2))
            ->method('mustGet')
            ->willReturnMap([
                ['zomg_active', false],
                ['gg_count', 69],
            ])
            /*->withConsecutive(['zomg_active'], ['gg_count'])
            ->willReturnOnConsecutiveCalls(false, 69)*/;

        $factory->get([
            'zomg_active' => ['setter' => 'setEnabled', 'must' => true],
            'gg_count' => ['setter' => 'setGGCount', 'must' => true],
        ], $object);
    }

    public function testGetWithMustException()
    {
        $this->expectException(SettingNotFoundException::class);

        $factory = new SettingsAwareServiceFactory($this->settingsRouter);
        $object = $this
            ->getMockBuilder(MockController::class)
            ->onlyMethods(['setEnabled'])
            ->getMock();

        $this
            ->settingsRouter
            ->expects($this->once())
            ->method('mustGet')
            ->with('zomg_active')
            ->will($this->throwException(new SettingNotFoundException('zomg_active')));

        $factory->get([
            'zomg_active' => ['setter' => 'setEnabled', 'must' => true],
        ], $object);
    }
}
