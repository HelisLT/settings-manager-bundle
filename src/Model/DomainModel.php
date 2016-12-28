<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Model;

class DomainModel
{
    public const DEFAULT_NAME = 'default';

    protected $name;
    protected $priority = 0;
    protected $enabled = false;
    protected $readOnly = false;

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
