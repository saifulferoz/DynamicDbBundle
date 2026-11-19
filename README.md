# DynamicDbBundle

`DynamicDbBundle` is a Symfony bundle that allows you to store database connection configurations in a database table and seamlessly fetch and instantiate connections and entity managers at runtime.

With this bundle, you can natively use `$doctrine->getConnection('dynamic_name')` or `$doctrine->getManager('dynamic_name')` just like any conventionally configured connection in your `doctrine.yaml`.

---

## Features

- **Seamless Doctrine Integration**: Fetches database configuration dynamically via Doctrine's `ManagerRegistry`.
- **Inherited ORM Configuration**: Dynamically boots new entity managers by automatically inheriting the configuration (metadata mapping drivers, naming strategies, custom functions, filters, etc.) of the default `EntityManager`. No need to redefine entity paths or configs, unless you want a dynamic manager to map its own entities (see [Mapping Entities to a Dynamic Manager](#5-mapping-entities-to-a-dynamic-manager-optional)).
- **Dynamic Connection Discovery**: Extends standard `ManagerRegistry` lookup methods (`getConnections()`, `getConnectionNames()`, `getManagers()`, and `getManagerNames()`) to include dynamically created connections/managers alongside static ones.
- **Universal Database Support**: Supports all database drivers supported by Doctrine DBAL (MySQL, PostgreSQL, SQLite, Oracle, SQL Server, etc.) via structured parameter mapping or DSN URLs.
- **Console Support**: DBAL console commands such as `dbal:run-sql --connection=<name>` and `doctrine:query:sql` work with dynamic connections (DoctrineBundle 2.x and 3.x).
- **Runtime Secret Injection**: Allows the consumer application to supply a decryption secret via Symfony's DI container, which is automatically forwarded to the connection entity via `setSecret()` before the connection config is built.

---

## Installation

Add the bundle to your project via Composer:
```bash
composer require feroz/dynamic-db-bundle
```

Ensure the bundle is registered in your `config/bundles.php`:
```php
return [
    // ...
    Feroz\DynamicDbBundle\DynamicDbBundle::class => ['all' => true],
];
```

---

## Usage Guide

### 1. Create a Configuration Entity

Create a standard Doctrine Entity in your application that stores the database connection configurations. **Crucially, this entity must implement `DynamicDbConnectionInterface`** and provide implementations for all interface methods, including `setSecret()`.

The `setSecret()` method is called automatically by `DynamicDbProvider` before building the connection config, allowing your entity to make the secret available to downstream logic (e.g., for custom password decryption).

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Feroz\DynamicDbBundle\Contract\DynamicDbConnectionInterface;

#[ORM\Entity]
class TenantConnection implements DynamicDbConnectionInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $connectionName = null; // e.g., "tenant_1"

    #[ORM\Column(length: 255)]
    private ?string $dbHost = null;

    #[ORM\Column(length: 255)]
    private ?string $dbName = null;

    #[ORM\Column(length: 255)]
    private ?string $dbUser = null;

    #[ORM\Column(length: 255)]
    private ?string $dbPassword = null;

    private ?string $secret = null;

    // --- DynamicDbConnectionInterface implementation ---

    public function getConnectionName(): string { return $this->connectionName; }
    public function getConnectionString(): ?string { return null; } // Optional: Return DSN URL or Oracle TNS string here
    public function getDatabaseDriver(): string { return 'pdo_mysql'; } // Hardcode or map a column
    public function getDatabaseHost(): string { return $this->dbHost; }
    public function getDatabasePort(): int|string { return 3306; }
    public function getDatabaseName(): string { return $this->dbName; }
    public function getDatabaseUser(): string { return $this->dbUser; }
    public function getDatabasePassword(): string { return $this->dbPassword; }

    /**
     * Called automatically by DynamicDbProvider before the connection config is built.
     * The secret is null when none is configured — implement your decryption logic here.
     */
    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }
}
```

### 2. Fetch the Dynamic Connection

You can fetch the connection or the manager directly through Symfony's core Doctrine integration! The bundle decorates the Doctrine registry to seamlessly integrate.

```php
namespace App\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TenantController extends AbstractController
{
    #[Route('/tenant/{tenantName}', name: 'tenant_dashboard')]
    public function index(string $tenantName, ManagerRegistry $doctrine): Response
    {
        // Behind the scenes, the bundle fetches the row matching $tenantName
        // and initializes the connection seamlessly!
        $connection = $doctrine->getConnection($tenantName);
        
        // Alternatively, grab the dynamic EntityManager
        $entityManager = $doctrine->getManager($tenantName);

        // Run queries for this specific tenant's database
        $results = $connection->executeQuery('SELECT * FROM users')->fetchAllAssociative();

        return $this->json($results);
    }
}
```

### 3. Password Encryption (Consumer Responsibility)

The bundle does **not** provide a built-in encryption utility. Password encryption and decryption are entirely the consumer's responsibility.

The recommended pattern is:

1. **Encrypt** the password before persisting the entity (using any encryption library of your choice).
2. **Decrypt** the password inside `getDatabasePassword()` of your entity, using the secret injected via `setSecret()`.

```php
public function setSecret(?string $secret): void
{
    $this->secret = $secret;
}

