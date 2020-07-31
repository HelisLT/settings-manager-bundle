<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Menu;

use Helis\SettingsManagerBundle\Event\ConfigureMenuEvent;
use Helis\SettingsManagerBundle\SettingsManagerEvents;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MenuBuilder
{
    private $factory;
    private $eventDispatcher;

    public function __construct(FactoryInterface $factory, EventDispatcherInterface $eventDispatcher)
    {
        $this->factory = $factory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function createTopMenu(array $options): ItemInterface
    {
        $menu = $this->factory->createItem('root');
        $menu
            ->addChild('navbar.settings_list', ['route' => 'settings_index'])
            ->setExtra('translation_domain', 'HelisSettingsManager');
        $menu
            ->addChild('navbar.domain_list', ['route' => 'settings_domain_index'])
            ->setExtra('translation_domain', 'HelisSettingsManager');

        $this->eventDispatcher->dispatch(
            new ConfigureMenuEvent($this->factory, $menu),
            SettingsManagerEvents::CONFIGURE_MENU
        );

        return $menu;
    }
}
