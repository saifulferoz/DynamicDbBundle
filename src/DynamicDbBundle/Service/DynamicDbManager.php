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

    /**
     * @param array<string, list<array{dir: string, prefix: string}>> $mappings entity mappings per dynamic manager name
     */
    public function __construct(
        private DynamicEntityManagerFactory $factory,
        private ?DynamicDbProvider $provider = null,
        private array $mappings = []
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

        $entityManager = $this->factory->createEntityManager($dbConfig, $this->mappings[$name] ?? []);
        $this->entityManagers[$name] = $entityManager;

        return $entityManager;
    }

    /**
     * Name of the dynamic manager whose configured mapping prefix covers $class, or null.
     */
    public function getManagerNameForClass(string $class): ?string
    {
        $class = ltrim($class, '\\');
        foreach ($this->mappings as $name => $mappings) {
            foreach ($mappings as $mapping) {
                if (str_starts_with($class, rtrim($mapping['prefix'], '\\').'\\')) {
                    return $name;
                }
            }
        }

        return null;
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
