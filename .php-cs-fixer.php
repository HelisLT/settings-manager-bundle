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
        'yoda_style' => false,
        'blank_line_after_opening_tag' => true,
        'declare_strict_types' => true,
        'phpdoc_to_comment' => false,
        'single_line_throw' => false,
        'global_namespace_import' => [
            'import_classes' => true,
        ],
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'array_syntax' => [
            'syntax' => 'short',
        ],
    ])
    ->setFinder($finder)
;
