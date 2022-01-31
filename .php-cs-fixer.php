<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        'src',
        'tests/src/Functional',
        'tests/src/Unit',
    ])
;

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'fopen_flags' => false,
        'protected_to_private' => false,
        'combine_nested_dirname' => true,
        'yoda_style' => false,
        'native_function_invocation' => false,
        'blank_line_after_opening_tag' => true,
        'declare_strict_types' => true,
        'phpdoc_var_without_name' => false,
        'function_declaration' => [
            'closure_function_spacing' => 'none',
        ],
        'cast_spaces' => [
            'space' => 'none',
        ],
    ])
    ->setFinder($finder)
;
