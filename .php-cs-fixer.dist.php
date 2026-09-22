<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests'])
;

// Pozor: migrační sada musí odpovídat "php" v composer.json. S @PHP8x4Migration
// by fixer přepsal kód na syntaxi, kterou PHP 8.2 nerozparsuje.
return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PHP8x2Migration' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => ['import_classes' => false, 'import_functions' => false],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'php_unit_method_casing' => false,
    ])
    ->setFinder($finder)
;
