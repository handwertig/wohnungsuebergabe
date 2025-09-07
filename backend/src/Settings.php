<?php
declare(strict_types=1);

namespace App;

use PDO;
use App\SystemLogger;

/**
 * Settings-Verwaltung für Anwendungseinstellungen
 * Verbesserte Version mit Logging und Fehlerbehandlung
 */
final class Settings
{
    private static ?array $cache = null;
    private static bool $loggingEnabled = true;

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
                    CREATE TABLE IF NOT EXISTS settings (
                        `key` VARCHAR(255) PRIMARY KEY,
                        `value` TEXT,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                // Standard-Einstellungen einfügen
                $defaults = self::getDefaults();
                
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)");
                foreach ($defaults as $key => $value) {
                    try {
                        $stmt->execute([$key, $value]);
                    } catch (\PDOException $e) {
                        // Duplikate ignorieren
                    }
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
            error_log("Settings::loadSettings error: " . $e->getMessage());
            // Fallback bei Datenbankfehlern
            return self::getDefaults();
        }
    }

    /**
     * Standard-Einstellungen
     */
    private static function getDefaults(): array
    {
        return [
            'pdf_logo_path' => '',
            'custom_css' => '',
            'brand_primary' => '#222357',
            'brand_secondary' => '#e22278',
            'smtp_host' => 'mailpit',
            'smtp_port' => '1025',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_secure' => '',
            'smtp_from_email' => 'no-reply@example.com',
            'smtp_from_name' => 'Wohnungsübergabe',
            'ds_base_uri' => 'https://eu.docusign.net',
            'ds_account_id' => '',
            'ds_user_id' => '',
            'ds_client_id' => '',
            'ds_client_secret' => '',
        ];
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
    public static function set(string $key, string $value): bool
    {
        try {
            $pdo = Database::pdo();
            
            // Alten Wert für Logging holen
            $oldValue = self::get($key, '');
            
            // Einstellung speichern
            $stmt = $pdo->prepare("
                INSERT INTO settings (`key`, `value`, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                    `value` = VALUES(`value`), 
                    updated_at = NOW()
            ");
            
            $result = $stmt->execute([$key, $value]);
            
            if ($result) {
                // Cache invalidieren
                self::$cache = null;
                
                // Änderung loggen (nur wenn Wert sich geändert hat)
                if (self::$loggingEnabled && $oldValue !== $value) {
                    try {
                        SystemLogger::logSettingsChanged(
                            'settings',
                            [$key => ['old' => $oldValue, 'new' => $value]]
                        );
                    } catch (\Throwable $e) {
                        // Logging-Fehler ignorieren
                        error_log("Logging failed: " . $e->getMessage());
                    }
                }
                
                return true;
            }
            
            return false;
            
        } catch (\Throwable $e) {
            error_log("Settings::set error for key '$key': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Setzt mehrere Einstellungen auf einmal
     */
    public static function setMany(array $settings): bool
    {
        if (empty($settings)) {
            return true;
        }
        
        try {
            $pdo = Database::pdo();
            
            // Alte Werte für Logging sammeln
            $changes = [];
            foreach ($settings as $key => $newValue) {
                $oldValue = self::get($key, '');
                if ($oldValue !== $newValue) {
                    $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
                }
            }
            
            // Transaction starten
            $pdo->beginTransaction();
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (`key`, `value`, updated_at) 
                    VALUES (?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                        `value` = VALUES(`value`), 
                        updated_at = NOW()
                ");
                
                foreach ($settings as $key => $value) {
                    $stmt->execute([$key, (string)$value]);
                }
                
                $pdo->commit();
                
                // Cache invalidieren
                self::$cache = null;
                
                // Änderungen loggen
                if (self::$loggingEnabled && !empty($changes)) {
                    try {
                        SystemLogger::logSettingsChanged('multiple_settings', $changes);
                    } catch (\Throwable $e) {
                        // Logging-Fehler ignorieren
                        error_log("Logging failed: " . $e->getMessage());
                    }
                }
                
                return true;
                
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            
        } catch (\Throwable $e) {
            error_log("Settings::setMany error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Löscht eine Einstellung
     */
    public static function delete(string $key): bool
    {
        try {
            $pdo = Database::pdo();
            
            // Alten Wert für Logging holen
            $oldValue = self::get($key, '');
            
            $stmt = $pdo->prepare("DELETE FROM settings WHERE `key` = ?");
            $result = $stmt->execute([$key]);
            
            if ($result && $stmt->rowCount() > 0) {
                // Cache invalidieren
                self::$cache = null;
                
                // Löschung loggen
                if (self::$loggingEnabled && $oldValue !== '') {
                    try {
                        SystemLogger::logSettingsChanged(
                            'settings_deleted',
                            [$key => ['old' => $oldValue, 'new' => null]]
                        );
                    } catch (\Throwable $e) {
                        // Logging-Fehler ignorieren
                    }
                }
                
                return true;
            }
            
            return false;
            
        } catch (\Throwable $e) {
            error_log("Settings::delete error: " . $e->getMessage());
            return false;
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
     * Logging ein-/ausschalten (für Tests)
     */
    public static function setLogging(bool $enabled): void
    {
        self::$loggingEnabled = $enabled;
    }

    /**
     * Holt Mail-Konfiguration
     */
    public static function getMailConfig(): array
    {
        return [
            'host' => self::get('smtp_host', 'mailpit'),
            'port' => (int)self::get('smtp_port', '1025'),
            'user' => self::get('smtp_user'),
            'pass' => self::get('smtp_pass'),
            'secure' => self::get('smtp_secure'),
            'from' => self::get('smtp_from_email', 'no-reply@example.com'),
            'from_name' => self::get('smtp_from_name', 'Wohnungsübergabe'),
        ];
    }

    /**
     * Holt DocuSign-Konfiguration
     */
    public static function getDocusignConfig(): array
    {
        return [
            'base_uri' => self::get('ds_base_uri', 'https://eu.docusign.net'),
            'account_id' => self::get('ds_account_id'),
            'user_id' => self::get('ds_user_id'),
            'client_id' => self::get('ds_client_id'),
            'client_secret' => self::get('ds_client_secret'),
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

    /**
     * Debug-Methode: Zeigt alle Settings mit ihren aktuellen Werten
     */
    public static function debug(): array
    {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->query("
                SELECT `key`, `value`, updated_at 
                FROM settings 
                ORDER BY `key`
            ");
            
            $debug = [
                'cache_status' => self::$cache !== null ? 'loaded' : 'empty',
                'logging_enabled' => self::$loggingEnabled,
                'settings_count' => $stmt->rowCount(),
                'settings' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
            return $debug;
            
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
                'cache_status' => self::$cache !== null ? 'loaded' : 'empty',
                'logging_enabled' => self::$loggingEnabled
            ];
        }
    }
}
