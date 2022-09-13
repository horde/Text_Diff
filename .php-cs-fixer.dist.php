<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    // Lib is for legacy
    ->exclude('lib')
        // doc may contain ancient examples or illustrational shorthand
    ->exclude('doc')
    ->notPath('src/Symfony/Component/Translation/Tests/fixtures/resources.php')
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR12' => true,
    '@PHP81Migration' => true,
    'ordered_imports' => true,
    'strict_param' => true,
    'declare_strict_types' => true,
    'method_argument_space' => true,
    'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)->setFormat('txt')->setRiskyAllowed(true);
;
