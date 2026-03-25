<?php

namespace Feroz\DynamicDbBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Feroz\DynamicDbBundle\Service\DynamicDbManager;
use Feroz\DynamicDbBundle\Service\DynamicDbProvider;
use Feroz\DynamicDbBundle\Service\DynamicEntityManagerFactory;
use PHPUnit\Framework\TestCase;

class DynamicDbManagerTest extends TestCase
{
    private $factory;
    private $provider;
    private $manager;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(DynamicEntityManagerFactory::class);
        $this->provider = $this->createMock(DynamicDbProvider::class);
        $this->manager = new DynamicDbManager($this->factory, $this->provider);
    }

    public function testGetEntityManagerWithConfig(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $config = ['driver' => 'pdo_mysql'];
        
        $this->factory->expects($this->once())
            ->method('createEntityManager')
            ->with($config)
            ->willReturn($emMock);
            
        $em = $this->manager->getEntityManager('test_db', $config);
        $this->assertSame($emMock, $em);
        
        $em2 = $this->manager->getEntityManager('test_db');
        $this->assertSame($emMock, $em2);
    }
    
    public function testGetEntityManagerWithoutConfigUsesProvider(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $config = ['driver' => 'pdo_mysql'];
        
        $this->provider->expects($this->once())
            ->method('findConnectionConfig')
            ->with('dynamic_db')
            ->willReturn($config);
            
        $this->factory->expects($this->once())
            ->method('createEntityManager')
            ->with($config)
            ->willReturn($emMock);
            
        $em = $this->manager->getEntityManager('dynamic_db');
        $this->assertSame($emMock, $em);
    }
    
    public function testGetEntityManagerThrowsExceptionWhenNotFound(): void
    {
        $this->provider->expects($this->once())
            ->method('findConnectionConfig')
            ->with('invalid_db')
            ->willReturn(null);
            
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->getEntityManager('invalid_db');
    }
}
