<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Twig;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingsExtension extends AbstractExtension
{
    public function __construct(private readonly SettingsRouter $settingsRouter)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting_get', [$this, 'getSetting']),
        ];
    }

    public function getSetting(string $settingName, $defaultValue = null): mixed
    {
        return $this->settingsRouter->get($settingName, $defaultValue);
    }
}
