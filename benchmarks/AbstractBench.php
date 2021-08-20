<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Benchmarks;

/**
 * @BeforeMethods({"setUp"})
 * @AfterMethods({"tearDown"})
 */
abstract class AbstractBench
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }
}
