<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\DataCollector;

use Helis\SettingsManagerBundle\Settings\SettingsStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Throwable;

class SettingsCollector implements DataCollectorInterface
{
    private array $data = [];

    public function __construct(private readonly SettingsStore $settingsStore)
    {
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $this->data = $this->settingsStore->getValues();
    }

    public function getName(): string
    {
        return 'settings_manager.settings_collector';
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function count(): int
    {
        return count($this->data);
    }
}
