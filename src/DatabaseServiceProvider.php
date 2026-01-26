<?php

declare(strict_types=1);

namespace ZephyrPHP\Database;

use Doctrine\ORM\EntityManagerInterface;
use ZephyrPHP\Container\Container;

class DatabaseServiceProvider
{
    public function register(Container $container): void
    {
        // Register Connection as singleton
        $container->singleton(Connection::class, function () {
            return Connection::getInstance();
        });

        // Register EntityManager as singleton
        $container->singleton(EntityManager::class, function () {
            return EntityManager::getInstance();
        });

        // Register Doctrine's EntityManagerInterface (for controller injection)
        $container->singleton(EntityManagerInterface::class, function () {
            return EntityManager::getInstance()->getEntityManager();
        });

        // Register aliases
        $container->alias('db', Connection::class);
        $container->alias('db.connection', Connection::class);
        $container->alias('em', EntityManager::class);
        $container->alias('entity.manager', EntityManager::class);
    }

    public function boot(): void
    {
        // Initialize the entity manager on boot
        EntityManager::getInstance()->create();
    }
}
