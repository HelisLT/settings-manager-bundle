<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Enqueue\Consumption;

use Enqueue\Consumption\Context\MessageReceived;
use Enqueue\Consumption\MessageReceivedExtensionInterface;
use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Helis\SettingsManagerBundle\Settings\Traits\SettingsRouterAwareTrait;

class WarmupSettingsManagerExtension implements MessageReceivedExtensionInterface
{
    use SettingsRouterAwareTrait;

    private int $currentIteration = 0;
    private int $divider = 1;

    public function setDivider(int $divider): void
    {
        $this->divider = $divider;
    }

    public function onMessageReceived(MessageReceived $context): void
    {
        if (!$this->settingsRouter instanceof SettingsRouter) {
            return;
        }

        if (++$this->currentIteration % $this->divider === 0
            && $this->settingsRouter->isWarm()
        ) {
            $this->settingsRouter->warmup();
        }
    }
}
