<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\DataCollector;

use Helis\SettingsManagerBundle\Settings\SettingsStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

class SettingsCollector implements DataCollectorInterface
{
    private $settingsStore;
    private $data;

    public function __construct(SettingsStore $settingsStore)
    {
        $this->settingsStore = $settingsStore;
        $this->data = [];
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = $this->settingsStore->getValues();
    }

    public function getName()
    {
        return 'settings_manager.settings_collector';
    }

    public function reset()
    {
        $this->data = [];
    }

    public function count(): int
    {
        return count($this->data);
    }
}
