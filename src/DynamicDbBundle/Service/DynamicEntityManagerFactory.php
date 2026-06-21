<?php

namespace Feroz\DynamicDbBundle\Service;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

class DynamicEntityManagerFactory
{
    private const SCHEME_MAP = [
        'db2'        => 'ibm_db2',
        'mssql'      => 'pdo_sqlsrv',
        'mysql'      => 'pdo_mysql',
        'mysql2'     => 'pdo_mysql',
        'postgres'   => 'pdo_pgsql',
        'postgresql' => 'pdo_pgsql',
        'pgsql'      => 'pdo_pgsql',
        'sqlite'     => 'pdo_sqlite',
        'sqlite3'    => 'pdo_sqlite',
        'oracle'     => 'oci8',
        'oci'        => 'oci8',
        'sqlsrv'     => 'pdo_sqlsrv',
    ];

    public function __construct(
        private EntityManagerInterface $defaultEntityManager
    ) {
    }

    public function createEntityManager(array $dbConfig): EntityManagerInterface
    {
        $connectionParams = [];

        if (!empty($dbConfig['connectionString'])) {
            if (str_contains($dbConfig['connectionString'], '://')) {
                // If the user provided a DSN URL, parse it using DBAL's DsnParser
                $dsnParser = new DsnParser(self::SCHEME_MAP);
                $connectionParams = $dsnParser->parse($dbConfig['connectionString']);
            } else {
                // For legacy or specific drivers like Oracle/SQLite
                $driver = $dbConfig['driver'] ?? 'pdo_mysql';
                if (str_contains($driver, 'sqlite')) {
                    $connectionParams['driver'] = $driver;
                    if ($dbConfig['connectionString'] === ':memory:') {
                        $connectionParams['memory'] = true;
                    } else {
                        $connectionParams['path'] = $dbConfig['connectionString'];
                    }
                } else {
                    // For Oracle (oci8, pdo_oci) the TNS or Easy Connect string acts as the dbname.
                    $connectionParams = [
                        'driver' => $driver,
                        'user' => $dbConfig['username'] ?? '',
                        'password' => $dbConfig['password'] ?? '',
                        'dbname' => $dbConfig['connectionString'],
                    ];
                }
            }
        } else {
            // Map the bundle's config keys directly to DBAL connection parameters.
            $connectionParams['driver'] = $dbConfig['driver'] ?? 'pdo_mysql';
            
            if (str_contains($connectionParams['driver'], 'sqlite')) {
                if (($dbConfig['dbName'] ?? '') === ':memory:') {
                    $connectionParams['memory'] = true;
                } else {
                    $connectionParams['path'] = $dbConfig['dbName'] ?? '';
                }
            } else {
                if (isset($dbConfig['host'])) {
                    $connectionParams['host'] = $dbConfig['host'];
                }
                if (isset($dbConfig['port'])) {
                    $connectionParams['port'] = $dbConfig['port'];
                }
                if (isset($dbConfig['dbName'])) {
                    $connectionParams['dbname'] = $dbConfig['dbName'];
                }
                if (isset($dbConfig['username'])) {
                    $connectionParams['user'] = $dbConfig['username'];
                }
                if (isset($dbConfig['password'])) {
                    $connectionParams['password'] = $dbConfig['password'];
                }
            }
        }

        $config = $this->defaultEntityManager->getConfiguration();

        return new EntityManager(DriverManager::getConnection($connectionParams, $config), $config);
    }
}