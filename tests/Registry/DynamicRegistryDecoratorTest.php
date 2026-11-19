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

    public function testGetConnectionsMergesDynamicConnections(): void
    {
        $staticConn = $this->createMock(Connection::class);
        $dynamicConn = $this->createMock(Connection::class);
        
        $this->decorated->method('getConnections')->willReturn(['default' => $staticConn]);
        
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dynamicConn);
        
        $this->dynamicDbManager->method('getEntityManagers')->willReturn(['dynamic_db' => $em]);
        
        $connections = $this->decorator->getConnections();
        $this->assertCount(2, $connections);
        $this->assertSame($staticConn, $connections['default']);
        $this->assertSame($dynamicConn, $connections['dynamic_db']);
    }

    public function testGetConnectionNamesIncludesDynamicNames(): void
    {
        $this->decorated->method('getConnectionNames')->willReturn(['default']);
        
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->dynamicDbManager->method('getEntityManagers')->willReturn(['dynamic_db' => $em]);
        
        $names = $this->decorator->getConnectionNames();
        $this->assertSame(['default', 'dynamic_db'], $names);
    }

    public function testGetManagersMergesDynamicManagers(): void
    {
        $staticEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $dynamicEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        
        $this->decorated->method('getManagers')->willReturn(['default' => $staticEm]);
        $this->dynamicDbManager->method('getEntityManagers')->willReturn(['dynamic_db' => $dynamicEm]);
        
        $managers = $this->decorator->getManagers();
        $this->assertCount(2, $managers);
        $this->assertSame($staticEm, $managers['default']);
        $this->assertSame($dynamicEm, $managers['dynamic_db']);
    }

    public function testGetManagerNamesIncludesDynamicNames(): void
    {
        $this->decorated->method('getManagerNames')->willReturn(['default']);
        
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->dynamicDbManager->method('getEntityManagers')->willReturn(['dynamic_db' => $em]);
        
        $names = $this->decorator->getManagerNames();
        $this->assertSame(['default', 'dynamic_db'], $names);
    }
}
