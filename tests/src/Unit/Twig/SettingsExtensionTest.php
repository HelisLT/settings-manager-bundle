<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Unit\Twig;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Helis\SettingsManagerBundle\Twig\SettingsExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig_SimpleFunction;

class SettingsExtensionTest extends TestCase
{
    /**
     * @var SettingsRouter|MockObject
     */
    private $settingsRouter;

    public function testGetFunctions()
    {
        $extension = new SettingsExtension($this->settingsRouter);
        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        /** @var Twig_SimpleFunction $function */
        $function = array_shift($functions);
        $this->assertInstanceOf(Twig_SimpleFunction::class, $function);
        $this->assertEquals('setting_get', $function->getName());
        $this->assertEquals('getSetting', $function->getCallable()[1]);
    }

    public function testGetSetting()
    {
        $this
            ->settingsRouter
            ->expects($this->once())
            ->method('get')
            ->with('foo_setting', 'hohoho')
            ->willReturn('cool');

        $extension = new SettingsExtension($this->settingsRouter);
        $value = $extension->getSetting('foo_setting', 'hohoho');

        $this->assertEquals('cool', $value);
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
