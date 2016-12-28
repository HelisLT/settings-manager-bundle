<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DatabaseInitializer extends WebTestCase
{
    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public static function init()
    {
        $client = static::createClient();

        $connection = $client->getContainer()->get('doctrine.orm.entity_manager')->getConnection();
        $connectionParams = $connection->getParams();

        unset($connectionParams['dbname'], $connectionParams['path'], $connectionParams['url']);
        $tmpConnection = DriverManager::getConnection($connectionParams);
        $tmpConnection->getSchemaManager()->dropAndCreateDatabase($connection->getDatabase());

        // Need to reload the container, we've lost the connection (db recreated).
        $container = (static::createClient())->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }
}
