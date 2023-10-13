<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings\Traits;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;

trait SettingsRouterAwareTrait
{
    protected ?SettingsRouter $settingsRouter = null;

    /**
     * @required
     */
    public function setSettingsRouter(SettingsRouter $settingsRouter): void
    {
        $this->settingsRouter = $settingsRouter;
    }
}
