<?php

namespace Feroz\DynamicDbBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Feroz\DynamicDbBundle\Contract\DynamicDbConnectionInterface;

class DynamicDbProvider
{
    public function __construct(
        private EntityManagerInterface $defaultEntityManager
    ) {
    }

    public function findConnectionConfig(string $name): ?array
    {
        $metadataFactory = $this->defaultEntityManager->getMetadataFactory();
        $allMetadata = $metadataFactory->getAllMetadata();

        $entityClass = null;
        foreach ($allMetadata as $metadata) {
            if (is_subclass_of($metadata->getName(), DynamicDbConnectionInterface::class)) {
                $entityClass = $metadata->getName();
                break;
            }
        }

        if (!$entityClass) {
            return null;
        }

        $repository = $this->defaultEntityManager->getRepository($entityClass);
        $classMetadata = $this->defaultEntityManager->getClassMetadata($entityClass);
        
        $searchField = 'connectionName';
        if (!$classMetadata->hasField($searchField) && $classMetadata->hasField('name')) {
            $searchField = 'name';
        }

        if ($classMetadata->hasField($searchField)) {
            $entity = $repository->findOneBy([$searchField => $name]);
        } else {
            // Fallback: fetch all and iterate
            $entities = $repository->findAll();
            $entity = null;
            foreach ($entities as $e) {
                if ($e instanceof DynamicDbConnectionInterface && $e->getConnectionName() === $name) {
                    $entity = $e;
                    break;
                }
            }
        }

        if (!$entity instanceof DynamicDbConnectionInterface) {
            return null;
        }

        return [
            'connectionString' => method_exists($entity, 'getConnectionString') ? $entity->getConnectionString() : null,
            'driver' => $entity->getDatabaseDriver(),
            'host' => $entity->getDatabaseHost(),
            'port' => $entity->getDatabasePort(),
            'dbName' => $entity->getDatabaseName(),
            'username' => $entity->getDatabaseUser(),
            'password' => $entity->getDatabasePassword(),
        ];
    }
}
