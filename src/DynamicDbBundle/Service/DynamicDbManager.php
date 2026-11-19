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
     * @var array<string, string> normalized mapping prefix (with trailing "\") => manager name, longest first
     */
    private array $prefixes = [];

    /**
     * @param array<string, list<array{dir: string, prefix: string}>> $mappings entity mappings per dynamic manager name
     */
    public function __construct(
        private DynamicEntityManagerFactory $factory,
        private ?DynamicDbProvider $provider = null,
        private array $mappings = []
    ) {
        foreach ($mappings as $name => $managerMappings) {
            foreach ($managerMappings as $mapping) {
                $this->prefixes[rtrim($mapping['prefix'], '\\').'\\'] ??= $name;
            }
        }
        // Longest (most specific) prefix first, so the first match wins.
        uksort($this->prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
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

        foreach ($this->prefixes as $prefix => $name) {
            if (str_starts_with($class, $prefix)) {
                return $name;
            }
        }

        return null;
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

    /**
     * Like DoctrineBundle's Registry::reset(): open managers are cleared and kept, so services
     * holding them stay in sync; only closed managers are dropped and rebuilt on next use.
     */
    public function reset(): void
    {
        foreach ($this->entityManagers as $name => $entityManager) {
            if ($entityManager->isOpen()) {
                $entityManager->clear();
            } else {
                $this->resetEntityManager($name);
            }
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
