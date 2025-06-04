<?php

namespace Feroz\DynamicDbBundle\Service;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Feroz\DynamicDbBundle\Utility\SecurityUtil;

class DynamicEntityManagerFactory
{
    public function __construct(private $secret)
    {
    }

    public function createEntityManager(array $dbConfig): EntityManagerInterface
    {
        $dbConfig['password'] = SecurityUtil::decrypt($dbConfig['password'], $this->secret);
        $connectionParams['url'] = $dbConfig['driver'].'://'.$dbConfig['username'].':'.urlencode(
                $dbConfig['password']
            ).'@'.$dbConfig['host'].'/'.$dbConfig['dbName'];

        $config = ORMSetup::createAttributeMetadataConfiguration(
            [__DIR__.'/../Entity/Other'],
            true
        );

        return new EntityManager(DriverManager::getConnection($connectionParams, $config), $config);
    }
}