<?php
declare(strict_types=1);

namespace App;

final class Validation
{
    /**
     * Prüft, ob die erforderlichen Felder vorhanden und nicht leer sind
     * @param array $data Die zu prüfenden Daten
     * @param array $requiredFields Array mit den Namen der Pflichtfelder
     * @return array Array mit Fehlermeldungen (leer wenn alles ok)
     */
    public static function required(array $data, array $requiredFields): array
    {
        $errors = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                $errors[$field] = ucfirst($field) . ' ist ein Pflichtfeld.';
            }
        }
        return $errors;
    }

    /**
     * @param string $type  einzug|auszug|zwischen
     * @param array  $post  Mischdaten (POST + ggf. Draftteile)
     * @param string $phase 'step1' | 'full'
     */
    public static function protocolPostErrors(string $type, array $post, string $phase = 'full'): array
    {
        $errors = [];

        // STEP 1: nur Adresse
        if ($phase === 'step1') {
            $addr = (array)($post['address'] ?? []);
            foreach (['city','street','house_no'] as $f) {
                if (trim((string)($addr[$f] ?? '')) === '') {
                    $errors[] = "Adresse: Feld '$f' ist erforderlich.";
                }
            }
            return $errors;
        }

        // FULL (Schritt 4 / Editor): nur Datenschutz als Pflicht bei Einzug/Auszug
        if ($type === 'einzug' || $type === 'auszug') {
            if (empty(($post['meta']['consents']['privacy'] ?? false))) {
                $errors[] = 'Bitte Datenschutzerklärung akzeptieren.';
            }
        }

        return $errors;
    }

    // IBAN-Prüfer lassen wir stehen (derzeit nicht blocking verwendet)
    public static function ibanIsValid(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{6,28}$/', $iban)) return false;
        $re = substr($iban, 4).substr($iban, 0, 4);
        $num = '';
        for ($i=0,$l=strlen($re); $i<$l; $i++) {
            $c = $re[$i];
            $num .= ($c >= 'A' && $c <= 'Z') ? (string)(ord($c) - 55) : $c;
        }
        $rem = 0;
        foreach (str_split($num, 9) as $chunk) {
            $rem = (int)(($rem.$chunk) % 97);
        }
        return $rem === 1;
    }
}
