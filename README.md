# DynamicDbBundle

`DynamicDbBundle` is a Symfony bundle that allows you to store database connection configurations in a database table and seamlessly fetch and instantiate connections and entity managers at runtime.

With this bundle, you can natively use `$doctrine->getConnection('dynamic_name')` just like any conventionally configured connection in your `doctrine.yaml`.

---

## Features

- **Seamless Doctrine Integration**: Fetches database configuration dynamically via Doctrine's `ManagerRegistry`.
- **On-The-Fly Connections**: Creates connections and entity managers completely dynamically without requiring compile-time setup.
- **Auto Cache Rebuilding**: Automatically handles clearing the Symfony cache when your dynamic database connection entities are created, updated, or removed, ensuring any new connections are immediately discoverable.
- **Secure Credentials**: Includes a `SecurityUtil` for encrypting and decrypting sensitive database passwords.

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

Create a standard Doctrine Entity in your application that stores the database connection configurations. **Crucially, this entity must implement `DynamicDbConnectionInterface`** and provide implementations for all connection parameter getters.

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
    
    // ... Implement the interface methods ...

    public function getConnectionName(): string { return $this->connectionName; }
    public function getConnectionString(): ?string { return null; } // Optional: Return Oracle TNS string here
    public function getDatabaseDriver(): string { return 'pdo_mysql'; } // Hardcode or map a column
    public function getDatabaseHost(): string { return $this->dbHost; }
    public function getDatabasePort(): int|string { return 3306; }
    public function getDatabaseName(): string { return $this->dbName; }
    public function getDatabaseUser(): string { return $this->dbUser; }
    public function getDatabasePassword(): string { return $this->dbPassword; }
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

You can use the built-in `SecurityUtil` to encrypt database passwords before validating them to your database, and the bundle will decrypt them automatically when creating the EntityManager.

```php
use Feroz\DynamicDbBundle\Utility\SecurityUtil;

// Encrypt before saving your configuration Entity
$encryptedPassword = SecurityUtil::encrypt('super_secret_password', 'your_secret_key');
$tenant->setDbPassword($encryptedPassword);
```

### How it Works
1. When you call `$doctrine->getConnection('X')`, the wrapped `DynamicRegistryDecorator` intercepts the request.
2. If Doctrine natively doesn't know about connection `X`, `DynamicDbProvider` kicks in.
3. It finds the class implementing `DynamicDbConnectionInterface` dynamically and uses the standard Default EntityManager to fetch the parameters matching `connectionName = 'X'`.
4. The `DynamicEntityManagerFactory` boots up the new ORM connection.
5. The connection is cached locally for the remainder of the request.