public function getDatabasePassword(): string
{
    if ($this->secret !== null) {
        // Decrypt using your own logic / library
        return MyEncryptionHelper::decrypt($this->dbPassword, $this->secret);
    }

    return $this->dbPassword; // Return as-is if no secret configured
}
```

### 4. Passing a Secret via Dependency Injection (for Encrypted Passwords)

`DynamicDbProvider` accepts `$secret` as an **optional constructor parameter**. The recommended approach is to bind it in your application's `services.yaml` using a Symfony parameter (e.g. from an environment variable):

```yaml
# config/services.yaml
parameters:
    dynamic_db_secret: '%env(DYNAMIC_DB_SECRET)%'

services:
    Feroz\DynamicDbBundle\Service\DynamicDbProvider:
        arguments:
            $secret: '%dynamic_db_secret%'
```

With this configuration, `DynamicDbProvider` will automatically call `$entity->setSecret($secret)` on the fetched connection entity before building the connection config, giving the entity access to the secret for custom decryption logic.

If no secret is needed, simply omit the binding — the `$secret` parameter defaults to `null` and `setSecret()` will not be called.

### 5. Mapping Entities to a Dynamic Manager (Optional)

By default a dynamic `EntityManager` reuses the default EM's metadata driver. To give a dynamic manager its own entities (and let `getRepository()` / `getManagerForClass()` route those classes to it), configure attribute mappings per manager name:

```yaml
# config/packages/dynamic_db.yaml
dynamic_db:
    managers:
        analytics:                       # the dynamic connection name
            mappings:
                - { dir: '%kernel.project_dir%/src/Entity/Analytics', prefix: 'App\Entity\Analytics' }
```

- When `mappings` is set, that manager uses **only** these attribute mappings; it no longer loads the default EM's entities.
- `$doctrine->getRepository(Customer::class)` and `getManagerForClass()` resolve a class to the dynamic manager whose prefix covers it (the most specific prefix wins). Configured dynamic prefixes take precedence over static managers, so entities can live under the default `App\Entity` tree. If that dynamic connection can't be found, `getManagerForClass()` returns `null` rather than falling back to the default database.
- The default EM still maps `src/Entity` recursively, so exclude the dynamic directory from your default mapping if schema tools on the default connection should ignore those entities.

---

## How it Works

1. When you call `$doctrine->getConnection('X')` or `$doctrine->getManager('X')`, the wrapped `DynamicRegistryDecorator` intercepts the request.
2. If Doctrine natively doesn't know about connection `X`, `DynamicDbProvider` kicks in.
3. It finds the class implementing `DynamicDbConnectionInterface` dynamically and uses the default EntityManager to fetch the entity matching `connectionName = 'X'`.
4. If a `$secret` was configured (via DI), it calls `$entity->setSecret($secret)` on the fetched entity.
5. The `DynamicEntityManagerFactory` boots up the new ORM connection using the database connection parameters (or parses a DSN URL using Doctrine's `DsnParser`).
6. The new dynamic `EntityManager` is instantiated by inheriting the exact configuration (metadata mappings, naming strategies, proxies, etc.) of the default `EntityManager`.
7. Standard methods like `$doctrine->getConnections()` or `$doctrine->getManagers()` are decorated to dynamically include the newly instantiated connections/managers alongside the statically defined ones.
8. The connection is cached locally for the remainder of the request.
