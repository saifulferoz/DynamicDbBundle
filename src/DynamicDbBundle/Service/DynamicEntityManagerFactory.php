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
        'mariadb'    => 'pdo_mysql',
        'postgres'   => 'pdo_pgsql',
        'postgresql' => 'pdo_pgsql',
        'pgsql'      => 'pdo_pgsql',
        'sqlite'     => 'pdo_sqlite',
        'sqlite3'    => 'pdo_sqlite',
        'oracle'     => 'oci8',
        'oci'        => 'oci8',
        'pdooci'     => 'pdo_oci',
        'pdooci8'    => 'pdo_oci',
        'sqlsrv'     => 'pdo_sqlsrv',
        'sqlserver'  => 'pdo_sqlsrv',
    ];

    /**
     * Driver names that are valid DBAL drivers as-is; anything else goes
     * through SCHEME_MAP so app-level names (postgresql, mariadb, pdooci, …)
     * resolve to a real DBAL driver instead of failing inside DriverManager.
     */
    private const DBAL_DRIVERS = [
        'pdo_mysql', 'mysqli', 'pdo_pgsql', 'pgsql', 'pdo_sqlite', 'sqlite3',
        'pdo_sqlsrv', 'sqlsrv', 'oci8', 'pdo_oci', 'ibm_db2',
    ];

    /** Drivers this bundle knowingly cannot build a Doctrine EM for. */
    private const NON_DBAL_DRIVERS = ['bigquery', 'mongodb'];

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
                $driver = $this->normalizeDriver($dbConfig['driver'] ?? 'pdo_mysql');
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
            $connectionParams['driver'] = $this->normalizeDriver($dbConfig['driver'] ?? 'pdo_mysql');
            
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

    /**
     * Resolves an application-level driver name to a real DBAL driver, and
     * fails fast with a clear message for drivers DBAL cannot handle at all.
     */
    private function normalizeDriver(string $driver): string
    {
        $driver = strtolower($driver);

        if (in_array($driver, self::NON_DBAL_DRIVERS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Driver "%s" is not a Doctrine DBAL driver; connect to it with its native client at the application level.',
                $driver
            ));
        }

        if (in_array($driver, self::DBAL_DRIVERS, true)) {
            return $driver;
        }

        return self::SCHEME_MAP[$driver] ?? $driver;
    }
}