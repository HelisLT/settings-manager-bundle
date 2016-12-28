<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class UniqueSettingDomain extends Constraint
{
    public $message = '{{ domainName }} domain already has setting named {{ settingName }}';

    public function getTargets()
    {
        return Constraint::CLASS_CONSTRAINT;
    }
}
