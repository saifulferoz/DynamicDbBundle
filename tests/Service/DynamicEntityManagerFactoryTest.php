<?php

namespace Feroz\DynamicDbBundle\Tests\Service;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Feroz\DynamicDbBundle\Service\DynamicEntityManagerFactory;
use PHPUnit\Framework\TestCase;

class DynamicEntityManagerFactoryTest extends TestCase
{
    private $defaultEntityManager;
    private $configuration;
    private $factory;

    protected function setUp(): void
    {
        $this->defaultEntityManager = $this->createMock(EntityManagerInterface::class);
        $this->configuration = new Configuration();
        
        // Configure standard settings for real Configuration
        $this->configuration->setProxyDir(sys_get_temp_dir());
        $this->configuration->setProxyNamespace('DynamicDbBundleTests\Proxies');
        
        $metadataDriver = $this->createMock(\Doctrine\Persistence\Mapping\Driver\MappingDriver::class);
        $this->configuration->setMetadataDriverImpl($metadataDriver);
        
        // Mock getConfiguration() to return our real Configuration
        $this->defaultEntityManager->method('getConfiguration')
            ->willReturn($this->configuration);

        $this->factory = new DynamicEntityManagerFactory($this->defaultEntityManager);
    }

    public function testCreateEntityManagerUsesDefaultConfiguration(): void
    {
        $dbConfig = [
            'driver' => 'pdo_sqlite',
            'dbName' => ':memory:',
        ];

        $em = $this->factory->createEntityManager($dbConfig);

        $this->assertInstanceOf(EntityManagerInterface::class, $em);
        $this->assertSame($this->configuration, $em->getConfiguration());
    }

    public function testCreateEntityManagerMapsSqliteMemory(): void
    {
        $dbConfig = [
            'driver' => 'pdo_sqlite',
            'dbName' => ':memory:',
        ];

        $em = $this->factory->createEntityManager($dbConfig);
        $params = $em->getConnection()->getParams();

        $this->assertSame('pdo_sqlite', $params['driver']);
        $this->assertTrue($params['memory']);
        $this->assertArrayNotHasKey('path', $params);
    }

    public function testCreateEntityManagerMapsSqlitePath(): void
    {
        $dbConfig = [
            'driver' => 'pdo_sqlite',
            'dbName' => '/tmp/test.db',
        ];

        $em = $this->factory->createEntityManager($dbConfig);
        $params = $em->getConnection()->getParams();

        $this->assertSame('pdo_sqlite', $params['driver']);
        $this->assertSame('/tmp/test.db', $params['path']);
        $this->assertArrayNotHasKey('memory', $params);
    }

    public function testCreateEntityManagerMapsConnectionStringUrl(): void
    {
        $dbConfig = [
            'connectionString' => 'mysql://user:pass@127.0.0.1:3306/dbname',
        ];

        $em = $this->factory->createEntityManager($dbConfig);
        $params = $em->getConnection()->getParams();

        $this->assertSame('pdo_mysql', $params['driver']);
        $this->assertSame('127.0.0.1', $params['host']);
        $this->assertSame(3306, $params['port']);
        $this->assertSame('user', $params['user']);
        $this->assertSame('pass', $params['password']);
        $this->assertSame('dbname', $params['dbname']);
    }

    public function testCreateEntityManagerMapsConnectionStringSqliteMemory(): void
    {
        $dbConfig = [
            'driver' => 'pdo_sqlite',
            'connectionString' => ':memory:',
        ];

        $em = $this->factory->createEntityManager($dbConfig);
        $params = $em->getConnection()->getParams();

        $this->assertSame('pdo_sqlite', $params['driver']);
        $this->assertTrue($params['memory']);
    }

    public function testCreateEntityManagerMapsConnectionStringOracle(): void
    {
        $dbConfig = [
            'driver' => 'oci8',
            'connectionString' => 'tns_name_or_easy_connect',
            'username' => 'oracle_user',
            'password' => 'oracle_pass',
        ];

        $em = $this->factory->createEntityManager($dbConfig);
        $params = $em->getConnection()->getParams();

        $this->assertSame('oci8', $params['driver']);
        $this->assertSame('oracle_user', $params['user']);
        $this->assertSame('oracle_pass', $params['password']);
        $this->assertSame('tns_name_or_easy_connect', $params['dbname']);
    }

    public function testCreateEntityManagerMapsStandardConfig(): void
    {
        $dbConfig = [
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'port' => 3306,
            'dbName' => 'mydb',
            'username' => 'myuser',
            'password' => 'mypass',
        ];

        $em = $this->factory->createEntityManager($dbConfig);
        $params = $em->getConnection()->getParams();

        $this->assertSame('pdo_mysql', $params['driver']);
        $this->assertSame('localhost', $params['host']);
        $this->assertSame(3306, $params['port']);
        $this->assertSame('mydb', $params['dbname']);
        $this->assertSame('myuser', $params['user']);
        $this->assertSame('mypass', $params['password']);
    }

    /**
     * Application-level driver names (postgresql, mariadb, pdooci, …) are not
     * DBAL driver names; the factory must normalize them before DriverManager
     * sees them.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('driverAliasProvider')]
    public function testCreateEntityManagerNormalizesDriverAliases(string $alias, string $expected): void
    {
        $dbConfig = [
            'driver' => $alias,
            'host' => 'localhost',
            'port' => 5432,
            'dbName' => 'mydb',
            'username' => 'user',
            'password' => 'pass',
        ];

        $em = $this->factory->createEntityManager($dbConfig);

        $this->assertSame($expected, $em->getConnection()->getParams()['driver']);
    }

    public static function driverAliasProvider(): array
    {
        return [
            'postgresql' => ['postgresql', 'pdo_pgsql'],
            'mariadb' => ['mariadb', 'pdo_mysql'],
            'pdooci' => ['pdooci', 'pdo_oci'],
            'sqlserver' => ['sqlserver', 'pdo_sqlsrv'],
            'valid dbal name kept' => ['mysqli', 'mysqli'],
            'case-insensitive' => ['PostgreSQL', 'pdo_pgsql'],
        ];
    }

    public function testCreateEntityManagerRejectsNonDbalDrivers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not a Doctrine DBAL driver');

        $this->factory->createEntityManager([
            'driver' => 'bigquery',
            'host' => 'my_dataset',
            'dbName' => 'my-project',
        ]);
    }
}
