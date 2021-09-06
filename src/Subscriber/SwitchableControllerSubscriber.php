<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Subscriber;

use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Helis\SettingsManagerBundle\Settings\Switchable\SwitchableControllerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class SwitchableControllerSubscriber implements EventSubscriberInterface
{
    private $settingsRouter;

    public function __construct(SettingsRouter $settingsRouter)
    {
        $this->settingsRouter = $settingsRouter;
    }

    public static function getSubscribedEvents()
    {
        return [KernelEvents::CONTROLLER => ['onKernelController']];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!is_array($controller = $event->getController())) {
            return;
        }

        $controller = $controller[0];

        if ($controller instanceof SwitchableControllerInterface
            && !$controller::isControllerEnabled($this->settingsRouter)
        ) {
            throw new NotFoundHttpException();
        }
    }
}
