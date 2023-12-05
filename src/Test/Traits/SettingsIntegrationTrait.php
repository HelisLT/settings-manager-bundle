<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Test\Traits;

use Helis\SettingsManagerBundle\Test\Provider\SettingsProviderMock;

trait SettingsIntegrationTrait
{
    /**
     * @after
     */
    public function tearDownSettings(): void
    {
        SettingsProviderMock::clear();
    }
}
