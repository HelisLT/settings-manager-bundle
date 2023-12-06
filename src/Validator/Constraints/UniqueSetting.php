<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class UniqueSetting extends Constraint
{
    public string $message = '{{ domainName }} domain already has setting named {{ settingName }}';

    public function getTargets(): string
    {
        return Constraint::CLASS_CONSTRAINT;
    }
}
