<?php

$finder = PhpCsFixer\Finder::create()->in('src');

return PhpCsFixer\Config::create()
    ->setRules([
        '@Symfony' => true,
        '@PSR2' => true,
        'blank_line_after_namespace' => true,
        'blank_line_before_statement' => ['statements' => ['return', 'throw', 'try']],
        'cast_spaces' => ['space' => 'none'],
        'declare_equal_normalize' => ['space' => 'none'],
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'none'],
        'yoda_style' => false,
        'no_null_property_initialization' => true,
        'declare_strict_types' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
