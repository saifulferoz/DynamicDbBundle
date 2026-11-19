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
        $this->decorated->method('getConnectionNames')->willReturn(['default' => 'doctrine.dbal.default_connection']);
        
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->dynamicDbManager->method('getEntityManagers')->willReturn(['dynamic_db' => $em]);
        
        $names = $this->decorator->getConnectionNames();
        $this->assertSame(['default' => 'doctrine.dbal.default_connection', 'dynamic_db' => 'dynamic_db'], $names);
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
        $this->decorated->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
        
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->dynamicDbManager->method('getEntityManagers')->willReturn(['dynamic_db' => $em, 'default' => $em]);
        
        $names = $this->decorator->getManagerNames();
        // keyed by name like Doctrine's registry; a static name is never overwritten
        $this->assertSame(['default' => 'doctrine.orm.default_entity_manager', 'dynamic_db' => 'dynamic_db'], $names);
    }

    public function testResetManagerRebuildsDynamicManager(): void
    {
        $freshEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->dynamicDbManager->method('hasEntityManager')->with('analytics')->willReturn(true);
        $this->dynamicDbManager->expects($this->once())->method('resetEntityManager')->with('analytics');
        $this->dynamicDbManager->method('getEntityManager')->with('analytics')->willReturn($freshEm);
        $this->decorated->expects($this->never())->method('resetManager');

        $this->assertSame($freshEm, $this->decorator->resetManager('analytics'));
    }

    public function testResetAlsoResetsDynamicManagers(): void
    {
        $this->dynamicDbManager->expects($this->once())->method('reset');

        $this->decorator->reset();
    }

    public function testGetManagerForClassUsesStaticManagerWithoutDynamicMapping(): void
    {
        $staticEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->decorated->method('getManagerForClass')->willReturn($staticEm);
        $this->dynamicDbManager->method('getManagerNameForClass')->willReturn(null);
        $this->dynamicDbManager->expects($this->never())->method('getEntityManager');

        $this->assertSame($staticEm, $this->decorator->getManagerForClass('App\\Entity\\User'));
    }

    public function testGetManagerForClassUsesDynamicManager(): void
    {
        $dynamicEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->decorated->method('getManagerForClass')->willReturn(null);
        $this->dynamicDbManager->method('getManagerNameForClass')->with('App\\Entity\\Analytics\\Customer')->willReturn('analytics');
        $this->dynamicDbManager->method('getEntityManager')->with('analytics')->willReturn($dynamicEm);

        $this->assertSame($dynamicEm, $this->decorator->getManagerForClass('App\\Entity\\Analytics\\Customer'));
    }

    public function testGetManagerForClassPrefersDynamicMappingOverOverlappingStaticManager(): void
    {
        // The default EM maps App\Entity, so it would also claim App\Entity\Analytics\Customer.
        $dynamicEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->decorated->expects($this->never())->method('getManagerForClass');
        $this->dynamicDbManager->method('getManagerNameForClass')->willReturn('analytics');
        $this->dynamicDbManager->method('getEntityManager')->with('analytics')->willReturn($dynamicEm);

        $this->assertSame($dynamicEm, $this->decorator->getManagerForClass('App\\Entity\\Analytics\\Customer'));
    }

    public function testGetManagerForClassReturnsNullWhenDynamicConnectionIsUnavailable(): void
    {
        $this->decorated->expects($this->never())->method('getManagerForClass');
        $this->dynamicDbManager->method('getManagerNameForClass')->willReturn('analytics');
        $this->dynamicDbManager->method('getEntityManager')->willThrowException(new \InvalidArgumentException());

        $this->assertNull($this->decorator->getManagerForClass('App\\Entity\\Analytics\\Customer'));
    }

    public function testGetManagerForClassReturnsNullForUnknownClass(): void
    {
        $this->decorated->method('getManagerForClass')->willReturn(null);
        $this->dynamicDbManager->method('getManagerNameForClass')->willReturn(null);

        $this->assertNull($this->decorator->getManagerForClass('Foo\\Bar'));
    }

    public function testGetRepositoryUsesDynamicManagerForClass(): void
    {
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $dynamicEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $dynamicEm->method('getRepository')->with('App\\Entity\\Analytics\\Customer')->willReturn($repository);
        $this->decorated->method('getManager')->with('analytics')->willThrowException(new \InvalidArgumentException());
        $this->dynamicDbManager->method('getManagerNameForClass')->willReturn('analytics');
        $this->dynamicDbManager->method('getEntityManager')->with('analytics')->willReturn($dynamicEm);

        $this->assertSame($repository, $this->decorator->getRepository('App\\Entity\\Analytics\\Customer'));
    }

    public function testGetRepositoryThrowsInsteadOfUsingDefaultManagerWhenDynamicConnectionIsUnavailable(): void
    {
        $this->decorated->method('getManager')->with('analytics')->willThrowException(new \InvalidArgumentException());
        $this->decorated->expects($this->never())->method('getManagerForClass');
        $this->dynamicDbManager->method('getManagerNameForClass')->willReturn('analytics');
        $this->dynamicDbManager->method('getEntityManager')->willThrowException(new \InvalidArgumentException());

        $this->expectException(\InvalidArgumentException::class);
        $this->decorator->getRepository('App\\Entity\\Analytics\\Customer');
    }

    public function testResetManagerBuildsConfiguredDynamicManagerNotYetLoaded(): void
    {
        $freshEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->decorated->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
        $this->decorated->expects($this->never())->method('resetManager');
        $this->dynamicDbManager->method('hasEntityManager')->willReturn(false);
        $this->dynamicDbManager->method('getEntityManager')->with('analytics')->willReturn($freshEm);

        $this->assertSame($freshEm, $this->decorator->resetManager('analytics'));
    }

    public function testResetManagerDelegatesStaticNames(): void
    {
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->decorated->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
        $this->decorated->expects($this->once())->method('resetManager')->with('default')->willReturn($em);
        $this->dynamicDbManager->expects($this->never())->method('resetEntityManager');

        $this->assertSame($em, $this->decorator->resetManager('default'));
    }

    public function testGetRepositoryWithDynamicManagerName(): void
    {
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $dynamicEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $dynamicEm->method('getRepository')->willReturn($repository);
        $this->decorated->method('getManager')->with('analytics')->willThrowException(new \InvalidArgumentException());
        $this->dynamicDbManager->method('getEntityManager')->with('analytics')->willReturn($dynamicEm);

        $this->assertSame($repository, $this->decorator->getRepository('App\\Entity\\Analytics\\Customer', 'analytics'));
    }

    public function testGetRepositoryFallsBackToDefaultManagerWithoutMappings(): void
    {
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $defaultEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $defaultEm->method('getRepository')->with('Foo\\Bar')->willReturn($repository);
        $this->decorated->method('getManagerForClass')->willReturn(null);
        $this->decorated->method('getManager')->with(null)->willReturn($defaultEm);
        $this->dynamicDbManager->method('getManagerNameForClass')->willReturn(null);
        $this->dynamicDbManager->expects($this->never())->method('getEntityManager');

        $this->assertSame($repository, $this->decorator->getRepository('Foo\\Bar'));
    }
}
