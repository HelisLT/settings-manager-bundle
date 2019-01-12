<?php

namespace Helis\SettingsManagerBundle\Model;

class ChoiceModel
{
    private $value;

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): ChoiceModel
    {
        $this->value = $value;

        return $this;
    }
}