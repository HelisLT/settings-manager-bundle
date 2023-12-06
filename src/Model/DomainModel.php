<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Model;

class DomainModel
{
    final public const DEFAULT_NAME = 'default';

    protected ?string $name = null;
    protected int $priority = 0;
    protected bool $enabled = false;
    protected bool $readOnly = false;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): DomainModel
    {
        $this->name = $name;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): DomainModel
    {
        $this->priority = $priority;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->name === self::DEFAULT_NAME ? true : $this->enabled;
    }

    public function setEnabled(bool $enabled): DomainModel
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isReadOnly(): bool
    {
        return $this->name === self::DEFAULT_NAME ? true : $this->readOnly;
    }

    public function setReadOnly(bool $readOnly): DomainModel
    {
        $this->readOnly = $readOnly;

        return $this;
    }
}
