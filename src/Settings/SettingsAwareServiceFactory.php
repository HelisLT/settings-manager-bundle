<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Exception\SettingNotFoundException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Throwable;

class SettingsAwareServiceFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private readonly SettingsRouter $settingsRouter)
    {
    }

    /**
     * @throws SettingNotFoundException
     */
    public function get(array $callMap, mixed $object): mixed
    {
        foreach ($callMap as $settingName => $callDetails) {
            $must = false;
            $setter = $callDetails;

            if (is_array($callDetails)) {
                $setter = $callDetails['setter'];
                $must = $callDetails['must'];
            }

            if ($must) {
                $setting = $this->settingsRouter->mustGet($settingName);
            } else {
                try {
                    $setting = $this->settingsRouter->get($settingName, null);
                } catch (Throwable $e) {
                    $this->logger && $this->logger->error('Failed to get setting', [
                        'exception' => $e,
                        'sSettingName' => $settingName,
                        'sObjectMethodName' => $setter,
                        'sObjectClassName' => $object::class,
                    ]);
                    continue;
                }

                if ($setting === null) {
                    $this->logger && $this->logger->notice('Setting was not found.', [
                        'sSettingName' => $settingName,
                        'sObjectMethodName' => $setter,
                        'sObjectClassName' => $object::class,
                    ]);
                    continue;
                }
            }

            if (method_exists($object, $setter)) {
                $object->{$setter}($setting);
            } else {
                throw new LogicException('Undefined method '.$object::class."::{$setter}().");
            }
        }

        return $object;
    }
}
