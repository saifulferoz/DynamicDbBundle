<?php

namespace Feroz\DynamicDbBundle\Service;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;

class DynamicEntityManagerFactory
{
    public function createEntityManager(array $dbConfig): EntityManagerInterface
    {
        $connectionParams = [];

        if (!empty($dbConfig['connectionString'])) {
            if (str_contains($dbConfig['connectionString'], '://')) {
                // If the user provided a full Doctrine URL including schema
                $connectionParams['url'] = $dbConfig['connectionString'];
            } else {
                // For Oracle (oci8, pdo_oci) the TNS or Easy Connect string acts as the dbname.
                $connectionParams = [
                    'driver' => $dbConfig['driver'] ?? 'oci8',
                    'user' => $dbConfig['username'] ?? '',
                    'password' => $dbConfig['password'] ?? '',
                    'dbname' => $dbConfig['connectionString'],
                ];
            }
        } else {
            // Default URL builder
            $connectionParams['url'] = ($dbConfig['driver'] ?? '') . '://' . ($dbConfig['username'] ?? '') . ':' . urlencode(
                    $dbConfig['password'] ?? ''
                ) . '@' . ($dbConfig['host'] ?? '') . ':' . ($dbConfig['port'] ?? '') . '/' . ($dbConfig['dbName'] ?? '');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            [__DIR__ . '/../Entity/Other'],
            true
        );

        return new EntityManager(DriverManager::getConnection($connectionParams, $config), $config);
    }
}