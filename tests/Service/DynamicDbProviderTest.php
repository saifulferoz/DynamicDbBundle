<?php

namespace Feroz\DynamicDbBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Feroz\DynamicDbBundle\Contract\DynamicDbConnectionInterface;
use Feroz\DynamicDbBundle\Service\DynamicDbProvider;
use PHPUnit\Framework\TestCase;

class DummyConnectionEntity implements DynamicDbConnectionInterface {
    public function getConnectionName(): string { return 'test_db'; }
    public function getConnectionString(): ?string { return null; }
    public function getDatabaseDriver(): string { return 'pdo_mysql'; }
    public function getDatabaseHost(): string { return '127.0.0.1'; }
    public function getDatabasePort(): int|string { return 3306; }
    public function getDatabaseName(): string { return 'test'; }
    public function getDatabaseUser(): string { return 'root'; }
    public function getDatabasePassword(): string { return ''; }
}

class DynamicDbProviderTest extends TestCase
{
    public function testFindConnectionConfigReturnsNullIfNoEntityClass(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $metadataFactory->method('getAllMetadata')->willReturn([]);
        
        $provider = new DynamicDbProvider($em);
        $this->assertNull($provider->findConnectionConfig('test_db'));
    }
}
