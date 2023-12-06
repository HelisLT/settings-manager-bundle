<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Validator\Constraints;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type as SettingTypeEnum;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidator;

class SettingTypeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof SettingModel || !$constraint instanceof SettingType) {
            return;
        }

        $type = $value->getType();
        if (!$type instanceof SettingTypeEnum || $value->getData() === null) {
            return;
        }

        $type = strtolower($type->name);
        $type = $type === 'yaml' ? 'array' : $type;
        $type = $type === 'choice' ? 'string' : $type;

        $this
            ->context
            ->getValidator()
            ->inContext($this->context)
            ->validate($value->getData(), new Type(['type' => $type, 'message' => $constraint->message]));
    }
}
