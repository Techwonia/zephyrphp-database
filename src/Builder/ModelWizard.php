<?php

declare(strict_types=1);

namespace ZephyrPHP\Database\Builder;

/**
 * ModelWizard - Interactive CLI wizard for building models
 */
class ModelWizard
{
    private Blueprint $blueprint;
    private bool $interactive;

    /**
     * Column type shortcuts for quick entry
     */
    private const TYPE_SHORTCUTS = [
        's' => 'string',
        'str' => 'string',
        't' => 'text',
        'i' => 'integer',
        'int' => 'integer',
        'bi' => 'bigint',
        'si' => 'smallint',
        'b' => 'boolean',
        'bool' => 'boolean',
        'f' => 'float',
        'd' => 'decimal',
        'dt' => 'datetime',
        'date' => 'date',
        'time' => 'time',
        'j' => 'json',
        'json' => 'json',
        'g' => 'guid',
        'uuid' => 'guid',
    ];

    /**
     * Relation type shortcuts
     */
    private const RELATION_SHORTCUTS = [
        'bt' => 'belongsTo',
        'ho' => 'hasOne',
        'hm' => 'hasMany',
        'btm' => 'belongsToMany',
        'm2o' => 'belongsTo',
        'o2m' => 'hasMany',
        'm2m' => 'belongsToMany',
        'o2o' => 'hasOne',
    ];

    public function __construct(string $name, bool $interactive = true)
    {
        $this->blueprint = Blueprint::create($name);
        $this->interactive = $interactive;
    }

    /**
     * Run the interactive wizard
     */
    public function run(): Blueprint
    {
        if (!$this->interactive) {
            return $this->blueprint;
        }

        $this->printHeader();
        $this->configureTable();
        $this->configureColumns();
        $this->configureRelations();
        $this->configureOptions();

        return $this->blueprint;
    }

