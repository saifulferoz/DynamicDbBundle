<?php

namespace Feroz\DynamicDbBundle\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\ConnectionProvider;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Replaces DoctrineBundle's ManagerRegistryAwareConnectionProvider, which type-hints
 * AbstractManagerRegistry and so rejects the decorated "doctrine" registry. Going through
 * the decorator also lets DBAL console commands (dbal:run-sql --connection=x) reach
 * dynamic connections.
 */
class RegistryConnectionProvider implements ConnectionProvider
{
    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function getDefaultConnection(): Connection
    {
        $connection = $this->managerRegistry->getConnection();
        assert($connection instanceof Connection);

        return $connection;
    }

    public function getConnection(string $name): Connection
    {
        $connection = $this->managerRegistry->getConnection($name);
        assert($connection instanceof Connection);

        return $connection;
    }
}
