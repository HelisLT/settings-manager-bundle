<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\DependencyInjection\Compiler;

use App\AbstractWebTestCase;
use App\FinalImportantService;
use App\ImportantService;

class SettingsAwarePassTest extends AbstractWebTestCase
{
    public function testIsEnabled(): void
    {
        $this->loadFixtures([]);

        $service = static::getContainer()->get(ImportantService::class);

        $this->assertTrue($service->isEnabled());
    }

    public function testIsEnabledWithFinalClass()
    {
        $this->loadFixtures([]);

        $service = static::getContainer()->get(FinalImportantService::class);

        $this->assertTrue($service->isEnabled());
    }
}
