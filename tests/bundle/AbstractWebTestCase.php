<?php
declare(strict_types=1);

namespace App;

use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractWebTestCase extends WebTestCase
{
    public function loadFixtures(array $groups): ?AbstractExecutor
    {
        $this->createSchemaIfMissing();

        return static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get()
            ->loadFixtures($groups);
    }

    private function createSchemaIfMissing(): void
    {
        /** @var EntityManagerInterface $om */
        $om = static::getContainer()->get('doctrine')->getManager();
        $om->getConnection()->setNestTransactionsWithSavepoints(true);

        if (!$om->getConnection()->createSchemaManager()->tablesExist(['settings_test_setting'])) {
            $schemaTool = new SchemaTool($om);
            $schemaTool->createSchema($om->getMetadataFactory()->getAllMetadata());
        }
    }
}
