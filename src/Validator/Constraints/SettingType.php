<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class SettingType extends Constraint
{
    public $message = 'Setting data value should be of type {{ type }}.';

    public function getTargets()
    {
        return Constraint::CLASS_CONSTRAINT;
    }
}
