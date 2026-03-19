<?php

declare(strict_types=1);

namespace ZephyrPHP\Database;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Connection as DBALConnection;

abstract class Migration
{
    protected ?DBALConnection $connection = null;
    protected ?Schema $schema = null;

    public function setConnection(DBALConnection $connection): void
    {
        $this->connection = $connection;
        $this->schema = new Schema();
    }

    abstract public function up(): void;

    abstract public function down(): void;

    protected function createTable(string $name, callable $callback): void
    {
        $table = $this->schema->createTable($name);
        $callback($table);
    }

    protected function dropTable(string $name): void
    {
        $this->schema->dropTable($name);
    }

    protected function table(string $name, callable $callback): void
    {
        $table = $this->schema->getTable($name);
        $callback($table);
    }

    protected function hasTable(string $name): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        return $schemaManager->tablesExist([$name]);
    }

    protected function hasColumn(string $table, string $column): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns($table);

        return isset($columns[$column]);
    }

    public function execute(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $queries = $this->schema->toSql($platform);

        foreach ($queries as $query) {
            $this->connection->executeStatement($query);
        }
    }

    protected function raw(string $sql, array $params = []): void
    {
        $this->connection->executeStatement($sql, $params);
    }
}
