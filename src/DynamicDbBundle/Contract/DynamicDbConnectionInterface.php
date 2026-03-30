<?php

namespace Feroz\DynamicDbBundle\Contract;

/**
 * Interface DynamicDbConnectionInterface
 * 
 * Implement this interface in your application's entity that stores dynamic 
 * doctrine configuration. The bundle will automatically fetch these configurations
 * at runtime when the connection is requested.
 */
interface DynamicDbConnectionInterface
{
    public function getConnectionName(): string;

    public function getConnectionString(): ?string;

    public function getDatabaseDriver(): string;

    public function getDatabaseHost(): string;

    public function getDatabasePort(): int|string;

    public function getDatabaseName(): string;

    public function getDatabaseUser(): string;

    public function getDatabasePassword(): string;

    public function setSecret(?string $secret): void;
}
