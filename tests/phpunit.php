<?php

declare(strict_types=1);

use Helis\SettingsManagerBundle\Tests\DatabaseInitializer;
use Composer\Autoload\ClassLoader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\AnnotationReader;

/** @var ClassLoader $loader */
$loader = require __DIR__ . '/../vendor/autoload.php';

AnnotationRegistry::registerLoader([$loader, 'loadClass']);
AnnotationReader::addGlobalIgnoredName('group');
AnnotationReader::addGlobalIgnoredName('dataProvider');

DatabaseInitializer::init();

return $loader;
