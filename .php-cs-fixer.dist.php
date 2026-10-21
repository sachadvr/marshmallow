<?php

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude(['assets', 'node_modules', 'public/build', 'var', 'vendor'])
    ->notPath([
        'tests/PHPStan/data/else.php',
        'config/bundles.php',
        'config/reference.php',
    ])
;

return new PhpCsFixer\Config()
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'no_useless_else' => true,
        'no_superfluous_elseif' => true,
        'blank_line_before_statement' => ['statements' => ['break', 'continue', 'declare', 'do', 'for', 'foreach', 'if', 'return', 'switch', 'throw', 'try', 'while', 'yield', 'yield_from']],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
