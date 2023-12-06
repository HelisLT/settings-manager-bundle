<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Event;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Symfony\Contracts\EventDispatcher\Event;

class SettingEvent extends Event
{
    public function __construct(protected SettingModel $setting)
    {
    }

    public function getSetting(): SettingModel
    {
        return $this->setting;
    }
}
