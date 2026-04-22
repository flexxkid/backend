<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class HrmsEntityRegistry
{
    public static function all(): array
    {
        return config('hrms_entities', []);
    }

    public static function get(string $entity): array
    {
        $definition = self::all()[$entity] ?? null;

        if (! $definition) {
            throw new InvalidArgumentException("Unknown HRMS entity [{$entity}].");
        }

        return $definition;
    }

    public static function model(string $entity): Model
    {
        $modelClass = self::get($entity)['model'];

        return new $modelClass();
    }

    public static function rules(string $entity, ?int $id = null): array
    {
        $rules = self::get($entity)['rules'] ?? [];

        return collect($rules)->mapWithKeys(function (array $fieldRules, string $field) use ($id) {
            return [
                $field => array_map(function ($rule) use ($id) {
                    return is_string($rule) ? str_replace('{id}', (string) ($id ?? 'NULL'), $rule) : $rule;
                }, $fieldRules),
            ];
        })->all();
    }

    public static function includes(string $entity): array
    {
        return self::get($entity)['includes'] ?? [];
    }

    public static function readonly(string $entity): bool
    {
        return (bool) Arr::get(self::get($entity), 'readonly', false);
    }
}
