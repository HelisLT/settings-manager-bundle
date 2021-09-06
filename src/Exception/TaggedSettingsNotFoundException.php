<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Exception;

class TaggedSettingsNotFoundException extends \Exception implements SettingsException
{
    public function __construct(string $tagName)
    {
        parent::__construct(sprintf('No settings found with tag "%s"', $tagName));
    }
}
