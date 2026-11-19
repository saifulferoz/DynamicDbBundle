<?php

namespace Feroz\DynamicDbBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
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
     * Name of the dynamic manager whose configured mapping prefix covers $class (the most
     * specific prefix wins; Doctrine proxy classes resolve to their entity), or null.
     */
    public function getManagerNameForClass(string $class): ?string
    {
        $class = ltrim($class, '\\');
        $pos = strrpos($class, '\\'.Proxy::MARKER.'\\');
        if ($pos !== false) {
            $class = substr($class, $pos + Proxy::MARKER_LENGTH + 2);
        }

        $match = null;
        $matchLength = 0;
        foreach ($this->mappings as $name => $mappings) {
            foreach ($mappings as $mapping) {
                $prefix = rtrim($mapping['prefix'], '\\').'\\';
                if (strlen($prefix) > $matchLength && str_starts_with($class, $prefix)) {
                    $match = $name;
                    $matchLength = strlen($prefix);
                }
            }
        }

        return $match;
    }

    /**
     * Drops a cached dynamic EM (closing its connection) so the next getEntityManager() builds a fresh one.
     */
    public function resetEntityManager(string $name): void
    {
        if (isset($this->entityManagers[$name])) {
            $this->entityManagers[$name]->getConnection()->close();
            unset($this->entityManagers[$name]);
        }
    }

    public function reset(): void
    {
        foreach (array_keys($this->entityManagers) as $name) {
            $this->resetEntityManager($name);
        }
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
