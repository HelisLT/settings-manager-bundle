<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle;

final class SettingsManagerActions
{
    public const SETTING_QUICK_EDIT = 'settings_manager.setting_quick_edit';
    public const SETTING_EDIT = 'settings_manager.setting_edit';
    public const SETTING_DELETE = 'settings_manager.setting_delete';
    public const SETTING_DUPLICATE = 'settings_manager.setting_duplicate';

    public const DOMAIN_QUICK_EDIT = 'settings_manager.domain_quick_edit';
    public const DOMAIN_COPY = 'settings_manager.domain_copy';
    public const DOMAIN_DELETE = 'settings_manager.domain_delete';

    private function __construct()
    {
    }
}
