<?php

declare(strict_types=1);

namespace ZephyrPHP\Database\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * SoftDeletes Trait - Adds soft delete functionality to entities
 *
 * When using this trait, entities are not permanently deleted from the database.
 * Instead, a deletedAt timestamp is set.
 */
trait SoftDeletes
{
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    /**
     * Get the deleted at timestamp
     */
    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    /**
     * Set the deleted at timestamp
     */
    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * Check if the entity is soft deleted
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Check if the entity is not soft deleted (alias for trashed check)
     */
    public function isTrashed(): bool
    {
        return $this->isDeleted();
    }

    /**
     * Soft delete the entity
     */
    public function softDelete(): self
    {
        $this->deletedAt = new \DateTime();
        return $this;
    }

    /**
     * Restore a soft deleted entity
     */
    public function restore(): self
    {
        $this->deletedAt = null;
        return $this;
    }

    /**
     * Force delete - permanently remove from database.
     *
     * This trait cannot perform permanent deletion on its own.
     * Use EntityManager directly to permanently delete an entity.
     *
     * @throws \RuntimeException Always, with instructions on how to permanently delete.
     */
    public function forceDelete(): void
    {
        throw new \RuntimeException('Use EntityManager::remove() and EntityManager::flush() to permanently delete.');
    }
}
