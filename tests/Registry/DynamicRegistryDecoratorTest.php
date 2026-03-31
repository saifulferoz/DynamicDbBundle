<?php

namespace Feroz\DynamicDbBundle\Tests\Registry;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Feroz\DynamicDbBundle\Registry\DynamicRegistryDecorator;
use Feroz\DynamicDbBundle\Service\DynamicDbManager;
use PHPUnit\Framework\TestCase;

class DynamicRegistryDecoratorTest extends TestCase
{
    private $decorated;
    private $dynamicDbManager;
    private $decorator;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(ManagerRegistry::class);
        $this->dynamicDbManager = $this->createMock(DynamicDbManager::class);
        $this->decorator = new DynamicRegistryDecorator($this->decorated, $this->dynamicDbManager);
    }

    public function testGetConnectionDelegatesToDecorated(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->decorated->expects($this->once())
            ->method('getConnection')
            ->with('default')
            ->willReturn($connection);
            
        $this->assertSame($connection, $this->decorator->getConnection('default'));
    }

    public function testGetConnectionFallsBackToDynamicDbManager(): void
    {
        $this->decorated->expects($this->once())
            ->method('getConnection')
            ->with('dynamic')
            ->willThrowException(new \InvalidArgumentException());
            
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);
        
        $this->dynamicDbManager->expects($this->once())
            ->method('getEntityManager')
            ->with('dynamic')
            ->willReturn($em);
            
        $this->assertSame($connection, $this->decorator->getConnection('dynamic'));
    }
}
