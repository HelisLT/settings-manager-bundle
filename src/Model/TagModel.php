<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Model;

class TagModel
{
    private ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): TagModel
    {
        $this->name = $name;

        return $this;
    }
}
