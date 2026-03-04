<?php

declare(strict_types=1);

namespace ZephyrPHP\Database;

use Doctrine\ORM\EntityManager as DoctrineEntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\DriverManager;

class EntityManager
{
    private static ?EntityManager $instance = null;
    private ?EntityManagerInterface $em = null;
    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful when config changes)
     */
    public static function resetInstance(): void
    {
        if (self::$instance !== null) {
            self::$instance->close();
            self::$instance = null;
        }
    }

    protected function getDefaultConfig(): array
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);

        // Check which models directory exists
        $modelPaths = [];
        if (is_dir($basePath . '/app/Models')) {
            $modelPaths[] = $basePath . '/app/Models';
        }
        if (is_dir($basePath . '/models')) {
            $modelPaths[] = $basePath . '/models';
        }
        // Fallback to app/Models if neither exists (will error later with clear message)
        if (empty($modelPaths)) {
            $modelPaths[] = $basePath . '/app/Models';
        }

        return [
            'connection' => [
                'driver' => $_ENV['DB_CONNECTION'] ?? 'pdo_mysql',
                'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                'dbname' => $_ENV['DB_DATABASE'] ?? 'zephyrphp',
                'user' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            ],
            'paths' => $modelPaths,
            'isDevMode' => ($_ENV['APP_ENV'] ?? $_ENV['ENV'] ?? 'dev') !== 'production',
            'proxyDir' => $basePath . '/storage/proxies',
            'cache' => null,
        ];
    }

    public function create(): EntityManagerInterface
    {
        if ($this->em !== null) {
            return $this->em;
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: $this->config['paths'],
            isDevMode: $this->config['isDevMode'],
            proxyDir: $this->config['proxyDir'],
            cache: $this->config['cache']
        );

        $connection = DriverManager::getConnection($this->config['connection'], $config);

        $this->em = new DoctrineEntityManager($connection, $config);

        return $this->em;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em ?? $this->create();
    }

    public function em(): EntityManagerInterface
    {
        return $this->getEntityManager();
    }

    /**
     * Add an entity path (for modules to register their models)
     */
    public function addPath(string $path): void
    {
        if (!in_array($path, $this->config['paths'], true)) {
            $this->config['paths'][] = $path;
        }

        // If EntityManager already created, add path to metadata driver directly
        if ($this->em !== null) {
            $driver = $this->em->getConfiguration()->getMetadataDriverImpl();
            if (method_exists($driver, 'addPaths')) {
                $driver->addPaths([$path]);
            }
        }
    }

    public function close(): void
    {
        if ($this->em !== null) {
            $this->em->close();
            $this->em = null;
        }
    }

    public function clear(): void
    {
        if ($this->em !== null) {
            $this->em->clear();
        }
    }

    public function flush(): void
    {
        if ($this->em !== null) {
            $this->em->flush();
        }
    }

    public function persist(object $entity): void
    {
        $this->getEntityManager()->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->getEntityManager()->remove($entity);
    }

    public function find(string $className, mixed $id): ?object
    {
        return $this->getEntityManager()->find($className, $id);
    }

    public function getRepository(string $className)
    {
        return $this->getEntityManager()->getRepository($className);
    }

    public function beginTransaction(): void
    {
        $this->getEntityManager()->beginTransaction();
    }

    public function commit(): void
    {
        $this->getEntityManager()->commit();
    }

    public function rollback(): void
    {
        $this->getEntityManager()->rollback();
    }

    public function transactional(callable $func): mixed
    {
        return $this->getEntityManager()->wrapInTransaction($func);
    }
}
