<?php

declare(strict_types=1);

// Support LARAVEL_PATH env var or fall back to relative path
$projectRoot = getenv('LARAVEL_PATH') ?: dirname(__DIR__, 2);

if (! file_exists($projectRoot . '/vendor/autoload.php')) {
    echo json_encode(['error' => "Laravel project not found at: {$projectRoot}"]);
    exit(1);
}

require $projectRoot . '/vendor/autoload.php';

$app = require_once $projectRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Schema;

$excludedFolders = ['Overrides', 'Scopes', 'Traits', 'Concerns'];

function getModelClass(string $modelName): ?string
{
    $possiblePaths = [
        "App\\Models\\{$modelName}",
        "App\\Models\\" . str_replace('/', '\\', $modelName),
    ];

    foreach ($possiblePaths as $class) {
        if (class_exists($class)) {
            return $class;
        }
    }

    return null;
}

function listAllModels(): array
{
    global $excludedFolders, $projectRoot;
    $modelsPath = $projectRoot . '/app/Models';
    $models = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($modelsPath),
            function ($current) use ($excludedFolders) {
                if ($current->isDir()) {
                    return ! in_array($current->getFilename(), $excludedFolders);
                }
                return true;
            }
        )
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relativePath = str_replace($modelsPath . '/', '', $file->getPathname());
            $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $fullClass = "App\\Models\\{$className}";

            try {
                if (class_exists($fullClass) && is_subclass_of($fullClass, Model::class)) {
                    $reflection = new \ReflectionClass($fullClass);
                    if (! $reflection->isAbstract()) {
                        $models[] = [
                            'name' => $className,
                            'class' => $fullClass,
                        ];
                    }
                }
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    usort($models, fn ($a, $b) => $a['name'] <=> $b['name']);

    return $models;
}

function getRelationshipType(object $relation): string
{
    return match (true) {
        $relation instanceof HasOne => 'hasOne',
        $relation instanceof HasMany => 'hasMany',
        $relation instanceof BelongsTo => 'belongsTo',
        $relation instanceof BelongsToMany => 'belongsToMany',
        $relation instanceof HasOneThrough => 'hasOneThrough',
        $relation instanceof HasManyThrough => 'hasManyThrough',
        $relation instanceof MorphOne => 'morphOne',
        $relation instanceof MorphMany => 'morphMany',
        $relation instanceof MorphTo => 'morphTo',
        $relation instanceof MorphToMany => 'morphToMany',
        default => get_class($relation),
    };
}

function getModelSchema(string $modelName): array
{
    $modelClass = getModelClass($modelName);

    if (! $modelClass) {
        return ['error' => "Model '{$modelName}' not found"];
    }

    try {
        $model = new $modelClass();
        $table = $model->getTable();
        $reflection = new \ReflectionClass($modelClass);

        $columns = [];
        if (Schema::hasTable($table)) {
            $columnListing = Schema::getColumnListing($table);
            foreach ($columnListing as $column) {
                try {
                    $type = Schema::getColumnType($table, $column);
                    $columns[$column] = ['type' => $type];
                } catch (Throwable $e) {
                    $columns[$column] = ['type' => 'unknown'];
                }
            }
        }

        $casts = $model->getCasts();

        $fillable = $model->getFillable();
        $guarded = $model->getGuarded();
        $hidden = $model->getHidden();

        $relationships = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $modelClass) {
                continue;
            }
            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            try {
                $returnType = $method->getReturnType();
                if ($returnType) {
                    $typeName = $returnType->getName();
                    $relationTypes = [
                        HasOne::class, HasMany::class, BelongsTo::class,
                        BelongsToMany::class, HasOneThrough::class, HasManyThrough::class,
                        MorphOne::class, MorphMany::class, MorphTo::class, MorphToMany::class,
                    ];

                    if (in_array($typeName, $relationTypes)) {
                        $relation = $model->{$method->getName()}();
                        $relationships[$method->getName()] = [
                            'type' => getRelationshipType($relation),
                            'related' => get_class($relation->getRelated()),
                        ];
                    }
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        $traits = array_map(
            fn ($trait) => class_basename($trait),
            array_keys($reflection->getTraits())
        );

        return [
            'model' => $modelName,
            'class' => $modelClass,
            'table' => $table,
            'primaryKey' => $model->getKeyName(),
            'keyType' => $model->getKeyType(),
            'incrementing' => $model->getIncrementing(),
            'timestamps' => $model->usesTimestamps(),
            'columns' => $columns,
            'casts' => $casts,
            'fillable' => $fillable,
            'guarded' => $guarded,
            'hidden' => $hidden,
            'relationships' => $relationships,
            'traits' => $traits,
        ];
    } catch (Throwable $e) {
        return [
            'error' => $e->getMessage(),
            'model' => $modelName,
        ];
    }
}

function searchModels(string $query): array
{
    $allModels = listAllModels();
    $query = strtolower($query);

    return array_values(array_filter($allModels, function ($model) use ($query) {
        return str_contains(strtolower($model['name']), $query);
    }));
}

$command = $argv[1] ?? 'help';
$argument = $argv[2] ?? null;

$result = match ($command) {
    'list' => ['models' => listAllModels()],
    'schema' => $argument ? getModelSchema($argument) : ['error' => 'Model name required'],
    'search' => $argument ? ['models' => searchModels($argument)] : ['error' => 'Search query required'],
    default => [
        'commands' => [
            'list' => 'List all models',
            'schema <ModelName>' => 'Get schema for a specific model',
            'search <query>' => 'Search models by name',
        ],
    ],
};

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
