<?php

$finder = PhpCsFixer\Finder::create()->in('src');

return PhpCsFixer\Config::create()
    ->setRules([
        '@Symfony' => true,
        '@PSR2' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'single_line_comment_style' => [],
        'yoda_style' => false,
        'no_null_property_initialization' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
