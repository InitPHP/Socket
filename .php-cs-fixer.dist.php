<?php

declare(strict_types=1);

/*
 * Shared php-cs-fixer configuration for every InitPHP package (ADR-005).
 *
 * This file is identical in all repositories as `.php-cs-fixer.dist.php`.
 * The only package-specific part is the two lines below; scripts/check-cs-config.sh
 * ignores exactly those two lines when it compares a package with this template.
 * Keep each of them on a single line.
 */
$excludeDirs = [];
$excludePaths = [];

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

foreach ($excludeDirs as $excludeDir) {
    $finder->exclude($excludeDir);
}

foreach ($excludePaths as $excludePath) {
    $finder->notPath($excludePath);
}

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS' => true,
        '@PHP8x2Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'single_quote' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters'],
        ],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/build/php-cs-fixer.cache');
