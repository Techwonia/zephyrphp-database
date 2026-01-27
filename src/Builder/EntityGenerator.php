<?php

declare(strict_types=1);

namespace ZephyrPHP\Database\Builder;

/**
 * EntityGenerator - Generates Doctrine entity PHP code from Blueprint
 */
class EntityGenerator
{
    private Blueprint $blueprint;
    private array $uses = [];

    public function __construct(Blueprint $blueprint)
    {
        $this->blueprint = $blueprint;
    }

    /**
     * Generate the complete entity PHP code
     */
    public function generate(): string
    {
        $this->uses = [];
        $this->collectUseStatements();

        $parts = [];
        $parts[] = '<?php';
        $parts[] = '';
        $parts[] = 'declare(strict_types=1);';
        $parts[] = '';
        $parts[] = 'namespace ' . $this->blueprint->getNamespace() . ';';
        $parts[] = '';
        $parts[] = $this->generateUseStatements();
        $parts[] = $this->generateClassAttributes();
        $parts[] = $this->generateClassDeclaration();
        $parts[] = '{';
        $parts[] = $this->generateTraits();
        $parts[] = $this->generateValidationRules();
        $parts[] = $this->generateProperties();
        $parts[] = $this->generateConstructor();
        $parts[] = $this->generateGettersSetters();
        $parts[] = $this->generateRelationMethods();
        $parts[] = $this->generateLifecycleMethods();
        $parts[] = $this->generateValidationMethods();
        $parts[] = '}';
        $parts[] = '';

        return implode("\n", array_filter($parts, fn($p) => $p !== null));
    }

    /**
     * Collect all use statements needed
     */
    private function collectUseStatements(): void
    {
        $this->uses['Doctrine\\ORM\\Mapping as ORM'] = true;

        // Check if we need Collection
        foreach ($this->blueprint->getRelations() as $relation) {
            if ($relation->isCollection()) {
                $this->uses['Doctrine\\Common\\Collections\\ArrayCollection'] = true;
                $this->uses['Doctrine\\Common\\Collections\\Collection'] = true;
                break;
            }
        }

        // Add parent class if extending
        $extends = $this->blueprint->getExtends();
        if ($extends && $extends !== '') {
            // Always add the use statement (strip leading backslash if present)
            $this->uses[ltrim($extends, '\\')] = true;
        }

        // Add traits
        foreach ($this->blueprint->getTraits() as $trait) {
            // Always add the use statement (strip leading backslash if present)
            $this->uses[ltrim($trait, '\\')] = true;
        }
    }

