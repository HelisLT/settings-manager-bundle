<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Model;

use MyCLabs\Enum\Enum;

/**
 * Enum Type.
 *
 * @method static Type STRING()
 * @method static Type BOOL()
 * @method static Type INT()
 * @method static Type FLOAT()
 * @method static Type YAML()
 */
class Type extends Enum
{
    public const STRING = 'string';
    public const BOOL = 'bool';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const YAML = 'yaml';
}
