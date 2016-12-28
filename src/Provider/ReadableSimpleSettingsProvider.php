<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Provider\Traits\ReadOnlyProviderTrait;

class ReadableSimpleSettingsProvider extends SimpleSettingsProvider
{
    use ReadOnlyProviderTrait;
}
