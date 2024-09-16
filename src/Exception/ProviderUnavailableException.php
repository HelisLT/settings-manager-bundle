<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when a provider cannot get the settings. Example a HTTP based provider
 * and the server is temporary down.
 */
class ProviderUnavailableException extends RuntimeException implements SettingsException
{
    public function __construct(string $message = 'Settings provider is unavailable', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
