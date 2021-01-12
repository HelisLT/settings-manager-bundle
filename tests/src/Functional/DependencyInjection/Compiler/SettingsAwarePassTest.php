<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\DependencyInjection\Compiler;

use App\ImportantService;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Test\FixturesTrait;

class SettingsAwarePassTest extends WebTestCase
{
    use FixturesTrait;

    public function testIsEnabled(): void
    {
        $this->loadFixtures([]);

        $service = $this->getContainer()->get(ImportantService::class);

        $this->assertTrue($service->isEnabled());
    }
}
