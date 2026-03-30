<?php

namespace Feroz\DynamicDbBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Feroz\DynamicDbBundle\Contract\DynamicDbConnectionInterface;
use Feroz\DynamicDbBundle\Service\DynamicDbProvider;
use PHPUnit\Framework\TestCase;

class DummyConnectionEntity implements DynamicDbConnectionInterface
{
    private ?string $secret = null;

    public function getConnectionName(): string { return 'test_db'; }
    public function getConnectionString(): ?string { return null; }
    public function getDatabaseDriver(): string { return 'pdo_mysql'; }
    public function getDatabaseHost(): string { return '127.0.0.1'; }
    public function getDatabasePort(): int|string { return 3306; }
    public function getDatabaseName(): string { return 'test'; }
    public function getDatabaseUser(): string { return 'root'; }
    public function getDatabasePassword(): string { return 'encrypted_pass'; }

    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }
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

    public function testSecretPassedViaConstructorPropagatesSecretToEntity(): void
    {
        $entity = new DummyConnectionEntity();
        $em = $this->createMock(EntityManagerInterface::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getName')->willReturn(DummyConnectionEntity::class);
        $classMetadata->method('hasField')->willReturn(false);

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$classMetadata]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn([$entity]);

        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getRepository')->willReturn($repository);
        $em->method('getClassMetadata')->willReturn($classMetadata);

        // Secret is now injected via constructor (e.g. from DI container or consumer)
        $provider = new DynamicDbProvider($em, 'my_runtime_secret');

        $config = $provider->findConnectionConfig('test_db');

        // The entity should have received the secret via setSecret()
        $this->assertSame('my_runtime_secret', $entity->getSecret());
        // The config array should still be returned correctly
        $this->assertSame('pdo_mysql', $config['driver']);
        $this->assertSame('encrypted_pass', $config['password']);
    }

    public function testNoSecretInConstructorLeavesEntitySecretNull(): void
    {
        $entity = new DummyConnectionEntity();
        $em = $this->createMock(EntityManagerInterface::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getName')->willReturn(DummyConnectionEntity::class);
        $classMetadata->method('hasField')->willReturn(false);

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$classMetadata]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn([$entity]);

        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getRepository')->willReturn($repository);
        $em->method('getClassMetadata')->willReturn($classMetadata);

        // No secret provided — defaults to null
        $provider = new DynamicDbProvider($em);

        $config = $provider->findConnectionConfig('test_db');

        // No secret was provided → setSecret() is not called → entity secret stays null
        $this->assertNull($entity->getSecret());
        $this->assertIsArray($config);
    }
}
