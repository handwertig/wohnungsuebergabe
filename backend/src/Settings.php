<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * Settings-Verwaltung für Anwendungseinstellungen
 * Vollständige, syntaktisch korrekte Version
 */
final class Settings
{
    private static ?array $cache = null;

    /**
     * Lädt alle Settings aus der Datenbank
     */
    private static function loadSettings(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $pdo = Database::pdo();
            
            // Prüfen ob settings Tabelle existiert
            $tables = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchAll();
            if (empty($tables)) {
                // Settings-Tabelle erstellen falls nicht vorhanden
                $pdo->exec("
                    CREATE TABLE settings (
                        `key` VARCHAR(255) PRIMARY KEY,
                        `value` TEXT,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                // Standard-Einstellungen einfügen
                $defaults = [
                    'pdf_logo_path' => '',
                    'custom_css' => '',
                    'brand_primary' => '#222357',
                    'brand_secondary' => '#e22278',
                    'smtp_host' => '',
                    'smtp_port' => '587',
                    'smtp_user' => '',
                    'smtp_pass' => '',
                    'smtp_from' => '',
                    'smtp_from_name' => 'Wohnungsübergabe',
                    'docusign_account_id' => '',
                    'docusign_base_url' => 'https://demo.docusign.net/restapi',
                    'docusign_client_id' => '',
                    'docusign_client_secret' => '',
                    'docusign_redirect_uri' => '',
                ];
                
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)");
                foreach ($defaults as $key => $value) {
                    $stmt->execute([$key, $value]);
                }
            }
            
            $stmt = $pdo->query("SELECT `key`, `value` FROM settings");
            $settings = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['key']] = $row['value'];
            }
            
            self::$cache = $settings;
            return $settings;
            
        } catch (\Throwable $e) {
            // Fallback bei Datenbankfehlern
            return [
                'pdf_logo_path' => '',
                'custom_css' => '',
                'brand_primary' => '#222357',
                'brand_secondary' => '#e22278',
                'smtp_host' => '',
                'smtp_port' => '587',
                'smtp_user' => '',
                'smtp_pass' => '',
                'smtp_from' => '',
                'smtp_from_name' => 'Wohnungsübergabe',
                'docusign_account_id' => '',
                'docusign_base_url' => 'https://demo.docusign.net/restapi',
                'docusign_client_id' => '',
                'docusign_client_secret' => '',
                'docusign_redirect_uri' => '',
            ];
        }
    }

    /**
     * Holt eine einzelne Einstellung
     */
    public static function get(string $key, string $default = ''): string
    {
        $settings = self::loadSettings();
        return (string)($settings[$key] ?? $default);
    }

    /**
     * Holt alle Einstellungen
     */
    public static function getAll(): array
    {
        return self::loadSettings();
    }

    /**
     * Setzt eine Einstellung
     */
    public static function set(string $key, string $value): void
    {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare("
                INSERT INTO settings (`key`, `value`) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$key, $value]);
            
            // Cache invalidieren
            self::$cache = null;
            
        } catch (\Throwable $e) {
            // Fehler ignorieren (graceful degradation)
            error_log("Settings::set error: " . $e->getMessage());
        }
    }

    /**
     * Setzt mehrere Einstellungen auf einmal
     */
    public static function setMany(array $settings): void
    {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare("
                INSERT INTO settings (`key`, `value`) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = CURRENT_TIMESTAMP
            ");
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, (string)$value]);
            }
            
            // Cache invalidieren
            self::$cache = null;
            
        } catch (\Throwable $e) {
            // Fehler ignorieren (graceful degradation)
            error_log("Settings::setMany error: " . $e->getMessage());
        }
    }

    /**
     * Löscht eine Einstellung
     */
    public static function delete(string $key): void
    {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare("DELETE FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            
            // Cache invalidieren
            self::$cache = null;
            
        } catch (\Throwable $e) {
            error_log("Settings::delete error: " . $e->getMessage());
        }
    }

    /**
     * Prüft ob eine Einstellung existiert
     */
    public static function has(string $key): bool
    {
        $settings = self::loadSettings();
        return array_key_exists($key, $settings);
    }

    /**
     * Cache leeren (für Tests und Wartung)
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Holt Mail-Konfiguration
     */
    public static function getMailConfig(): array
    {
        return [
            'host' => self::get('smtp_host'),
            'port' => (int)self::get('smtp_port', '587'),
            'user' => self::get('smtp_user'),
            'pass' => self::get('smtp_pass'),
            'from' => self::get('smtp_from'),
            'from_name' => self::get('smtp_from_name', 'Wohnungsübergabe'),
        ];
    }

    /**
     * Holt DocuSign-Konfiguration
     */
    public static function getDocusignConfig(): array
    {
        return [
            'account_id' => self::get('docusign_account_id'),
            'base_url' => self::get('docusign_base_url', 'https://demo.docusign.net/restapi'),
            'client_id' => self::get('docusign_client_id'),
            'client_secret' => self::get('docusign_client_secret'),
            'redirect_uri' => self::get('docusign_redirect_uri'),
        ];
    }

    /**
     * Holt Branding-Konfiguration
     */
    public static function getBrandingConfig(): array
    {
        return [
            'logo_path' => self::get('pdf_logo_path'),
            'custom_css' => self::get('custom_css'),
            'primary_color' => self::get('brand_primary', '#222357'),
            'secondary_color' => self::get('brand_secondary', '#e22278'),
        ];
    }
}
