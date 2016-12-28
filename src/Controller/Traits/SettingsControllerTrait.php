<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Controller\Traits;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

trait SettingsControllerTrait
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

    public function denyUnlessEnabled(string $settingName): void
    {
        if (!$this->settingsRouter->getBool($settingName)) {
            throw new AccessDeniedException();
        }
    }
}
