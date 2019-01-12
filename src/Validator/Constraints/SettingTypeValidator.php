<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Validator\Constraints;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidator;

class SettingTypeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$value instanceof SettingModel || !$constraint instanceof SettingType) {
            return;
        }

        $type = $value->getType();
        if ($type === null || $value->getData() === null) {
            return;
        }

        $type = strtolower($type->getKey());
        $type = $type === 'yaml' ? 'array' : $type;
        $type = $type === 'choice' ? 'string' : $type;

        $this
            ->context
            ->getValidator()
            ->inContext($this->context)
            ->validate($value->getData(), new Type(['type' => $type, 'message' => $constraint->message]));
    }
}
