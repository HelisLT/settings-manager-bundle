<?php

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\ValueObject\PhpVersion;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withCache(__DIR__.'/tests/app/var/cache/rector', FileCacheStorage::class)
    ->withPaths([__DIR__.'/src'])
    ->withPhpVersion(PhpVersion::PHP_81)
    ->withPHPStanConfigs([__DIR__.'/phpstan.neon.dist'])
    ->withPhpSets(php81: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withSkip([
        \Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector::class,
        \Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector::class,
        \Rector\Php81\Rector\Array_\FirstClassCallableRector::class,
    ])
    ->withImportNames(removeUnusedImports: true);
