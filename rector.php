<?php

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\FirstClassCallableRector;
use Rector\Php81\Rector\Class_\MyCLabsClassToEnumRector;
use Rector\Php81\Rector\MethodCall\MyCLabsMethodCallToEnumConstRector;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory(__DIR__.'/tests/app/var/cache/rector');

    $rectorConfig->paths([
        __DIR__.'/src',
    ]);

    $rectorConfig->phpVersion(PhpVersion::PHP_81);
    $rectorConfig->phpstanConfig(__DIR__.'/phpstan.neon.dist');

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::PHP_80,
        SetList::PHP_81,
    ]);

    $rectorConfig->skip([
        SimplifyEmptyCheckOnEmptyArrayRector::class,
        MyCLabsClassToEnumRector::class,
        MyCLabsMethodCallToEnumConstRector::class,
        FirstClassCallableRector::class,
    ]);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses();
};