    /**
     * Parse a quick definition string
     * Format: "name:type:modifiers" or "name:relation:Target"
     *
     * Examples:
     *   "title:string:255"
     *   "email:string:unique"
     *   "price:decimal:10,2"
     *   "user:belongsTo:User"
     *   "posts:hasMany:Post"
     *   "is_active:boolean:default:true"
     */
    public function parseQuickDefinition(string $definition): self
    {
        $parts = explode(':', $definition);
        $name = array_shift($parts);
        $type = array_shift($parts) ?? 'string';

        // Resolve type shortcuts
        $type = self::TYPE_SHORTCUTS[$type] ?? $type;

        // Check if it's a relation
        if (isset(self::RELATION_SHORTCUTS[$type]) || in_array($type, ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany'])) {
            $relationType = self::RELATION_SHORTCUTS[$type] ?? $type;
            $target = array_shift($parts) ?? ucfirst($name);
            $this->addRelation($relationType, $target, $name, $parts);
            return $this;
        }

        // It's a column
        $this->addColumn($name, $type, $parts);
        return $this;
    }

    /**
     * Parse multiple quick definitions
     */
    public function parseQuickDefinitions(array $definitions): self
    {
        foreach ($definitions as $def) {
            $this->parseQuickDefinition($def);
        }
        return $this;
    }

    /**
     * Add a column from parsed definition
     */
    private function addColumn(string $name, string $type, array $modifiers): void
    {
        // Handle length/precision for certain types
        $options = [];

        switch ($type) {
            case 'string':
                if (!empty($modifiers) && is_numeric($modifiers[0])) {
                    $options['length'] = (int) array_shift($modifiers);
                }
                $this->blueprint->string($name, $options['length'] ?? 255);
                break;

            case 'decimal':
            case 'float':
                if (!empty($modifiers) && str_contains($modifiers[0], ',')) {
                    [$precision, $scale] = explode(',', array_shift($modifiers));
                    $options['precision'] = (int) $precision;
                    $options['scale'] = (int) $scale;
                }
                $this->blueprint->column($name, $type, $options);
                break;

            default:
                $this->blueprint->column($name, $type, $options);
        }

        // Apply modifiers
        $this->applyModifiers($modifiers);
    }

    /**
     * Add a relation from parsed definition
     */
    private function addRelation(string $type, string $target, string $property, array $modifiers): void
    {
        switch ($type) {
            case 'belongsTo':
                $this->blueprint->belongsTo($target, $property);
                break;
            case 'hasOne':
                $this->blueprint->hasOne($target, $property);
                break;
            case 'hasMany':
                $this->blueprint->hasMany($target, $property);
                break;
            case 'belongsToMany':
                $this->blueprint->belongsToMany($target, $property);
                break;
        }

        // Apply modifiers
        $this->applyRelationModifiers($modifiers);
    }

    /**
     * Apply column modifiers
     */
    private function applyModifiers(array $modifiers): void
    {
        foreach ($modifiers as $mod) {
            $mod = strtolower($mod);

            if ($mod === 'nullable' || $mod === 'null') {
                $this->blueprint->nullable();
            } elseif ($mod === 'unique') {
                $this->blueprint->unique();
            } elseif ($mod === 'index' || $mod === 'indexed') {
                $this->blueprint->index();
            } elseif ($mod === 'unsigned') {
                $this->blueprint->unsigned();
            } elseif (str_starts_with($mod, 'default:')) {
                $value = substr($mod, 8);
                if ($value === 'true') $value = true;
                elseif ($value === 'false') $value = false;
                elseif (is_numeric($value)) $value = (int) $value;
                $this->blueprint->default($value);
            }
        }
    }

    /**
     * Apply relation modifiers
     */
    private function applyRelationModifiers(array $modifiers): void
    {
        foreach ($modifiers as $mod) {
            $mod = strtolower($mod);

            if ($mod === 'cascade') {
                $this->blueprint->cascade(['persist', 'remove']);
            } elseif ($mod === 'eager') {
                $this->blueprint->eager();
            } elseif ($mod === 'orphan') {
                $this->blueprint->orphanRemoval();
            }
        }
    }

    /**
     * Get the blueprint
     */
    public function getBlueprint(): Blueprint
    {
        return $this->blueprint;
    }

    /**
     * Build and return the generated code
     */
    public function build(): string
    {
        return $this->blueprint->build();
    }

    /**
     * Save the entity to file
     */
    public function save(string $basePath = null): string
    {
        return $this->blueprint->save($basePath);
    }

    // Interactive wizard methods (for CLI use)

    private function printHeader(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════╗\n";
        echo "║     ZephyrPHP Model Builder Wizard       ║\n";
        echo "╚══════════════════════════════════════════╝\n";
        echo "\n";
    }

    private function configureTable(): void
    {
        $default = strtolower($this->blueprint->getName());
        $table = $this->ask("Table name [{$default}]: ", $default);
        $this->blueprint->table($table);
    }

    private function configureColumns(): void
    {
        echo "\n";
        echo "Add columns (enter empty line to finish):\n";
        echo "Format: name:type:modifiers (e.g., title:string:255, email:string:unique)\n";
        echo "Type shortcuts: s=string, i=int, b=bool, t=text, dt=datetime, j=json\n";
        echo "\n";

        while (true) {
            $input = $this->ask('  Column: ');
            if (empty($input)) {
                break;
            }
            try {
                $this->parseQuickDefinition($input);
                echo "    ✓ Added\n";
            } catch (\Exception $e) {
                echo "    ✗ Error: {$e->getMessage()}\n";
            }
        }
    }

    private function configureRelations(): void
    {
        echo "\n";
        echo "Add relationships (enter empty line to finish):\n";
        echo "Format: property:type:Target (e.g., user:belongsTo:User, posts:hasMany:Post)\n";
        echo "Type shortcuts: bt=belongsTo, hm=hasMany, ho=hasOne, btm=belongsToMany\n";
        echo "\n";

        while (true) {
            $input = $this->ask('  Relation: ');
            if (empty($input)) {
                break;
            }
            try {
                $this->parseQuickDefinition($input);
                echo "    ✓ Added\n";
            } catch (\Exception $e) {
                echo "    ✗ Error: {$e->getMessage()}\n";
            }
        }
    }

    private function configureOptions(): void
    {
        echo "\n";

        if ($this->confirm('Enable timestamps (createdAt, updatedAt)?', true)) {
            $this->blueprint->timestamps(true);
        } else {
            $this->blueprint->timestamps(false);
        }

        if ($this->confirm('Enable soft deletes (deletedAt)?', false)) {
            $this->blueprint->softDeletes(true);
        }

        echo "\n";
    }

    private function ask(string $question, string $default = ''): string
    {
        echo $question;
        $input = trim(fgets(STDIN) ?: '');
        return $input === '' ? $default : $input;
    }

    private function confirm(string $question, bool $default = false): bool
    {
        $suffix = $default ? '[Y/n]' : '[y/N]';
        $answer = strtolower($this->ask("{$question} {$suffix}: "));

        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['y', 'yes', '1', 'true']);
    }

    /**
     * Get available column types
     */
    public static function getAvailableTypes(): array
    {
        return [
            'String Types' => ['string', 'char', 'text', 'longText', 'guid'],
            'Numeric Types' => ['integer', 'smallInteger', 'bigInteger', 'float', 'double', 'decimal', 'money'],
            'Boolean' => ['boolean'],
            'Date/Time' => ['date', 'datetime', 'datetimeTz', 'time', 'timestamp'],
            'Immutable Date/Time' => ['dateImmutable', 'datetimeImmutable', 'timeImmutable'],
            'JSON/Array' => ['json', 'jsonb', 'array', 'simpleArray'],
            'Binary' => ['binary', 'blob'],
            'Special' => ['enum', 'ipAddress', 'macAddress', 'morphs'],
        ];
    }

    /**
     * Get available relation types
     */
    public static function getAvailableRelations(): array
    {
        return [
            'belongsTo' => 'Many-to-One (this model belongs to one target)',
            'hasOne' => 'One-to-One (this model has one target)',
            'hasMany' => 'One-to-Many (this model has many targets)',
            'belongsToMany' => 'Many-to-Many (this model belongs to many targets)',
        ];
    }
}
