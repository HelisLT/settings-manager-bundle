<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Validator\Constraints;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UniqueSettingValidator extends ConstraintValidator
{
    public function __construct(private readonly SettingsManager $settingManager)
    {
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$value instanceof SettingModel || !$constraint instanceof UniqueSetting) {
            return;
        }

        $settings = $this->settingManager->getSettingsByName([$value->getDomain()->getName()], [$value->getName()]);

        if ($settings !== []) {
            $this
                ->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ domainName }}', $value->getDomain()->getName())
                ->setParameter('{{ settingName }}', $value->getName())
                ->addViolation();
        }
    }
}
