<?php

namespace Helis\SettingsManagerBundle\Provider;

interface ModificationAwareSettingsProviderInterface extends SettingsProviderInterface
{
    public function getModificationTime(): int;
}
