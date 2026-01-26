<?php

declare(strict_types=1);

namespace ZephyrPHP\Database\Builder;

/**
 * Relation - Represents a relationship in the Blueprint
 */
class Relation
{
    private string $type;
    private string $target;
    private string $property;
    private array $options = [];

    public function __construct(string $type, string $target, string $property, array $options = [])
    {
        $this->type = $type;
        $this->target = $target;
        $this->property = $property;
        $this->options = array_merge([
            'mappedBy' => null,
            'inversedBy' => null,
            'cascade' => [],
            'fetch' => 'LAZY',
            'orphanRemoval' => false,
            'orderBy' => null,
            'joinColumn' => null,
            'joinTable' => null,
        ], $options);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function cascade(array $operations): self
    {
        $this->options['cascade'] = $operations;
        return $this;
    }

    public function getCascade(): array
    {
        return $this->options['cascade'];
    }

    public function fetch(string $mode): self
    {
        $this->options['fetch'] = $mode;
        return $this;
    }

    public function getFetch(): string
    {
        return $this->options['fetch'];
    }

    public function orphanRemoval(bool $enabled): self
    {
        $this->options['orphanRemoval'] = $enabled;
        return $this;
    }

    public function hasOrphanRemoval(): bool
    {
        return $this->options['orphanRemoval'];
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->options['orderBy'] = [$field => $direction];
        return $this;
    }

    public function getOrderBy(): ?array
    {
        return $this->options['orderBy'];
    }

    public function joinColumn(string $name, string $referencedColumn = 'id'): self
    {
        $this->options['joinColumn'] = [
            'name' => $name,
            'referencedColumnName' => $referencedColumn,
        ];
        return $this;
    }

    public function getJoinColumn(): ?array
    {
        return $this->options['joinColumn'];
    }

    public function getMappedBy(): ?string
    {
        return $this->options['mappedBy'];
    }

    public function getInversedBy(): ?string
    {
        return $this->options['inversedBy'];
    }

    public function getJoinTable(): ?string
    {
        return $this->options['joinTable'];
    }

    /**
     * Check if this is a collection (OneToMany or ManyToMany)
     */
    public function isCollection(): bool
    {
        return in_array($this->type, ['OneToMany', 'ManyToMany']);
    }

    /**
     * Check if this is the owning side
     */
    public function isOwningSide(): bool
    {
        return $this->options['mappedBy'] === null;
    }

    /**
     * Get PHP type hint for this relation
     */
    public function getPhpType(): string
    {
        if ($this->isCollection()) {
            return 'Collection';
        }

        $className = $this->getTargetClassName();
        return '?' . $className;
    }

    /**
     * Get target class name (without namespace)
     */
    public function getTargetClassName(): string
    {
        $parts = explode('\\', $this->target);
        return end($parts);
    }

    /**
     * Get default value for PHP property
     */
    public function getPhpDefault(): string
    {
        return $this->isCollection() ? '' : 'null';
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'target' => $this->target,
            'property' => $this->property,
            'options' => array_filter($this->options, fn($v) => $v !== null && $v !== false && $v !== []),
        ];
    }
}
