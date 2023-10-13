<?php
declare(strict_types=1);

namespace App;

use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;

abstract class AbstractWebTestCase extends WebTestCase
{
    public function loadFixtures(array $groups): ?AbstractExecutor
    {
        $this->createSchemaIfMissing();

        return $this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures($groups);
    }

    private function createSchemaIfMissing()
    {
        /** @var EntityManagerInterface $om */
        $om = $this->getContainer()->get('doctrine')->getManager();
        $om->getConnection()->setNestTransactionsWithSavepoints(true);

        if (!$om->getConnection()->createSchemaManager()->tablesExist(['settings_test_setting'])) {
            $schemaTool = new SchemaTool($om);
            $schemaTool->createSchema($om->getMetadataFactory()->getAllMetadata());
        }
    }
}
