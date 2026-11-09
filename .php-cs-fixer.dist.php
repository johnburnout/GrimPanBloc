<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    // public/setup : script autonome sans namespace, gabarits PHP/HTML
    ->exclude(['var', 'build', 'public/setup'])
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        // Pas d'alignement en colonnes : une forme de tableau longue décalerait tout le bloc
        'phpdoc_align' => ['align' => 'left'],
    ])
    ->setFinder($finder)
;
