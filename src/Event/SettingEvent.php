<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Event;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Symfony\Component\EventDispatcher\Event as ComponentEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event as ContractEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;

if (is_a(EventDispatcherInterface::class, ContractsEventDispatcherInterface::class, true)) {
    class SettingEvent extends ContractEvent
    {
        protected $setting;

        public function __construct(SettingModel $setting)
        {
            $this->setting = $setting;
        }

        public function getSetting(): SettingModel
        {
            return $this->setting;
        }
    }
} else {
    class SettingEvent extends ComponentEvent
    {
        protected $setting;

        public function __construct(SettingModel $setting)
        {
            $this->setting = $setting;
        }

        public function getSetting(): SettingModel
        {
            return $this->setting;
        }
    }
}
