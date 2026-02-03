<?php

declare(strict_types=1);

$projectRoot = getenv('LARAVEL_PATH') ?: dirname(__DIR__, 2);

if (! file_exists($projectRoot . '/vendor/autoload.php')) {
    echo json_encode(['error' => "Laravel project not found at: {$projectRoot}"]);
    exit(1);
}

require $projectRoot . '/vendor/autoload.php';

$app = require_once $projectRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function listAllEnums(): array
{
    global $projectRoot;
    $enumsPath = $projectRoot . '/app/Enums';
    $enums = [];

    if (! is_dir($enumsPath)) {
        return ['error' => 'No app/Enums directory found'];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($enumsPath)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relativePath = str_replace($enumsPath . '/', '', $file->getPathname());
            $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $fullClass = "App\\Enums\\{$className}";

            try {
                if (enum_exists($fullClass)) {
                    $reflection = new \ReflectionEnum($fullClass);
                    $backingType = $reflection->getBackingType();
                    
                    $enums[] = [
                        'name' => $className,
                        'class' => $fullClass,
                        'backingType' => $backingType ? $backingType->getName() : null,
                        'caseCount' => count($reflection->getCases()),
                    ];
                }
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    usort($enums, fn ($a, $b) => $a['name'] <=> $b['name']);

    return ['enums' => $enums, 'total' => count($enums)];
}

function getEnumDetails(string $enumName): array
{
    $enumClass = getEnumClass($enumName);

    if (! $enumClass) {
        return ['error' => "Enum '{$enumName}' not found"];
    }

    try {
        $reflection = new \ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType();

        $cases = [];
        foreach ($reflection->getCases() as $case) {
            $caseData = [
                'name' => $case->getName(),
            ];

            if ($backingType) {
                $caseData['value'] = $case->getBackingValue();
            }

            $attributes = [];
            foreach ($case->getAttributes() as $attr) {
                $attrInstance = $attr->newInstance();
                $attrData = ['name' => $attr->getName()];
                
                foreach ((new \ReflectionClass($attrInstance))->getProperties() as $prop) {
                    $prop->setAccessible(true);
                    $attrData[$prop->getName()] = $prop->getValue($attrInstance);
                }
                
                $attributes[] = $attrData;
            }
            
            if (! empty($attributes)) {
                $caseData['attributes'] = $attributes;
            }

            $cases[] = $caseData;
        }

        $methods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $enumClass) {
                continue;
            }
            
            $params = [];
            foreach ($method->getParameters() as $param) {
                $paramData = ['name' => $param->getName()];
                if ($param->hasType()) {
                    $paramType = $param->getType();
                    if ($paramType instanceof \ReflectionNamedType) {
                        $paramData['type'] = $paramType->getName();
                    } elseif ($paramType instanceof \ReflectionUnionType) {
                        $paramData['type'] = implode('|', array_map(fn($t) => $t->getName(), $paramType->getTypes()));
                    } else {
                        $paramData['type'] = (string) $paramType;
                    }
                }
                if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                    $paramData['default'] = $param->getDefaultValue();
                }
                $params[] = $paramData;
            }

            $returnType = $method->getReturnType();
            $returnTypeName = null;
            if ($returnType) {
                if ($returnType instanceof \ReflectionNamedType) {
                    $returnTypeName = $returnType->getName();
                } elseif ($returnType instanceof \ReflectionUnionType) {
                    $returnTypeName = implode('|', array_map(fn($t) => $t->getName(), $returnType->getTypes()));
                } else {
                    $returnTypeName = (string) $returnType;
                }
            }
            
            $methods[] = [
                'name' => $method->getName(),
                'static' => $method->isStatic(),
                'parameters' => $params,
                'returnType' => $returnTypeName,
            ];
        }

        $traits = array_map(
            fn ($trait) => class_basename($trait),
            array_keys($reflection->getTraits())
        );

        $interfaces = array_map(
            fn ($interface) => class_basename($interface),
            array_keys($reflection->getInterfaces())
        );

        return [
            'enum' => $enumName,
            'class' => $enumClass,
            'backingType' => $backingType ? $backingType->getName() : null,
            'cases' => $cases,
            'methods' => $methods,
            'traits' => $traits,
            'interfaces' => $interfaces,
        ];
    } catch (Throwable $e) {
        return [
            'error' => $e->getMessage(),
            'enum' => $enumName,
        ];
    }
}

function getEnumClass(string $enumName): ?string
{
    $possiblePaths = [
        "App\\Enums\\{$enumName}",
        "App\\Enums\\" . str_replace('/', '\\', $enumName),
    ];

    foreach ($possiblePaths as $class) {
        if (enum_exists($class)) {
            return $class;
        }
    }

    return null;
}

function searchEnums(string $query): array
{
    $result = listAllEnums();
    
    if (isset($result['error'])) {
        return $result;
    }

    $query = strtolower($query);

    $filtered = array_values(array_filter($result['enums'], function ($enum) use ($query) {
        return str_contains(strtolower($enum['name']), $query);
    }));

    return ['enums' => $filtered, 'total' => count($filtered)];
}

function getEnumValues(string $enumName): array
{
    $enumClass = getEnumClass($enumName);

    if (! $enumClass) {
        return ['error' => "Enum '{$enumName}' not found"];
    }

    try {
        $reflection = new \ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType();

        $values = [];
        foreach ($reflection->getCases() as $case) {
            if ($backingType) {
                $values[$case->getName()] = $case->getBackingValue();
            } else {
                $values[] = $case->getName();
            }
        }

        return [
            'enum' => $enumName,
            'class' => $enumClass,
            'backingType' => $backingType ? $backingType->getName() : null,
            'values' => $values,
        ];
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}

$command = $argv[1] ?? 'help';
$argument = $argv[2] ?? null;

$result = match ($command) {
    'list' => listAllEnums(),
    'details' => $argument ? getEnumDetails($argument) : ['error' => 'Enum name required'],
    'values' => $argument ? getEnumValues($argument) : ['error' => 'Enum name required'],
    'search' => $argument ? searchEnums($argument) : ['error' => 'Search query required'],
    default => [
        'commands' => [
            'list' => 'List all enums',
            'details <EnumName>' => 'Get full details for a specific enum',
            'values <EnumName>' => 'Get just the case names and values',
            'search <query>' => 'Search enums by name',
        ],
    ],
};

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
