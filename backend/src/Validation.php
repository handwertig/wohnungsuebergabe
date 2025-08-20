<?php
declare(strict_types=1);

namespace App;

final class Validation
{
    /** Pflichtfelder je Protokoll-Art, basierend auf Pflichtenheft */
    public static function protocolPostErrors(string $type, array $post): array
    {
        $errors = [];

        // Kopf/Adresse immer sinnvoll
        $addr = (array)($post['address'] ?? []);
        $reqAddr = ['city','street','house_no'];
        foreach ($reqAddr as $f) {
            if (trim((string)($addr[$f] ?? '')) === '') {
                $errors[] = "Adresse: Feld '$f' ist erforderlich.";
            }
        }

        // Typ-spezifisch
        $type = in_array($type, ['einzug','auszug','zwischen'], true) ? $type : 'einzug';

        // Einzug: Mietername, Schlüssel sinnvoll, Zählerstände
        if ($type === 'einzug') {
            if (trim((string)($post['tenant_name'] ?? '')) === '') {
                $errors[] = 'Mietername ist erforderlich (Einzug).';
            }
            // einfache Zählerprüfung: mindestens eine Gruppe mit Stand
            if (!self::hasAnyMeterValue((array)($post['meters'] ?? []))) {
                $errors[] = 'Mindestens ein Zählerstand muss angegeben werden (Einzug).';
            }
        }

        // Auszug: Zählerstände + Bank + neue Meldeadresse
        if ($type === 'auszug') {
            if (!self::hasAnyMeterValue((array)($post['meters'] ?? []))) {
                $errors[] = 'Zählerstände sind erforderlich (Auszug).';
            }
            $bank = (array)($post['meta']['bank'] ?? []);
            foreach (['bank','iban','holder'] as $f) {
                if (trim((string)($bank[$f] ?? '')) === '') {
                    $errors[] = "Bankdaten: '$f' erforderlich (Auszug).";
                }
            }
            $newAddr = (array)($post['meta']['tenant_new_addr'] ?? []);
            foreach (['street','house_no','postal_code','city'] as $f) {
                if (trim((string)($newAddr[$f] ?? '')) === '') {
                    $errors[] = "Neue Meldeadresse: '$f' erforderlich (Auszug).";
                }
            }
        }

        // Zwischen: keine harten Pflichten, aber wenn Meter angegeben, dann Zahlenformat checken
        $meters = (array)($post['meters'] ?? []);
        foreach ($meters as $k => $row) {
            $val = (string)($row['val'] ?? '');
            if ($val !== '' && !preg_match('/^\d+(?:[.,]\d{1,3})?$/', $val)) {
                $errors[] = "Zähler '$k': Stand hat ungültiges Format.";
            }
        }

        return $errors;
    }

    private static function hasAnyMeterValue(array $meters): bool
    {
        foreach ($meters as $row) {
            if (trim((string)($row['val'] ?? '')) !== '') return true;
        }
        return false;
    }

    /** Basishilfen (weiter nutzbar) */
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
