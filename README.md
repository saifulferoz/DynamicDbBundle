# DynamicDbBundle

`DynamicDbBundle` is a Symfony bundle that allows you to store database connection configurations in a database table and seamlessly fetch and instantiate connections and entity managers at runtime.

With this bundle, you can natively use `$doctrine->getConnection('dynamic_name')` just like any conventionally configured connection in your `doctrine.yaml`.

---

## Features

- **Seamless Doctrine Integration**: Fetches database configuration dynamically via Doctrine's `ManagerRegistry`.
- **On-The-Fly Connections**: Creates connections and entity managers completely dynamically without requiring compile-time setup.
- **Auto Cache Rebuilding**: Automatically handles clearing the Symfony cache when your dynamic database connection entities are created, updated, or removed, ensuring any new connections are immediately discoverable.
- **Secure Credentials**: Includes a `SecurityUtil` for encrypting and decrypting sensitive database passwords.
- **Runtime Secret Injection**: Allows the consumer application to supply the decryption secret via Symfony's DI container, which is automatically forwarded to the connection entity via `setSecret()` before the connection config is built.

---

## Installation

Add the bundle to your project via Composer (if published):
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
    public function getConnectionString(): ?string { return null; } // Optional: Return Oracle TNS string here
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

### 3. Password Encryption (Optional)

You can use the built-in `SecurityUtil` to encrypt database passwords before saving them to your database. The bundle will automatically pass your secret to the entity via `setSecret()` before the connection is created.

```php
use Feroz\DynamicDbBundle\Utility\SecurityUtil;

// Encrypt before saving your configuration Entity
$encryptedPassword = SecurityUtil::encrypt('super_secret_password', 'your_secret_key');
$tenant->setDbPassword($encryptedPassword);
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

### How it Works
1. When you call `$doctrine->getConnection('X')`, the wrapped `DynamicRegistryDecorator` intercepts the request.
2. If Doctrine natively doesn't know about connection `X`, `DynamicDbProvider` kicks in.
3. It finds the class implementing `DynamicDbConnectionInterface` dynamically and uses the default EntityManager to fetch the entity matching `connectionName = 'X'`.
4. If a `$secret` was configured (via DI), it calls `$entity->setSecret($secret)` on the fetched entity before building the config.
5. The `DynamicEntityManagerFactory` boots up the new ORM connection using the config.
6. The connection is cached locally for the remainder of the request.
