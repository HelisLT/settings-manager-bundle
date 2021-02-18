<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Menu;

use Helis\SettingsManagerBundle\Event\ConfigureMenuEvent;
use Helis\SettingsManagerBundle\Settings\EventManager;
use Helis\SettingsManagerBundle\SettingsManagerEvents;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;

class MenuBuilder
{
    private $factory;
    private $eventManager;

    public function __construct(FactoryInterface $factory, EventManager $eventManager)
    {
        $this->factory = $factory;
        $this->eventManager = $eventManager;
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

        $this->eventManager->dispatchConfigureMenu(
            SettingsManagerEvents::CONFIGURE_MENU,
            new ConfigureMenuEvent($this->factory, $menu)
        );

        return $menu;
    }
}
