<?php

namespace Helis\SettingsManagerBundle\Exception;

/**
 * Thrown when a provider cannot get the settings. Example a HTTP based provider
 * and the server is temporary down.
 */
class ProviderUnavailableException extends \RuntimeException implements SettingsException
{

}
