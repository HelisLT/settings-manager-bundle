<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

interface ModificationAwareSettingsProviderInterface extends SettingsProviderInterface
{
    public function setModificationTimeKey(string $modificationTimeKey): void;

    public function getModificationTime(): int;
}
