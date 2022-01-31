<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Twig;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingsExtension extends AbstractExtension
{
    private $settingsRouter;

    public function __construct(SettingsRouter $settingsRouter)
    {
        $this->settingsRouter = $settingsRouter;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting_get', [$this, 'getSetting']),
        ];
    }

    /**
     * @return mixed
     */
    public function getSetting(string $settingName, $defaultValue = null)
    {
        return $this->settingsRouter->get($settingName, $defaultValue);
    }
}
