<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle;

final class SettingsManagerEvents
{
    /**
     * Provides an ability to modify or extend menu.
     *
     * Event class \Helis\SettingsManagerBundle\Event\ConfigureMenuEvent
     */
    public const CONFIGURE_MENU = 'settings_manager.configure_menu';

    /**
     * Provides an ability to modify setting just before fetch.
     *
     * Event class \Helis\SettingsManagerBundle\Event\GetSettingEvent
     */
    public const GET_SETTING = 'settings_manager.get_setting';

    /**
     * Provides an ability to inform about setting change right after flush to storage.
     *
     * Event class \Helis\SettingsManagerBundle\Event\SettingChangeEvent
     */
    public const UPDATE_SETTING = 'settings_manager.update_setting';

    /**
     * Provides an ability to inform about setting duplication right after flush to storage.
     *
     * Event class \Helis\SettingsManagerBundle\Event\SettingChangeEvent
     */
    public const DUPLICATE_SETTING = 'settings_manager.duplicate_setting';

    /**
     * Provides an ability to inform about setting deletion right after flush to storage.
     *
     * Event class \Helis\SettingsManagerBundle\Event\SettingChangeEvent
     */
    public const DELETE_SETTING = 'settings_manager.delete_setting';

    private function __construct()
    {
    }
}
