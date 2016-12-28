<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings;

use Helis\SettingsManagerBundle\Settings\Switchable\SwitchableInterface;
use Helis\SettingsManagerBundle\Settings\Switchable\SwitchableTrait;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class SettingsAccessControl implements SwitchableInterface
{
    use SwitchableTrait;

    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function isGranted(string $action, $subject): bool
    {
        return $this->isEnabled() ? $this->authorizationChecker->isGranted($action, $subject) : true;
    }
}
