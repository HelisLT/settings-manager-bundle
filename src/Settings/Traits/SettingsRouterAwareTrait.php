<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings\Traits;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;

trait SettingsRouterAwareTrait
{
    /**
     * @var SettingsRouter
     */
    protected $settingsRouter;

    /**
     * @required
     */
    public function setSettingsRouter(SettingsRouter $settingsRouter): void
    {
        $this->settingsRouter = $settingsRouter;
    }
}
