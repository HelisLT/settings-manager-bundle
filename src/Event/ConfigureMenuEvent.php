<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Event;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\EventDispatcher\Event as ComponentEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event as ContractEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;

if (is_a(EventDispatcherInterface::class, ContractsEventDispatcherInterface::class, true)) {
    class ConfigureMenuEvent extends ContractEvent
    {
        private $factory;
        private $menu;

        public function __construct(FactoryInterface $factory, ItemInterface $menu)
        {
            $this->factory = $factory;
            $this->menu = $menu;
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
} else {
    class ConfigureMenuEvent extends ComponentEvent
    {
        private $factory;
        private $menu;

        public function __construct(FactoryInterface $factory, ItemInterface $menu)
        {
            $this->factory = $factory;
            $this->menu = $menu;
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
}
