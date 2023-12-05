<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Model;

enum Type: string
{
    case STRING = 'string';
    case BOOL = 'bool';
    case INT = 'int';
    case FLOAT = 'float';
    case YAML = 'yaml';
    case CHOICE = 'choice';
}
