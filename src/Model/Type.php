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
 * @method static Type CHOICE()
 */
class Type extends Enum
{
    final public const STRING = 'string';
    final public const BOOL = 'bool';
    final public const INT = 'int';
    final public const FLOAT = 'float';
    final public const YAML = 'yaml';
    final public const CHOICE = 'choice';
}
