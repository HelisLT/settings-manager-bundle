<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Enqueue\Consumption;

use Enqueue\Consumption\Context\MessageReceived;
use Enqueue\Consumption\MessageReceivedExtensionInterface;
use Helis\SettingsManagerBundle\Settings\Traits\SettingsRouterAwareTrait;

class WarmupSettingsManagerExtension implements MessageReceivedExtensionInterface
{
    use SettingsRouterAwareTrait;

    private $currentIteration;
    private $divider;

    public function __construct()
    {
        $this->currentIteration = 0;
        $this->divider = 1;
    }

    public function setDivider(int $divider): void
    {
        $this->divider = $divider;
    }

    public function onMessageReceived(MessageReceived $context): void
    {
        if (!$this->settingsRouter) {
            return;
        }

        if (++$this->currentIteration % $this->divider === 0
            && $this->settingsRouter->isWarm()
        ) {
            $this->settingsRouter->warmup();
        }
    }
}
