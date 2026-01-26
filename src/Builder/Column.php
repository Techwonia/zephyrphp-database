<?php

declare(strict_types=1);

namespace ZephyrPHP\Database\Builder;

/**
 * Column - Represents a database column in the Blueprint
 */
class Column
{
    private string $name;
    private string $type;
    private array $options = [];

    public function __construct(string $name, string $type, array $options = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->options = array_merge([
            'nullable' => false,
            'unique' => false,
            'indexed' => false,
            'default' => null,
            'length' => null,
            'precision' => null,
            'scale' => null,
            'unsigned' => false,
            'fixed' => false,
            'comment' => null,
            'enumType' => null,
        ], $options);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function nullable(bool $nullable = true): self
    {
        $this->options['nullable'] = $nullable;
        return $this;
    }

    public function isNullable(): bool
    {
        return $this->options['nullable'];
    }

    public function unique(bool $unique = true): self
    {
        $this->options['unique'] = $unique;
        return $this;
    }

    public function isUnique(): bool
    {
        return $this->options['unique'];
    }

    public function indexed(bool $indexed = true): self
    {
        $this->options['indexed'] = $indexed;
        return $this;
    }

    public function isIndexed(): bool
    {
        return $this->options['indexed'];
    }

    public function default(mixed $value): self
    {
        $this->options['default'] = $value;
        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->options['default'];
    }

    public function hasDefault(): bool
    {
        return $this->options['default'] !== null;
    }

    public function length(int $length): self
    {
        $this->options['length'] = $length;
        return $this;
    }

    public function getLength(): ?int
    {
        return $this->options['length'];
    }

    public function precision(int $precision): self
    {
        $this->options['precision'] = $precision;
        return $this;
    }

    public function getPrecision(): ?int
    {
        return $this->options['precision'];
    }

    public function scale(int $scale): self
    {
        $this->options['scale'] = $scale;
        return $this;
    }

    public function getScale(): ?int
    {
        return $this->options['scale'];
    }

    public function unsigned(bool $unsigned = true): self
    {
        $this->options['unsigned'] = $unsigned;
        return $this;
    }

    public function isUnsigned(): bool
    {
        return $this->options['unsigned'];
    }

    public function fixed(bool $fixed = true): self
    {
        $this->options['fixed'] = $fixed;
        return $this;
    }

    public function isFixed(): bool
    {
        return $this->options['fixed'];
    }

    public function comment(string $comment): self
    {
        $this->options['comment'] = $comment;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->options['comment'];
    }

    public function getEnumType(): ?array
    {
        return $this->options['enumType'];
    }

    /**
     * Get PHP type hint for this column
     */
    public function getPhpType(): string
    {
        $typeMap = [
            'string' => 'string',
            'text' => 'string',
            'integer' => 'int',
            'smallint' => 'int',
            'bigint' => 'string',
            'boolean' => 'bool',
            'decimal' => 'string',
            'float' => 'float',
            'date' => '\\DateTimeInterface',
            'date_immutable' => '\\DateTimeImmutable',
            'datetime' => '\\DateTimeInterface',
            'datetime_immutable' => '\\DateTimeImmutable',
            'datetimetz' => '\\DateTimeInterface',
            'datetimetz_immutable' => '\\DateTimeImmutable',
            'time' => '\\DateTimeInterface',
            'time_immutable' => '\\DateTimeImmutable',
            'json' => 'array',
            'simple_array' => 'array',
            'binary' => 'string',
            'blob' => 'string',
            'guid' => 'string',
        ];

        $phpType = $typeMap[$this->type] ?? 'mixed';

        if ($this->isNullable()) {
            $phpType = '?' . $phpType;
        }

        return $phpType;
    }

    /**
     * Get default value for PHP property
     */
    public function getPhpDefault(): string
    {
        if ($this->hasDefault()) {
            $default = $this->getDefault();
            if (is_bool($default)) {
                return $default ? 'true' : 'false';
            }
            if (is_string($default)) {
                return "'" . addslashes($default) . "'";
            }
            if (is_array($default)) {
                return '[]';
            }
            if (is_null($default)) {
                return 'null';
            }
            return (string) $default;
        }

        if ($this->isNullable()) {
            return 'null';
        }

        // Type-specific defaults for non-nullable columns
        $defaults = [
            'string' => "''",
            'text' => "''",
            'integer' => '0',
            'smallint' => '0',
            'bigint' => "'0'",
            'boolean' => 'false',
            'decimal' => "'0'",
            'float' => '0.0',
            'json' => '[]',
            'simple_array' => '[]',
            'binary' => "''",
            'blob' => "''",
            'guid' => "''",
        ];

        return $defaults[$this->type] ?? "''";
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'options' => array_filter($this->options, fn($v) => $v !== null && $v !== false),
        ];
    }
}
