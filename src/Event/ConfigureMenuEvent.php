<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Event;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Contracts\EventDispatcher\Event;

class ConfigureMenuEvent extends Event
{
    public function __construct(private readonly FactoryInterface $factory, private readonly ItemInterface $menu)
    {
    }

    public function getFactory(): FactoryInterface
    {
        return $this->factory;
    }

    public function getMenu(): ItemInterface
    {
        return $this->menu;
    }
}
