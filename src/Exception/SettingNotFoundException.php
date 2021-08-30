<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Exception;


class SettingNotFoundException extends \Exception implements SettingsException
{
    public function __construct(string $settingName)
    {
        parent::__construct(sprintf('Setting "%s" not found', $settingName));
    }
}
