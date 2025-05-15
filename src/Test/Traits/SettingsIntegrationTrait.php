<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Test\Traits;

use Helis\SettingsManagerBundle\Test\Provider\SettingsProviderMock;
use PHPUnit\Framework\Attributes\After;

trait SettingsIntegrationTrait
{
    #[After]
    /**
     * @after
     */
    public function tearDownSettings(): void
    {
        SettingsProviderMock::clear();
    }
}
