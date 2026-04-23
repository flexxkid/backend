<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PersonName
{
    public static function normalizePayload(array $payload, bool $required = false): array
    {
        $hasFullName = array_key_exists('FullName', $payload);
        $hasFirstName = array_key_exists('FirstName', $payload);
        $hasLastName = array_key_exists('LastName', $payload);

        if (! $hasFullName && ! $hasFirstName && ! $hasLastName) {
            return $payload;
        }

        $firstName = self::clean($payload['FirstName'] ?? null);
        $lastName = self::clean($payload['LastName'] ?? null);

        if ($firstName !== null || $lastName !== null) {
            if ($firstName === null || $lastName === null) {
                throw ValidationException::withMessages([
                    'FullName' => ['Provide both FirstName and LastName.'],
                ]);
            }

            $payload['FullName'] = "{$firstName} {$lastName}";

            unset($payload['FirstName'], $payload['LastName']);

            return $payload;
        }

        $fullName = self::clean($payload['FullName'] ?? null);

        if ($fullName === null) {
            if ($required) {
                throw ValidationException::withMessages([
                    'FullName' => ['The FullName field is required.'],
                ]);
            }

            return $payload;
        }

        [$parsedFirstName, $parsedLastName] = self::split($fullName);

        if ($parsedFirstName === null || $parsedLastName === null) {
            throw ValidationException::withMessages([
                'FullName' => ['FullName must include at least a first name and last name.'],
            ]);
        }

        $payload['FullName'] = "{$parsedFirstName} {$parsedLastName}";

        return $payload;
    }

    private static function split(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) < 2) {
            return [null, null];
        }

        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts);

        return [$firstName, $lastName];
    }

    private static function clean(null|string|int|float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        return $cleaned === '' ? null : $cleaned;
    }
}
