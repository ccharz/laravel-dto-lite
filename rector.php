<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class,
    ])
    ->withAttributesSets(symfony: true, phpunit: true)
    ->withImportNames(removeUnusedImports: true)
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        earlyReturn: true,
        codeQuality: true,
        codingStyle: true,
        privatization: true,
        typeDeclarations: true,
    );
