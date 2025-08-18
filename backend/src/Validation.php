<?php
declare(strict_types=1);

namespace App;

final class Validation
{
    /** @param array<string, mixed> $data */
    public static function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $f) {
            if (!isset($data[$f]) || trim((string)$data[$f]) === '') {
                $errors[$f] = 'Pflichtfeld';
            }
        }
        return $errors;
    }

    public static function email(?string $value): ?string
    {
        if ($value === null || $value === '') return null;
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Ungültige E‑Mail';
    }
}