    /**
     * Generate use statements
     */
    private function generateUseStatements(): string
    {
        $uses = array_keys($this->uses);
        sort($uses);

        $lines = [];
        foreach ($uses as $use) {
            $lines[] = 'use ' . ltrim($use, '\\') . ';';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Generate class-level ORM attributes
     */
    private function generateClassAttributes(): string
    {
        $attrs = [];

        // Entity attribute
        $entityOptions = [];
        if ($this->blueprint->getRepositoryClass()) {
            $entityOptions[] = 'repositoryClass: ' . $this->blueprint->getRepositoryClass() . '::class';
        }
        $entityAttr = '#[ORM\\Entity' . ($entityOptions ? '(' . implode(', ', $entityOptions) . ')' : '') . ']';
        $attrs[] = $entityAttr;

        // Table attribute
        $tableOptions = [];
        $tableName = $this->blueprint->getTable() ?: strtolower($this->blueprint->getName()) . 's';
        $tableOptions[] = "name: '{$tableName}'";

        // Collect unique constraints from columns with unique: true
        // This generates named constraints like 'tablename_columnname_unique'
        // which is the industry standard and allows proper error message extraction
        $uniqueConstraints = [];
        foreach ($this->blueprint->getColumns() as $column) {
            if ($column->isUnique()) {
                $constraintName = "{$tableName}_{$column->getName()}_unique";
                $uniqueConstraints[$constraintName] = ['columns' => [$column->getName()]];
            }
        }

        // Add explicit indexes from blueprint
        $indexes = $this->blueprint->getIndexes();
        if (!empty($indexes)) {
            foreach ($indexes as $name => $config) {
                if ($config['unique']) {
                    $uniqueConstraints[$name] = ['columns' => $config['columns']];
                }
            }
        }

        // Generate UniqueConstraint attributes
        if (!empty($uniqueConstraints)) {
            $uniqueAttrs = [];
            foreach ($uniqueConstraints as $name => $config) {
                $cols = "columns: ['" . implode("', '", $config['columns']) . "']";
                $uniqueAttrs[] = "new ORM\\UniqueConstraint(name: '{$name}', {$cols})";
            }
            $tableOptions[] = 'uniqueConstraints: [' . implode(', ', $uniqueAttrs) . ']';
        }

        // Generate Index attributes (non-unique)
        if (!empty($indexes)) {
            $indexAttrs = [];
            foreach ($indexes as $name => $config) {
                if (!$config['unique']) {
                    $cols = "columns: ['" . implode("', '", $config['columns']) . "']";
                    $indexAttrs[] = "new ORM\\Index(name: '{$name}', {$cols})";
                }
            }
            if (!empty($indexAttrs)) {
                $tableOptions[] = 'indexes: [' . implode(', ', $indexAttrs) . ']';
            }
        }

        $attrs[] = '#[ORM\\Table(' . implode(', ', $tableOptions) . ')]';

        // Add HasLifecycleCallbacks if needed
        if (!empty($this->blueprint->getLifecycleCallbacks()) || !$this->blueprint->getExtends()) {
            $attrs[] = '#[ORM\\HasLifecycleCallbacks]';
        }

        return implode("\n", $attrs);
    }

    /**
     * Generate class declaration line
     */
    private function generateClassDeclaration(): string
    {
        $extends = $this->blueprint->getExtends();
        $className = $this->blueprint->getName();

        if ($extends && $extends !== '') {
            $parentClass = $this->getShortClassName($extends);
            return "class {$className} extends {$parentClass}";
        }

        return "class {$className}";
    }

    /**
     * Generate trait use statements
     */
    private function generateTraits(): ?string
    {
        $traits = $this->blueprint->getTraits();
        if (empty($traits)) {
            return null;
        }

        $traitNames = array_map(fn($t) => $this->getShortClassName($t), $traits);
        return '    use ' . implode(', ', $traitNames) . ';' . "\n";
    }

    /**
     * Generate all property declarations
     */
    private function generateProperties(): string
    {
        $lines = [];

        // Primary key (only if standalone or custom)
        if (!$this->blueprint->getExtends() || $this->blueprint->getPrimaryKey()) {
            $lines[] = $this->generatePrimaryKey();
        }

        // Columns
        foreach ($this->blueprint->getColumns() as $column) {
            $lines[] = $this->generateColumnProperty($column);
        }

        // Relations
        foreach ($this->blueprint->getRelations() as $relation) {
            $lines[] = $this->generateRelationProperty($relation);
        }

        // Soft delete column if standalone
        if ($this->blueprint->hasSoftDeletes() && !$this->blueprint->getExtends()) {
            $lines[] = $this->generateSoftDeleteProperty();
        }

        // Timestamps if standalone
        if ($this->blueprint->hasTimestamps() && !$this->blueprint->getExtends()) {
            $lines[] = $this->generateTimestampProperties();
        }

        return implode("\n", array_filter($lines));
    }

    /**
     * Generate primary key property
     */
    private function generatePrimaryKey(): string
    {
        $name = $this->blueprint->getPrimaryKey() ?? 'id';
        $type = $this->blueprint->getPrimaryKeyType();
        $strategy = $this->blueprint->getPrimaryKeyStrategy();

        $phpType = $type === 'guid' ? 'string' : 'int';

        $lines = [];
        $lines[] = '    #[ORM\\Id]';

        if ($strategy === 'UUID') {
            $lines[] = '    #[ORM\\GeneratedValue(strategy: \'CUSTOM\')]';
            $lines[] = '    #[ORM\\CustomIdGenerator(class: \'Ramsey\\Uuid\\Doctrine\\UuidGenerator\')]';
        } else {
            $lines[] = '    #[ORM\\GeneratedValue]';
        }

        $lines[] = "    #[ORM\\Column(type: '{$type}')]";
        $lines[] = "    private ?{$phpType} \${$name} = null;";
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate a column property
     */
    private function generateColumnProperty(Column $column): string
    {
        $lines = [];
        $attrParts = [];

        $attrParts[] = "type: '" . $column->getType() . "'";

        if ($column->getLength()) {
            $attrParts[] = 'length: ' . $column->getLength();
        }

        if ($column->getPrecision()) {
            $attrParts[] = 'precision: ' . $column->getPrecision();
        }

        if ($column->getScale()) {
            $attrParts[] = 'scale: ' . $column->getScale();
        }

        if ($column->isNullable()) {
            $attrParts[] = 'nullable: true';
        }

        // Note: unique constraints are handled at table level with named constraints
        // e.g., #[ORM\Table(uniqueConstraints: [new ORM\UniqueConstraint(name: 'tablename_column_unique', columns: ['column'])])]
        // This allows proper extraction of field names from database error messages

        if ($column->getComment()) {
            $attrParts[] = "options: ['comment' => '" . addslashes($column->getComment()) . "']";
        }

        $lines[] = '    #[ORM\\Column(' . implode(', ', $attrParts) . ')]';

        $phpType = $column->getPhpType();
        $default = $column->getPhpDefault();
        $lines[] = "    private {$phpType} \${$column->getName()} = {$default};";
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate a relation property
     */
    private function generateRelationProperty(Relation $relation): string
    {
        $lines = [];
        $attrParts = [];

        $targetClass = $relation->getTargetClassName();
        $attrParts[] = "targetEntity: {$targetClass}::class";

        if ($relation->getMappedBy()) {
            $attrParts[] = "mappedBy: '" . $relation->getMappedBy() . "'";
        }

        if ($relation->getInversedBy()) {
            $attrParts[] = "inversedBy: '" . $relation->getInversedBy() . "'";
        }

        if (!empty($relation->getCascade())) {
            $cascades = "['" . implode("', '", $relation->getCascade()) . "']";
            $attrParts[] = "cascade: {$cascades}";
        }

        if ($relation->getFetch() !== 'LAZY') {
            $attrParts[] = "fetch: '" . $relation->getFetch() . "'";
        }

        if ($relation->hasOrphanRemoval()) {
            $attrParts[] = 'orphanRemoval: true';
        }

        $lines[] = '    #[ORM\\' . $relation->getType() . '(' . implode(', ', $attrParts) . ')]';

        // JoinColumn for ManyToOne and owning OneToOne
        // Generate named foreign key constraints for proper error message extraction
        // Format: tablename_fieldname_fk (industry standard)
        if (in_array($relation->getType(), ['ManyToOne', 'OneToOne']) && $relation->isOwningSide()) {
            $joinCol = $relation->getJoinColumn();
            $tableName = $this->blueprint->getTable() ?: strtolower($this->blueprint->getName()) . 's';

            if ($joinCol) {
                $columnName = $joinCol['name'];
                $referencedColumn = $joinCol['referencedColumnName'];
            } else {
                $columnName = strtolower($relation->getProperty()) . '_id';
                $referencedColumn = 'id';
            }

            // Generate named FK constraint: tablename_columnname_fk
            $fkConstraintName = "{$tableName}_{$columnName}_fk";

            $joinParts = [
                "name: '{$columnName}'",
                "referencedColumnName: '{$referencedColumn}'",
            ];

            // Add onDelete if specified in options
            $onDelete = $relation->getOption('onDelete');
            if ($onDelete) {
                $joinParts[] = "onDelete: '{$onDelete}'";
            }

            // Add named constraint option for proper error extraction
            $joinParts[] = "options: ['foreignKeyName' => '{$fkConstraintName}']";

            $lines[] = '    #[ORM\\JoinColumn(' . implode(', ', $joinParts) . ')]';
        }

        // JoinTable for owning ManyToMany
        if ($relation->getType() === 'ManyToMany' && $relation->isOwningSide()) {
            $joinTable = $relation->getJoinTable();
            if ($joinTable) {
                $lines[] = "    #[ORM\\JoinTable(name: '{$joinTable}')]";
            }
        }

        // OrderBy for collections
        if ($relation->getOrderBy()) {
            $orderBy = $relation->getOrderBy();
            $orderStr = "['" . key($orderBy) . "' => '" . current($orderBy) . "']";
            $lines[] = "    #[ORM\\OrderBy({$orderStr})]";
        }

        // Property declaration
        if ($relation->isCollection()) {
            $lines[] = "    private Collection \${$relation->getProperty()};";
        } else {
            $lines[] = "    private ?{$targetClass} \${$relation->getProperty()} = null;";
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate soft delete property
     */
    private function generateSoftDeleteProperty(): string
    {
        $lines = [];
        $lines[] = "    #[ORM\\Column(type: 'datetime', nullable: true)]";
        $lines[] = '    private ?\DateTimeInterface $deletedAt = null;';
        $lines[] = '';
        return implode("\n", $lines);
    }

    /**
     * Generate timestamp properties
     */
    private function generateTimestampProperties(): string
    {
        $lines = [];
        $lines[] = "    #[ORM\\Column(type: 'datetime', nullable: true)]";
        $lines[] = '    private ?\DateTimeInterface $createdAt = null;';
        $lines[] = '';
        $lines[] = "    #[ORM\\Column(type: 'datetime', nullable: true)]";
        $lines[] = '    private ?\DateTimeInterface $updatedAt = null;';
        $lines[] = '';
        return implode("\n", $lines);
    }

    /**
     * Generate constructor
     */
    private function generateConstructor(): string
    {
        $collections = [];
        foreach ($this->blueprint->getRelations() as $relation) {
            if ($relation->isCollection()) {
                $collections[] = $relation->getProperty();
            }
        }

        if (empty($collections)) {
            return '';
        }

        $lines = [];
        $lines[] = '    public function __construct()';
        $lines[] = '    {';
        foreach ($collections as $prop) {
            $lines[] = "        \$this->{$prop} = new ArrayCollection();";
        }
        $lines[] = '    }';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate getters and setters for columns
     */
    private function generateGettersSetters(): string
    {
        $lines = [];

        // Primary key getter (only if standalone or custom)
        if (!$this->blueprint->getExtends() || $this->blueprint->getPrimaryKey()) {
            $pkName = $this->blueprint->getPrimaryKey() ?? 'id';
            $pkType = $this->blueprint->getPrimaryKeyType() === 'guid' ? 'string' : 'int';
            $lines[] = $this->generateGetter($pkName, "?{$pkType}");
        }

        // Column getters/setters
        foreach ($this->blueprint->getColumns() as $column) {
            $lines[] = $this->generateGetter($column->getName(), $column->getPhpType());
            $lines[] = $this->generateSetter($column->getName(), $column->getPhpType());
        }

        // Timestamp getters/setters if standalone
        if ($this->blueprint->hasTimestamps() && !$this->blueprint->getExtends()) {
            $lines[] = $this->generateGetter('createdAt', '?\DateTimeInterface');
            $lines[] = $this->generateSetter('createdAt', '\DateTimeInterface');
            $lines[] = $this->generateGetter('updatedAt', '?\DateTimeInterface');
            $lines[] = $this->generateSetter('updatedAt', '\DateTimeInterface');
        }

        // Soft delete getters/setters if standalone
        if ($this->blueprint->hasSoftDeletes() && !$this->blueprint->getExtends()) {
            $lines[] = $this->generateGetter('deletedAt', '?\DateTimeInterface');
            $lines[] = $this->generateSetter('deletedAt', '?\DateTimeInterface');
        }

        return implode("\n", array_filter($lines));
    }

    /**
     * Generate a getter method
     */
    private function generateGetter(string $name, string $type): string
    {
        $methodName = 'get' . ucfirst($name);
        $returnType = str_starts_with($type, '?') ? $type : $type;

        $lines = [];
        $lines[] = "    public function {$methodName}(): {$returnType}";
        $lines[] = '    {';
        $lines[] = "        return \$this->{$name};";
        $lines[] = '    }';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate a setter method
     */
    private function generateSetter(string $name, string $type): string
    {
        $methodName = 'set' . ucfirst($name);
        $paramType = str_replace('?', '', $type);

        $lines = [];
        $lines[] = "    public function {$methodName}({$paramType} \${$name}): self";
        $lines[] = '    {';
        $lines[] = "        \$this->{$name} = \${$name};";
        $lines[] = '        return $this;';
        $lines[] = '    }';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate relation helper methods
     */
    private function generateRelationMethods(): string
    {
        $lines = [];

        foreach ($this->blueprint->getRelations() as $relation) {
            if ($relation->isCollection()) {
                $lines[] = $this->generateCollectionMethods($relation);
            } else {
                $lines[] = $this->generateSingleRelationMethods($relation);
            }
        }

        return implode("\n", array_filter($lines));
    }

    /**
     * Generate methods for single relations (ManyToOne, OneToOne)
     */
    private function generateSingleRelationMethods(Relation $relation): string
    {
        $prop = $relation->getProperty();
        $target = $relation->getTargetClassName();
        $methodName = ucfirst($prop);

        $lines = [];

        // Getter
        $lines[] = "    public function get{$methodName}(): ?{$target}";
        $lines[] = '    {';
        $lines[] = "        return \$this->{$prop};";
        $lines[] = '    }';
        $lines[] = '';

        // Setter
        $lines[] = "    public function set{$methodName}(?{$target} \${$prop}): self";
        $lines[] = '    {';
        $lines[] = "        \$this->{$prop} = \${$prop};";
        $lines[] = '        return $this;';
        $lines[] = '    }';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate methods for collection relations (OneToMany, ManyToMany)
     */
    private function generateCollectionMethods(Relation $relation): string
    {
        $prop = $relation->getProperty();
        $target = $relation->getTargetClassName();
        $singular = rtrim($prop, 's');
        $methodNamePlural = ucfirst($prop);
        $methodNameSingular = ucfirst($singular);

        $lines = [];

        // Getter
        $lines[] = "    public function get{$methodNamePlural}(): Collection";
        $lines[] = '    {';
        $lines[] = "        return \$this->{$prop};";
        $lines[] = '    }';
        $lines[] = '';

        // Add method
        $lines[] = "    public function add{$methodNameSingular}({$target} \${$singular}): self";
        $lines[] = '    {';
        $lines[] = "        if (!\$this->{$prop}->contains(\${$singular})) {";
        $lines[] = "            \$this->{$prop}->add(\${$singular});";

        // Set inverse side for OneToMany
        if ($relation->getType() === 'OneToMany' && $relation->getMappedBy()) {
            $setter = 'set' . ucfirst($relation->getMappedBy());
            $lines[] = "            \${$singular}->{$setter}(\$this);";
        }

        $lines[] = '        }';
        $lines[] = '        return $this;';
        $lines[] = '    }';
        $lines[] = '';

        // Remove method
        $lines[] = "    public function remove{$methodNameSingular}({$target} \${$singular}): self";
        $lines[] = '    {';
        $lines[] = "        if (\$this->{$prop}->removeElement(\${$singular})) {";

        // Unset inverse side for OneToMany
        if ($relation->getType() === 'OneToMany' && $relation->getMappedBy()) {
            $setter = 'set' . ucfirst($relation->getMappedBy());
            $lines[] = "            \${$singular}->{$setter}(null);";
        }

        $lines[] = '        }';
        $lines[] = '        return $this;';
        $lines[] = '    }';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate lifecycle callback methods
     */
    private function generateLifecycleMethods(): string
    {
        $lines = [];
        $callbacks = $this->blueprint->getLifecycleCallbacks();

        // Add default timestamp callbacks if standalone with timestamps
        if ($this->blueprint->hasTimestamps() && !$this->blueprint->getExtends()) {
            $lines[] = '    #[ORM\\PrePersist]';
            $lines[] = '    public function onPrePersist(): void';
            $lines[] = '    {';
            $lines[] = '        $this->createdAt = new \DateTime();';
            $lines[] = '        $this->updatedAt = new \DateTime();';
            $lines[] = '    }';
            $lines[] = '';

            $lines[] = '    #[ORM\\PreUpdate]';
            $lines[] = '    public function onPreUpdate(): void';
            $lines[] = '    {';
            $lines[] = '        $this->updatedAt = new \DateTime();';
            $lines[] = '    }';
            $lines[] = '';
        }

        // Custom lifecycle callbacks
        foreach ($callbacks as $event => $methods) {
            foreach ($methods as $method) {
                $lines[] = "    #[ORM\\{$event}]";
                $lines[] = "    public function {$method}(): void";
                $lines[] = '    {';
                $lines[] = '        // TODO: Implement lifecycle callback';
                $lines[] = '    }';
                $lines[] = '';
            }
        }

        return implode("\n", array_filter($lines));
    }

    /**
     * Get short class name from fully qualified name
     */
    private function getShortClassName(string $class): string
    {
        $parts = explode('\\', ltrim($class, '\\'));
        return end($parts);
    }

    /**
     * Generate validation rules for the model
     * Outputs both $rules/$messages for Validator class and VALIDATION_RULES constant
     */
    private function generateValidationRules(): string
    {
        $validations = $this->blueprint->getValidations();
        if (empty($validations)) {
            return '';
        }

        $lines = [];

        // Generate $rules static property (string format for ZephyrPHP\Validation\Validator)
        $lines[] = '    /**';
        $lines[] = '     * Validation rules for this model';
        $lines[] = '     * Used by controllers with ZephyrPHP\\Validation\\Validator';
        $lines[] = '     */';
        $lines[] = '    public static array $rules = [';

        foreach ($validations as $field => $rules) {
            // Convert array of rules to pipe-separated string
            $rulesStr = implode('|', $rules);
            $lines[] = "        '{$field}' => '{$rulesStr}',";
        }

        $lines[] = '    ];';
        $lines[] = '';

        // Generate $messages static property
        $lines[] = '    /**';
        $lines[] = '     * Custom validation messages';
        $lines[] = '     */';
        $lines[] = '    public static array $messages = [';

        foreach ($validations as $field => $rules) {
            foreach ($rules as $rule) {
                $baseRule = str_contains($rule, ':') ? explode(':', $rule)[0] : $rule;
                $message = $this->getValidationMessage($field, $baseRule, $rule);
                if ($message) {
                    $lines[] = "        '{$field}.{$baseRule}' => '{$message}',";
                }
            }
        }

        $lines[] = '    ];';
        $lines[] = '';

        // Also generate VALIDATION_RULES constant (array format for inline validation)
        $lines[] = '    /**';
        $lines[] = '     * Validation rules as array (for inline validation)';
        $lines[] = '     */';
        $lines[] = '    public const VALIDATION_RULES = [';

        foreach ($validations as $field => $rules) {
            $rulesStr = "'" . implode("', '", $rules) . "'";
            $lines[] = "        '{$field}' => [{$rulesStr}],";
        }

        $lines[] = '    ];';
        $lines[] = '';

        // Also generate regex patterns if any
        $hasRegex = false;
        foreach ($validations as $field => $rules) {
            foreach ($rules as $rule) {
                if (str_starts_with($rule, 'regex:')) {
                    $hasRegex = true;
                    break 2;
                }
            }
        }

        if ($hasRegex) {
            $lines[] = '    /**';
            $lines[] = '     * Custom regex patterns for validation';
            $lines[] = '     */';
            $lines[] = '    public const VALIDATION_PATTERNS = [';
            foreach ($validations as $field => $rules) {
                foreach ($rules as $rule) {
                    if (str_starts_with($rule, 'regex:')) {
                        $pattern = substr($rule, 6);
                        $lines[] = "        '{$field}' => '{$pattern}',";
                    }
                }
            }
            $lines[] = '    ];';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Get human-readable validation message for a rule
     */
    private function getValidationMessage(string $field, string $baseRule, string $fullRule): ?string
    {
        $fieldLabel = ucfirst(str_replace('_', ' ', $field));

        return match($baseRule) {
            'required' => "{$fieldLabel} is required.",
            'email' => "{$fieldLabel} must be a valid email address.",
            'url' => "{$fieldLabel} must be a valid URL.",
            'phone' => "{$fieldLabel} must be a valid phone number.",
            'alpha' => "{$fieldLabel} must contain only letters.",
            'alphanum' => "{$fieldLabel} must contain only letters and numbers.",
            'slug' => "{$fieldLabel} must be a valid URL slug.",
            'ip' => "{$fieldLabel} must be a valid IP address.",
            'json' => "{$fieldLabel} must be valid JSON.",
            'positive' => "{$fieldLabel} must be a positive number.",
            'negative' => "{$fieldLabel} must be a negative number.",
            'past' => "{$fieldLabel} must be a past date.",
            'future' => "{$fieldLabel} must be a future date.",
            'after_today' => "{$fieldLabel} must be today or later.",
            'min' => $this->getMinMessage($fieldLabel, $field, $fullRule),
            'max' => $this->getMaxMessage($fieldLabel, $field, $fullRule),
            'regex' => "{$fieldLabel} format is invalid.",
            default => null,
        };
    }

    /**
     * Check if a field is a numeric type
     */
    private function isNumericField(string $field): bool
    {
        $columns = $this->blueprint->getColumns();
        if (!isset($columns[$field])) {
            return false;
        }

        $column = $columns[$field];
        $numericTypes = ['integer', 'smallint', 'bigint', 'float', 'decimal'];
        return in_array($column->getType(), $numericTypes);
    }

    private function getMinMessage(string $fieldLabel, string $field, string $rule): string
    {
        $value = str_contains($rule, ':') ? explode(':', $rule)[1] : '0';
        if ($this->isNumericField($field)) {
            return "{$fieldLabel} must be at least {$value}.";
        }
        return "{$fieldLabel} must be at least {$value} characters.";
    }

    private function getMaxMessage(string $fieldLabel, string $field, string $rule): string
    {
        $value = str_contains($rule, ':') ? explode(':', $rule)[1] : '255';
        if ($this->isNumericField($field)) {
            return "{$fieldLabel} must not exceed {$value}.";
        }
        return "{$fieldLabel} must not exceed {$value} characters.";
    }

    /**
     * Generate validation helper methods
     */
    private function generateValidationMethods(): string
    {
        $validations = $this->blueprint->getValidations();
        if (empty($validations)) {
            return '';
        }

        $lines = [];

        // Generate validate() method
        $lines[] = '    /**';
        $lines[] = '     * Validate the model data';
        $lines[] = '     * @return array Array of validation errors (empty if valid)';
        $lines[] = '     */';
        $lines[] = '    public function validate(): array';
        $lines[] = '    {';
        $lines[] = '        $errors = [];';
        $lines[] = '';

        foreach ($validations as $field => $rules) {
            $getter = 'get' . ucfirst($field);
            $lines[] = "        // Validate {$field}";
            $lines[] = "        \$value = \$this->{$getter}();";

            foreach ($rules as $rule) {
                if ($rule === 'required') {
                    $lines[] = "        if (\$value === null || \$value === '') {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} is required';";
                    $lines[] = '        }';
                } elseif ($rule === 'email') {
                    $lines[] = "        if (\$value !== null && \$value !== '' && !filter_var(\$value, FILTER_VALIDATE_EMAIL)) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must be a valid email address';";
                    $lines[] = '        }';
                } elseif ($rule === 'url') {
                    $lines[] = "        if (\$value !== null && \$value !== '' && !filter_var(\$value, FILTER_VALIDATE_URL)) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must be a valid URL';";
                    $lines[] = '        }';
                } elseif ($rule === 'phone') {
                    $lines[] = "        if (\$value !== null && \$value !== '' && !preg_match('/^[+]?[0-9\\s\\-().]{7,20}$/', \$value)) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must be a valid phone number';";
                    $lines[] = '        }';
                } elseif ($rule === 'alpha') {
                    $lines[] = "        if (\$value !== null && \$value !== '' && !ctype_alpha(\$value)) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must contain only letters';";
                    $lines[] = '        }';
                } elseif ($rule === 'alphanum') {
                    $lines[] = "        if (\$value !== null && \$value !== '' && !ctype_alnum(\$value)) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must contain only letters and numbers';";
                    $lines[] = '        }';
                } elseif ($rule === 'slug') {
                    $lines[] = "        if (\$value !== null && \$value !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', \$value)) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must be a valid URL slug';";
                    $lines[] = '        }';
                } elseif ($rule === 'ip') {
                    $lines[] = "        if (\$value !== null && \$value !== '' && !filter_var(\$value, FILTER_VALIDATE_IP)) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must be a valid IP address';";
                    $lines[] = '        }';
                } elseif ($rule === 'json') {
                    $lines[] = "        if (\$value !== null && \$value !== '') {";
                    $lines[] = "            json_decode(\$value);";
                    $lines[] = "            if (json_last_error() !== JSON_ERROR_NONE) {";
                    $lines[] = "                \$errors['{$field}'][] = '{$field} must be valid JSON';";
                    $lines[] = '            }';
                    $lines[] = '        }';
                } elseif ($rule === 'positive') {
                    $lines[] = "        if (\$value !== null && \$value <= 0) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must be a positive number';";
                    $lines[] = '        }';
                } elseif ($rule === 'negative') {
                    $lines[] = "        if (\$value !== null && \$value >= 0) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must be a negative number';";
                    $lines[] = '        }';
                } elseif ($rule === 'past') {
                    $lines[] = "        if (\$value !== null && \$value > new \\DateTime()) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must be a past date';";
                    $lines[] = '        }';
                } elseif ($rule === 'future') {
                    $lines[] = "        if (\$value !== null && \$value < new \\DateTime()) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must be a future date';";
                    $lines[] = '        }';
                } elseif ($rule === 'after_today') {
                    $lines[] = "        if (\$value !== null && \$value < new \\DateTime('today')) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} must be today or later';";
                    $lines[] = '        }';
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = substr($rule, 4);
                    $lines[] = "        if (\$value !== null) {";
                    $lines[] = "            if (is_string(\$value) && strlen(\$value) < {$min}) {";
                    $lines[] = "                \$errors['{$field}'][] = '{$field} must be at least {$min} characters';";
                    $lines[] = "            } elseif (is_numeric(\$value) && \$value < {$min}) {";
                    $lines[] = "                \$errors['{$field}'][] = '{$field} must be at least {$min}';";
                    $lines[] = '            }';
                    $lines[] = '        }';
                } elseif (str_starts_with($rule, 'max:')) {
                    $max = substr($rule, 4);
                    $lines[] = "        if (\$value !== null) {";
                    $lines[] = "            if (is_string(\$value) && strlen(\$value) > {$max}) {";
                    $lines[] = "                \$errors['{$field}'][] = '{$field} must not exceed {$max} characters';";
                    $lines[] = "            } elseif (is_numeric(\$value) && \$value > {$max}) {";
                    $lines[] = "                \$errors['{$field}'][] = '{$field} must not exceed {$max}';";
                    $lines[] = '            }';
                    $lines[] = '        }';
                } elseif (str_starts_with($rule, 'regex:')) {
                    $pattern = substr($rule, 6);
                    $pattern = str_replace("'", "\\'", $pattern);
                    $lines[] = "        if (\$value !== null && \$value !== '' && !preg_match('{$pattern}', \$value)) {";
                    $lines[] = "            \$errors['{$field}'][] = '{$field} format is invalid';";
                    $lines[] = '        }';
                }
            }

            $lines[] = '';
        }

        $lines[] = '        return $errors;';
        $lines[] = '    }';
        $lines[] = '';

        // Generate isValid() method
        $lines[] = '    /**';
        $lines[] = '     * Check if the model data is valid';
        $lines[] = '     */';
        $lines[] = '    public function isValid(): bool';
        $lines[] = '    {';
        $lines[] = '        return empty($this->validate());';
        $lines[] = '    }';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
