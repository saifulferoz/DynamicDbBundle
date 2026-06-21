<?php

namespace Feroz\DynamicDbBundle\Registry;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Feroz\DynamicDbBundle\Service\DynamicDbManager;

class DynamicRegistryDecorator implements ManagerRegistry
{
    public function __construct(
        private ManagerRegistry $decorated,
        private DynamicDbManager $dynamicDbManager
    ) {
    }

    public function getDefaultConnectionName(): string
    {
        return $this->decorated->getDefaultConnectionName();
    }

    public function getConnection(string|null $name = null): object
    {
        try {
            return $this->decorated->getConnection($name);
        } catch (\InvalidArgumentException $e) {
            try {
                $em = $this->dynamicDbManager->getEntityManager($name);
                return $em->getConnection();
            } catch (\InvalidArgumentException $e2) {
                // If it fails to find dynamically, throw original exception
                throw $e;
            }
        }
    }

    public function getConnections(): array
    {
        $connections = $this->decorated->getConnections();
        foreach ($this->dynamicDbManager->getEntityManagers() as $name => $em) {
            $connections[$name] = $em->getConnection();
        }
        return $connections;
    }

    public function getConnectionNames(): array
    {
        $names = $this->decorated->getConnectionNames();
        foreach (array_keys($this->dynamicDbManager->getEntityManagers()) as $name) {
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        return $names;
    }

    public function getDefaultManagerName(): string
    {
        return $this->decorated->getDefaultManagerName();
    }

    public function getManager(string|null $name = null): ObjectManager
    {
        try {
            return $this->decorated->getManager($name);
        } catch (\InvalidArgumentException $e) {
            try {
                return $this->dynamicDbManager->getEntityManager($name);
            } catch (\InvalidArgumentException $e2) {
                throw $e;
            }
        }
    }

    public function getManagers(): array
    {
        $managers = $this->decorated->getManagers();
        foreach ($this->dynamicDbManager->getEntityManagers() as $name => $em) {
            $managers[$name] = $em;
        }
        return $managers;
    }

    public function resetManager(string|null $name = null): ObjectManager
    {
        return $this->decorated->resetManager($name);
    }

    public function getAliasNamespace(string $alias): string
    {
        // This method was removed in doctrine/persistence 3.0 but exists in older versions.
        // We defer to the decorated if it has it.
        if (method_exists($this->decorated, 'getAliasNamespace')) {
            return $this->decorated->getAliasNamespace($alias);
        }
        throw new \BadMethodCallException('Method getAliasNamespace() is not available in Doctrine ManagerRegistry.');
    }

    public function getManagerNames(): array
    {
        $names = $this->decorated->getManagerNames();
        foreach (array_keys($this->dynamicDbManager->getEntityManagers()) as $name) {
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        return $names;
    }

    public function getRepository(string $persistentObject, string|null $persistentManagerName = null): ObjectRepository
    {
        return $this->decorated->getRepository($persistentObject, $persistentManagerName);
    }

    public function getManagerForClass(string $class): ?ObjectManager
    {
        return $this->decorated->getManagerForClass($class);
    }
}
