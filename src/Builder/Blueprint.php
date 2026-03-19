<?php

declare(strict_types=1);

namespace ZephyrPHP\Database\Builder;

/**
 * Blueprint - Fluent Model Builder for ZephyrPHP
 *
 * Build Doctrine entities using a fluent, chainable API.
 * Unique approach: Think of it as a construction blueprint for your models.
 *
 * Usage:
 *   $blueprint = Blueprint::create('User')
 *       ->table('users')
 *       ->string('name')
 *       ->email('email')->unique()
 *       ->hasMany('Post', 'posts')
 *       ->timestamps();
 */
class Blueprint
{
    private string $name;
    private ?string $namespace = null;
    private ?string $table = null;
    private ?string $repositoryClass = null;
    private array $columns = [];
    private array $relations = [];
    private array $indexes = [];
    private array $traits = [];
    private array $lifecycleCallbacks = [];
    private bool $timestamps = true;
    private bool $softDeletes = false;
    private ?Column $lastColumn = null;
    private ?Relation $lastRelation = null;
    private string $extends = '\\ZephyrPHP\\Database\\Model';
    private ?string $primaryKey = null;
    private string $primaryKeyType = 'integer';
    private string $primaryKeyStrategy = 'AUTO';
    private array $validations = [];

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->table = strtolower($name);
    }

    /**
     * Create a new Blueprint
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * Auto-detect namespace from project's composer.json
     */
    public static function detectNamespace(string $basePath = null): string
    {
        $basePath = $basePath ?? (defined('BASE_PATH') ? BASE_PATH : getcwd());
        $composerFile = $basePath . '/composer.json';

        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            if (isset($composer['autoload']['psr-4'])) {
                foreach ($composer['autoload']['psr-4'] as $namespace => $path) {
                    if ($path === 'app/' || $path === 'app') {
                        return rtrim($namespace, '\\') . '\\Models';
                    }
                }
            }
        }

        return 'App\\Models';
    }

    /**
     * Set the namespace manually (overrides auto-detection)
     */
    public function namespace(string $namespace): self
    {
        $this->namespace = rtrim($namespace, '\\');
        return $this;
    }

    /**
     * Get the namespace (auto-detect if not set)
     */
    public function getNamespace(): string
    {
        if ($this->namespace === null) {
            $this->namespace = self::detectNamespace();
        }
        return $this->namespace;
    }

    /**
     * Set the table name
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set repository class
     */
    public function repository(string $class): self
    {
        $this->repositoryClass = $class;
        return $this;
    }

    /**
     * Set parent class to extend
     */
    public function extends(string $class): self
    {
        $this->extends = $class;
        return $this;
    }

    /**
     * Don't extend any class
     */
    public function standalone(): self
    {
        $this->extends = '';
        $this->timestamps = false;
        return $this;
    }

    /**
     * Configure primary key
     */
    public function primaryKey(string $name = 'id', string $type = 'integer', string $strategy = 'AUTO'): self
    {
        $this->primaryKey = $name;
        $this->primaryKeyType = $type;
        $this->primaryKeyStrategy = $strategy;
        return $this;
    }

    /**
     * Use UUID as primary key
     */
    public function uuid(string $name = 'id'): self
    {
        return $this->primaryKey($name, 'guid', 'UUID');
    }

    // ==========================================
    // COLUMN TYPES - All Doctrine DBAL Types
    // ==========================================

    /**
     * Add a column
     */
    public function column(string $name, string $type, array $options = []): self
    {
        $column = new Column($name, $type, $options);
        $this->columns[$name] = $column;
        $this->lastColumn = $column;
        $this->lastRelation = null;
        return $this;
    }

    // String Types
    public function string(string $name, int $length = 255): self
    {
        return $this->column($name, 'string', ['length' => $length]);
    }

    public function char(string $name, int $length = 255): self
    {
        return $this->column($name, 'string', ['length' => $length, 'fixed' => true]);
    }

    public function text(string $name): self
    {
        return $this->column($name, 'text');
    }

    public function longText(string $name): self
    {
        return $this->column($name, 'text', ['length' => 16777215]);
    }

    public function email(string $name = 'email'): self
    {
        return $this->string($name, 180);
    }

    public function slug(string $name = 'slug'): self
    {
        return $this->string($name, 255);
    }

    public function guid(string $name): self
    {
        return $this->column($name, 'guid');
    }

    // Numeric Types
    public function integer(string $name): self
    {
        return $this->column($name, 'integer');
    }

    public function smallInteger(string $name): self
    {
        return $this->column($name, 'smallint');
    }

    public function bigInteger(string $name): self
    {
        return $this->column($name, 'bigint');
    }

    public function tinyInteger(string $name): self
    {
        return $this->column($name, 'smallint');
    }

    public function unsignedInteger(string $name): self
    {
        return $this->column($name, 'integer', ['unsigned' => true]);
    }

    public function unsignedBigInteger(string $name): self
    {
        return $this->column($name, 'bigint', ['unsigned' => true]);
    }

    public function float(string $name, int $precision = 8, int $scale = 2): self
    {
        return $this->column($name, 'float', ['precision' => $precision, 'scale' => $scale]);
    }

    public function double(string $name, int $precision = 16, int $scale = 4): self
    {
        return $this->column($name, 'float', ['precision' => $precision, 'scale' => $scale]);
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): self
    {
        return $this->column($name, 'decimal', ['precision' => $precision, 'scale' => $scale]);
    }

    public function money(string $name, int $precision = 19, int $scale = 4): self
    {
        return $this->decimal($name, $precision, $scale);
    }

    // Boolean Type
    public function boolean(string $name): self
    {
        return $this->column($name, 'boolean');
    }

    public function bool(string $name): self
    {
        return $this->boolean($name);
    }

    // Date/Time Types
    public function date(string $name): self
    {
        return $this->column($name, 'date');
    }

    public function dateImmutable(string $name): self
    {
        return $this->column($name, 'date_immutable');
    }

    public function datetime(string $name): self
    {
        return $this->column($name, 'datetime');
    }

    public function datetimeImmutable(string $name): self
    {
        return $this->column($name, 'datetime_immutable');
    }

    public function datetimeTz(string $name): self
    {
        return $this->column($name, 'datetimetz');
    }

    public function time(string $name): self
    {
        return $this->column($name, 'time');
    }

    public function timeImmutable(string $name): self
    {
        return $this->column($name, 'time_immutable');
    }

    public function timestamp(string $name): self
    {
        return $this->datetime($name);
    }

    public function year(string $name): self
    {
        return $this->column($name, 'smallint');
    }

    // Binary Types
    public function binary(string $name, int $length = 255): self
    {
        return $this->column($name, 'binary', ['length' => $length]);
    }

    public function blob(string $name): self
    {
        return $this->column($name, 'blob');
    }

    // JSON/Array Types
    public function json(string $name): self
    {
        return $this->column($name, 'json');
    }

    public function jsonb(string $name): self
    {
        return $this->column($name, 'json');
    }

    public function array(string $name): self
    {
        return $this->column($name, 'json');
    }

    public function simpleArray(string $name): self
    {
        return $this->column($name, 'simple_array');
    }

    // Special Types
    public function enum(string $name, array $values): self
    {
        return $this->column($name, 'string', ['enumType' => $values, 'length' => 50]);
    }

    public function ipAddress(string $name = 'ip_address'): self
    {
        return $this->string($name, 45);
    }

    public function macAddress(string $name = 'mac_address'): self
    {
        return $this->string($name, 17);
    }

    public function morphs(string $name): self
    {
        $this->string($name . '_type');
        $this->unsignedBigInteger($name . '_id');
        return $this;
    }

    // ==========================================
    // COLUMN MODIFIERS - Chain after column
    // ==========================================

    /**
     * Make column nullable
     */
    public function nullable(bool $nullable = true): self
    {
        if ($this->lastColumn) {
            $this->lastColumn->nullable($nullable);
        }
        return $this;
    }

    /**
     * Set default value
     */
    public function default(mixed $value): self
    {
        if ($this->lastColumn) {
            $this->lastColumn->default($value);
        }
        return $this;
    }

    /**
     * Make column unique
     */
    public function unique(string $indexName = null): self
    {
        if ($this->lastColumn) {
            $this->lastColumn->unique(true);
            $name = $indexName ?? 'unique_' . $this->lastColumn->getName();
            $this->indexes[$name] = [
                'columns' => [$this->lastColumn->getName()],
                'unique' => true,
            ];
        }
        return $this;
    }

    /**
     * Add index to column
     */
    public function index(string $indexName = null): self
    {
        if ($this->lastColumn) {
            $name = $indexName ?? 'idx_' . $this->lastColumn->getName();
            $this->indexes[$name] = [
                'columns' => [$this->lastColumn->getName()],
                'unique' => false,
            ];
        }
        return $this;
    }

    /**
     * Set column comment
     */
    public function comment(string $comment): self
    {
        if ($this->lastColumn) {
            $this->lastColumn->comment($comment);
        }
        return $this;
    }

    /**
     * Make column unsigned (numeric types)
     */
    public function unsigned(): self
    {
        if ($this->lastColumn) {
            $this->lastColumn->unsigned(true);
        }
        return $this;
    }

    // ==========================================
    // RELATIONSHIPS - All Doctrine Types
    // ==========================================

    /**
     * Add a relationship
     */
    protected function addRelation(string $type, string $target, string $property, array $options = []): self
    {
        $relation = new Relation($type, $target, $property, $options);
        $this->relations[$property] = $relation;
        $this->lastRelation = $relation;
        $this->lastColumn = null;
        return $this;
    }

    /**
     * One-to-One relationship (owning side)
     */
    public function hasOne(string $target, string $property = null, string $inversedBy = null): self
    {
        $property = $property ?? lcfirst($this->classBasename($target));
        return $this->addRelation('OneToOne', $target, $property, [
            'inversedBy' => $inversedBy,
        ]);
    }

    /**
     * One-to-One relationship (inverse side)
     */
    public function belongsToOne(string $target, string $property = null, string $mappedBy = null): self
    {
        $property = $property ?? lcfirst($this->classBasename($target));
        return $this->addRelation('OneToOne', $target, $property, [
            'mappedBy' => $mappedBy,
        ]);
    }

    /**
     * Many-to-One relationship (owning side - foreign key here)
     */
    public function belongsTo(string $target, string $property = null, string $inversedBy = null): self
    {
        $property = $property ?? lcfirst($this->classBasename($target));
        return $this->addRelation('ManyToOne', $target, $property, [
            'inversedBy' => $inversedBy,
        ]);
    }

    /**
     * One-to-Many relationship (inverse side - no foreign key)
     */
    public function hasMany(string $target, string $property = null, string $mappedBy = null): self
    {
        $property = $property ?? lcfirst($this->classBasename($target)) . 's';
        return $this->addRelation('OneToMany', $target, $property, [
            'mappedBy' => $mappedBy ?? lcfirst($this->name),
        ]);
    }

    /**
     * Many-to-Many relationship (owning side)
     */
    public function belongsToMany(string $target, string $property = null, string $joinTable = null): self
    {
        $property = $property ?? lcfirst($this->classBasename($target)) . 's';
        return $this->addRelation('ManyToMany', $target, $property, [
            'joinTable' => $joinTable,
        ]);
    }

    /**
     * Many-to-Many relationship (inverse side)
     */
    public function morphedByMany(string $target, string $property = null, string $mappedBy = null): self
    {
        $property = $property ?? lcfirst($this->classBasename($target)) . 's';
        return $this->addRelation('ManyToMany', $target, $property, [
            'mappedBy' => $mappedBy ?? lcfirst($this->name) . 's',
        ]);
    }

    // ==========================================
    // RELATIONSHIP MODIFIERS
    // ==========================================

    /**
     * Set cascade operations
     */
    public function cascade(array $operations = ['persist', 'remove']): self
    {
        if ($this->lastRelation) {
            $this->lastRelation->cascade($operations);
        }
        return $this;
    }

    /**
     * Set fetch mode to EAGER
     */
    public function eager(): self
    {
        if ($this->lastRelation) {
            $this->lastRelation->fetch('EAGER');
        }
        return $this;
    }

    /**
     * Set fetch mode to LAZY
     */
    public function lazy(): self
    {
        if ($this->lastRelation) {
            $this->lastRelation->fetch('LAZY');
        }
        return $this;
    }

    /**
     * Set fetch mode to EXTRA_LAZY
     */
    public function extraLazy(): self
    {
        if ($this->lastRelation) {
            $this->lastRelation->fetch('EXTRA_LAZY');
        }
        return $this;
    }

    /**
     * Set orphan removal
     */
    public function orphanRemoval(bool $enabled = true): self
    {
        if ($this->lastRelation) {
            $this->lastRelation->orphanRemoval($enabled);
        }
        return $this;
    }

    /**
     * Set order by for collection
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        if ($this->lastRelation) {
            $this->lastRelation->orderBy($field, $direction);
        }
        return $this;
    }

    /**
     * Set join column name
     */
    public function foreignKey(string $column, string $referencedColumn = 'id'): self
    {
        if ($this->lastRelation) {
            $this->lastRelation->joinColumn($column, $referencedColumn);
        }
        return $this;
    }

    // ==========================================
    // INDEXES - Composite & Special
    // ==========================================

    /**
     * Add composite index
     */
    public function compositeIndex(array $columns, string $name = null): self
    {
        $name = $name ?? 'idx_' . implode('_', $columns);
        $this->indexes[$name] = [
            'columns' => $columns,
            'unique' => false,
        ];
        return $this;
    }

    /**
     * Add composite unique index
     */
    public function compositeUnique(array $columns, string $name = null): self
    {
        $name = $name ?? 'unique_' . implode('_', $columns);
        $this->indexes[$name] = [
            'columns' => $columns,
            'unique' => true,
        ];
        return $this;
    }

    // ==========================================
    // TRAITS & FEATURES
    // ==========================================

    /**
     * Add timestamps (createdAt, updatedAt)
     */
    public function timestamps(bool $enabled = true): self
    {
        $this->timestamps = $enabled;
        return $this;
    }

    /**
     * Add soft deletes (deletedAt)
     */
    public function softDeletes(bool $enabled = true): self
    {
        $this->softDeletes = $enabled;
        if ($enabled) {
            $this->traits[] = '\\ZephyrPHP\\Database\\Traits\\SoftDeletes';
        }
        return $this;
    }

    /**
     * Add a trait
     */
    public function useTrait(string $trait): self
    {
        $this->traits[] = $trait;
        return $this;
    }

    // ==========================================
    // LIFECYCLE CALLBACKS
    // ==========================================

    /**
     * Add PrePersist callback
     */
    public function onPrePersist(string $method): self
    {
        $this->lifecycleCallbacks['PrePersist'][] = $method;
        return $this;
    }

    /**
     * Add PostPersist callback
     */
    public function onPostPersist(string $method): self
    {
        $this->lifecycleCallbacks['PostPersist'][] = $method;
        return $this;
    }

    /**
     * Add PreUpdate callback
     */
    public function onPreUpdate(string $method): self
    {
        $this->lifecycleCallbacks['PreUpdate'][] = $method;
        return $this;
    }

    /**
     * Add PostUpdate callback
     */
    public function onPostUpdate(string $method): self
    {
        $this->lifecycleCallbacks['PostUpdate'][] = $method;
        return $this;
    }

    /**
     * Add PreRemove callback
     */
    public function onPreRemove(string $method): self
    {
        $this->lifecycleCallbacks['PreRemove'][] = $method;
        return $this;
    }

    /**
     * Add PostRemove callback
     */
    public function onPostRemove(string $method): self
    {
        $this->lifecycleCallbacks['PostRemove'][] = $method;
        return $this;
    }

    /**
     * Add PostLoad callback
     */
    public function onPostLoad(string $method): self
    {
        $this->lifecycleCallbacks['PostLoad'][] = $method;
        return $this;
    }

    // ==========================================
    // BUILD & GENERATE
    // ==========================================

    /**
     * Build and return the generated code
     */
    public function build(): string
    {
        $generator = new EntityGenerator($this);
        return $generator->generate();
    }

    /**
     * Save the generated entity to file
     */
    public function save(string $basePath = null): string
    {
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $this->name)) {
            throw new \InvalidArgumentException("Invalid class name: {$this->name}");
        }

        $basePath = $basePath ?? (defined('BASE_PATH') ? BASE_PATH : getcwd());
        $path = $basePath . '/app/Models/' . $this->name . '.php';

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = $this->build();
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Get blueprint data for generation
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'namespace' => $this->getNamespace(),
            'table' => $this->table,
            'extends' => $this->extends,
            'repositoryClass' => $this->repositoryClass,
            'primaryKey' => $this->primaryKey,
            'primaryKeyType' => $this->primaryKeyType,
            'primaryKeyStrategy' => $this->primaryKeyStrategy,
            'columns' => array_map(fn($c) => $c->toArray(), $this->columns),
            'relations' => array_map(fn($r) => $r->toArray(), $this->relations),
            'indexes' => $this->indexes,
            'traits' => $this->traits,
            'lifecycleCallbacks' => $this->lifecycleCallbacks,
            'timestamps' => $this->timestamps,
            'softDeletes' => $this->softDeletes,
        ];
    }

    /**
     * Get class basename from fully qualified name
     */
    protected function classBasename(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }

    // Getters
    public function getName(): string { return $this->name; }
    public function getTable(): ?string { return $this->table; }
    public function getExtends(): string { return $this->extends; }
    public function getRepositoryClass(): ?string { return $this->repositoryClass; }
    public function getPrimaryKey(): ?string { return $this->primaryKey; }
    public function getPrimaryKeyType(): string { return $this->primaryKeyType; }
    public function getPrimaryKeyStrategy(): string { return $this->primaryKeyStrategy; }
    public function getColumns(): array { return $this->columns; }
    public function getRelations(): array { return $this->relations; }
    public function getIndexes(): array { return $this->indexes; }
    public function getTraits(): array { return $this->traits; }
    public function getLifecycleCallbacks(): array { return $this->lifecycleCallbacks; }
    public function hasTimestamps(): bool { return $this->timestamps; }
    public function hasSoftDeletes(): bool { return $this->softDeletes; }
    public function getValidations(): array { return $this->validations; }
    public function setValidations(array $validations): self { $this->validations = $validations; return $this; }
}
