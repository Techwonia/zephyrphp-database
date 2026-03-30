<?php

declare(strict_types=1);

namespace ZephyrPHP\Database;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;

class Connection
{
    private static ?Connection $instance = null;
    private ?DBALConnection $connection = null;
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

    protected function getDefaultConfig(): array
    {
        return [
            'driver' => $_ENV['DB_CONNECTION'] ?? 'pdo_mysql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'dbname' => $_ENV['DB_DATABASE'] ?? 'zephyrphp',
            'user' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        ];
    }

    public function connect(): DBALConnection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $params = $this->config;

        // Add SSL/TLS options if configured
        if (!empty($_ENV['DB_SSL_CA'])) {
            $params['driverOptions'][\PDO::MYSQL_ATTR_SSL_CA] = $_ENV['DB_SSL_CA'];
        }
        if (!empty($_ENV['DB_SSL_CERT'])) {
            $params['driverOptions'][\PDO::MYSQL_ATTR_SSL_CERT] = $_ENV['DB_SSL_CERT'];
        }
        if (!empty($_ENV['DB_SSL_KEY'])) {
            $params['driverOptions'][\PDO::MYSQL_ATTR_SSL_KEY] = $_ENV['DB_SSL_KEY'];
        }

        $lastException = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $this->connection = DriverManager::getConnection($params);
                return $this->connection;
            } catch (Exception $e) {
                $lastException = $e;
                if ($attempt === 1) {
                    usleep(100_000); // 100ms backoff before retry
                }
            }
        }

        // Both attempts failed
        $safeMsg = preg_replace('/password[=:]\s*\S+/i', 'password=***', $lastException->getMessage());
        error_log("Database connection failed after 2 attempts: " . $safeMsg);
        throw new \RuntimeException("Database connection failed. Check your configuration.", 0, $lastException);
    }

    public function getConnection(): ?DBALConnection
    {
        return $this->connection ?? $this->connect();
    }

    public function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connection->isConnected();
    }

    public function reconnect(): DBALConnection
    {
        $this->disconnect();
        return $this->connect();
    }

    public function getConfig(): array
    {
        $safe = $this->config;
        if (isset($safe['password'])) {
            $safe['password'] = '***REDACTED***';
        }
        return $safe;
    }

    /**
     * Execute a callback within a database transaction.
     * Automatically commits on success, rolls back on exception.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $conn = $this->getConnection();
        $conn->beginTransaction();
        try {
            $result = $callback($conn);
            $conn->commit();
            return $result;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        if ($this->connection !== null) {
            $this->reconnect();
        }

        return $this;
    }
}
