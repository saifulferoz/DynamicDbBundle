<?php

namespace Feroz\DynamicDbBundle\Tests\Functional;

use Feroz\DynamicDbBundle\Registry\DynamicRegistryDecorator;
use Feroz\DynamicDbBundle\Service\DynamicDbManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class BundleIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testDoctrineRegistryIsDecorated(): void
    {
        self::bootKernel();

        $this->assertInstanceOf(DynamicRegistryDecorator::class, self::getContainer()->get('doctrine'));
    }

    public function testDbalRunSqlReachesDynamicConnection(): void
    {
        $kernel = self::bootKernel();
        self::getContainer()->get(DynamicDbManager::class)
            ->getEntityManager('analytics', ['driver' => 'pdo_sqlite', 'dbName' => ':memory:']);

        $tester = new CommandTester((new Application($kernel))->find('dbal:run-sql'));
        $exitCode = $tester->execute(['sql' => 'SELECT 42 AS answer', '--connection' => 'analytics']);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertStringContainsString('42', $tester->getDisplay());
    }

    public function testDbalRunSqlStillReachesStaticConnection(): void
    {
        $kernel = self::bootKernel();

        $tester = new CommandTester((new Application($kernel))->find('dbal:run-sql'));
        $exitCode = $tester->execute(['sql' => 'SELECT 7 AS answer']);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertStringContainsString('7', $tester->getDisplay());
    }
}
