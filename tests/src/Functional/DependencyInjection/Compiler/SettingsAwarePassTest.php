<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\DependencyInjection\Compiler;

use App\ImportantService;
use Liip\FunctionalTestBundle\Test\WebTestCase;

class SettingsAwarePassTest extends WebTestCase
{
    public function testIsEnabled(): void
    {
        $service = $this->getContainer()->get(ImportantService::class);

        $this->assertTrue($service->isEnabled());
    }
}
