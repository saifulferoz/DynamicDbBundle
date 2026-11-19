<?php

namespace Feroz\DynamicDbBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Feroz\DynamicDbBundle\Service\DynamicDbProvider;

class DynamicDbManager
{
    /**
     * @var array<string, EntityManagerInterface>
     */
    private array $entityManagers = [];

    public function __construct(
        private DynamicEntityManagerFactory $factory,
        private ?DynamicDbProvider $provider = null
    ) {
    }

    public function getEntityManager(string $name, array $dbConfig = []): EntityManagerInterface
    {
        if (isset($this->entityManagers[$name])) {
            return $this->entityManagers[$name];
        }

        if (empty($dbConfig) && $this->provider !== null) {
            $dbConfig = $this->provider->findConnectionConfig($name);
        }

        if (empty($dbConfig)) {
            throw new \InvalidArgumentException(sprintf('Dynamic database connection "%s" not found and no configuration provided.', $name));
        }

        $entityManager = $this->factory->createEntityManager($dbConfig);
        $this->entityManagers[$name] = $entityManager;

        return $entityManager;
    }

    public function hasEntityManager(string $name): bool
    {
        return isset($this->entityManagers[$name]);
    }

    /**
     * @return array<string, EntityManagerInterface>
     */
    public function getEntityManagers(): array
    {
        return $this->entityManagers;
    }
}
