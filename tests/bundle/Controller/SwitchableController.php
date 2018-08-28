<?php

declare(strict_types=1);

namespace App\Controller;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Helis\SettingsManagerBundle\Settings\Switchable\SwitchableControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class SwitchableController extends AbstractController implements SwitchableControllerInterface
{
    public static function isControllerEnabled(SettingsRouter $router): bool
    {
        return $router->getBool('switchable_controller_enabled');
    }

    public function printAction(string $value): Response
    {
        return new Response($value);
    }
}
