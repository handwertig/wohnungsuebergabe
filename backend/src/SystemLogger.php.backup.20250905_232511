<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * SystemLogger - Umfassendes Logging aller Systemaktivitäten
 * 
 * Protokolliert alle wichtigen Aktionen im System für Compliance und Audit-Zwecke
 */
final class SystemLogger
{
    /**
     * Loggt eine Benutzeraktion im System
     */
    public static function log(
        string $actionType, 
        string $description, 
        ?string $resourceType = null, 
        ?string $resourceId = null, 
        ?array $additionalData = null
    ): void {
        try {
            // Stelle sicher, dass die Tabelle existiert
            self::ensureTableExists();
            
            $pdo = Database::pdo();
            
            // Benutzerinformationen ermitteln
            $user = Auth::user();
            $userEmail = $user['email'] ?? 'system';
            $userIp = self::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
            $requestUrl = $_SERVER['REQUEST_URI'] ?? null;
            
            $stmt = $pdo->prepare("
                INSERT INTO system_log (
                    id, user_email, user_ip, action_type, action_description, 
                    resource_type, resource_id, additional_data, 
                    request_method, request_url, user_agent, created_at
                ) VALUES (
                    UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");
            
            $stmt->execute([
                $userEmail,
                $userIp,
                $actionType,
                $description,
                $resourceType,
                $resourceId,
                $additionalData ? json_encode($additionalData, JSON_UNESCAPED_UNICODE) : null,
                $requestMethod,
                $requestUrl,
                $userAgent
            ]);
            
        } catch (\Throwable $e) {
            // Log-Fehler sollten das System nicht zum Absturz bringen
            error_log("SystemLogger Fehler: " . $e->getMessage());
        }
    }
    
    /**
     * Spezielle Methoden für häufige Aktionen
     */
    
    public static function logLogin(string $email): void
    {
        self::log('login', "Benutzer '$email' hat sich angemeldet");
    }
    
    public static function logLogout(string $email): void
    {
        self::log('logout', "Benutzer '$email' hat sich abgemeldet");
    }
    
    public static function logProtocolCreated(string $protocolId, string $tenantName, string $type): void
    {
        self::log(
            'protocol_created', 
            "Neues $type-Protokoll für '$tenantName' erstellt",
            'protocol',
            $protocolId,
            ['tenant_name' => $tenantName, 'type' => $type]
        );
    }
    
    public static function logProtocolUpdated(string $protocolId, string $tenantName, int $newVersion): void
    {
        self::log(
            'protocol_updated', 
            "Protokoll für '$tenantName' aktualisiert (Version $newVersion)",
            'protocol',
            $protocolId,
            ['tenant_name' => $tenantName, 'version' => $newVersion]
        );
    }
    
    public static function logProtocolViewed(string $protocolId, string $tenantName): void
    {
        self::log(
            'protocol_viewed', 
            "Protokoll für '$tenantName' angesehen",
            'protocol',
            $protocolId,
            ['tenant_name' => $tenantName]
        );
    }
    
    public static function logPdfGenerated(string $protocolId, int $version): void
    {
        self::log(
            'pdf_generated', 
            "PDF für Protokoll generiert (Version $version)",
            'protocol',
            $protocolId,
            ['version' => $version]
        );
    }
    
    public static function logPdfViewed(string $protocolId, int $version): void
    {
        self::log(
            'pdf_viewed', 
            "PDF für Protokoll angesehen (Version $version)",
            'protocol',
            $protocolId,
            ['version' => $version]
        );
    }
    
    public static function logEmailSent(string $protocolId, string $recipientType, string $toEmail, bool $success): void
    {
        $status = $success ? 'erfolgreich versendet' : 'Versand fehlgeschlagen';
        self::log(
            $success ? 'email_sent' : 'email_failed', 
            "E-Mail an $recipientType ($toEmail) $status",
            'protocol',
            $protocolId,
            ['recipient_type' => $recipientType, 'to_email' => $toEmail, 'success' => $success]
        );
    }
    
    public static function logDraftStarted(string $draftId, string $type): void
    {
        self::log(
            'protocol_draft_started', 
            "Neuer $type-Protokoll-Entwurf gestartet",
            'draft',
            $draftId,
            ['type' => $type]
        );
    }
    
    public static function logDraftSaved(string $draftId, int $step): void
    {
        self::log(
            'protocol_draft_saved', 
            "Protokoll-Entwurf gespeichert (Schritt $step)",
            'draft',
            $draftId,
            ['step' => $step]
        );
    }
    
    public static function logDraftFinished(string $draftId, string $protocolId): void
    {
        self::log(
            'protocol_draft_finished', 
            "Protokoll-Entwurf abgeschlossen und als Protokoll gespeichert",
            'draft',
            $draftId,
            ['protocol_id' => $protocolId]
        );
    }
    
    public static function logSettingsUpdated(string $section): void
    {
        self::log(
            'settings_updated', 
            "Einstellungen aktualisiert: $section"
        );
    }
    
    public static function logExportGenerated(string $type): void
    {
        self::log(
            'export_generated', 
            "Export generiert: $type"
        );
    }
    
    public static function logResourceCreated(string $resourceType, string $resourceId, string $name): void
    {
        self::log(
            $resourceType . '_created', 
            ucfirst($resourceType) . " '$name' erstellt",
            $resourceType,
            $resourceId,
            ['name' => $name]
        );
    }
    
    public static function logResourceUpdated(string $resourceType, string $resourceId, string $name): void
    {
        self::log(
            $resourceType . '_updated', 
            ucfirst($resourceType) . " '$name' aktualisiert",
            $resourceType,
            $resourceId,
            ['name' => $name]
        );
    }
    
    public static function logResourceDeleted(string $resourceType, string $resourceId, string $name): void
    {
        self::log(
            $resourceType . '_deleted', 
            ucfirst($resourceType) . " '$name' gelöscht",
            $resourceType,
            $resourceId,
            ['name' => $name]
        );
    }
    
    public static function logResourceViewed(string $resourceType, string $resourceId, string $name): void
    {
        self::log(
            $resourceType . '_viewed', 
            ucfirst($resourceType) . " '$name' angesehen",
            $resourceType,
            $resourceId,
            ['name' => $name]
        );
    }
    
    /**
     * Ermittelt die Client-IP-Adresse
     */
    private static function getClientIp(): ?string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
    
    /**
     * Stellt sicher, dass die system_log Tabelle existiert und die richtige Struktur hat
     */
    private static function ensureTableExists(): void {
        try {
            $pdo = Database::pdo();
            $pdo->exec("CREATE TABLE IF NOT EXISTS system_log (
                id CHAR(36) PRIMARY KEY,
                user_email VARCHAR(255) NOT NULL DEFAULT 'system',
                user_ip VARCHAR(45) NULL,
                action_type VARCHAR(100) NOT NULL,
                action_description TEXT NOT NULL,
                resource_type VARCHAR(50) NULL,
                resource_id CHAR(36) NULL,
                additional_data JSON NULL,
                request_method VARCHAR(10) NULL,
                request_url VARCHAR(500) NULL,
                user_agent TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_email (user_email),
                INDEX idx_action_type (action_type),
                INDEX idx_resource (resource_type, resource_id),
                INDEX idx_created_at (created_at),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            error_log("SystemLogger: Could not ensure table exists: " . $e->getMessage());
        }
    }

    /**
     * Lädt Log-Einträge mit Filter und Pagination (MariaDB/MySQL kompatibel)
     */
    public static function getLogs(
        int $page = 1,
        int $perPage = 100,
        ?string $search = null,
        ?string $actionType = null,
        ?string $userEmail = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        try {
            // Stelle sicher, dass die Tabelle existiert
            self::ensureTableExists();
            
            $pdo = Database::pdo();
            
            // Sichere Parameter-Validierung
            $page = max(1, (int)$page);
            $perPage = max(1, min(1000, (int)$perPage)); // Max 1000 Einträge
            $offset = ($page - 1) * $perPage;
            
            $where = [];
            $params = [];
            
            if ($search && trim($search) !== '') {
                $where[] = "(action_description LIKE ? OR user_email LIKE ? OR COALESCE(resource_id, '') LIKE ?)";
                $searchTerm = '%' . trim($search) . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if ($actionType && trim($actionType) !== '') {
                $where[] = "action_type = ?";
                $params[] = trim($actionType);
            }
            
            if ($userEmail && trim($userEmail) !== '') {
                $where[] = "user_email = ?";
                $params[] = trim($userEmail);
            }
            
            if ($dateFrom && trim($dateFrom) !== '') {
                $where[] = "DATE(created_at) >= ?";
                $params[] = trim($dateFrom);
            }
            
            if ($dateTo && trim($dateTo) !== '') {
                $where[] = "DATE(created_at) <= ?";
                $params[] = trim($dateTo);
            }
            
            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            
            // Gesamtanzahl ermitteln
            $countSql = "SELECT COUNT(*) FROM system_log $whereClause";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $totalCount = (int)$stmt->fetchColumn();
            
            // Paginierte Daten laden - LIMIT und OFFSET direkt einbauen (sicher durch Validierung)
            $dataSql = "SELECT 
                user_email, 
                COALESCE(user_ip, '') as ip_address, 
                action_type as action, 
                action_description as details, 
                COALESCE(resource_type, '') as entity_type, 
                COALESCE(resource_id, '') as entity_id, 
                created_at as timestamp
            FROM system_log 
            $whereClause 
            ORDER BY created_at DESC 
            LIMIT $perPage OFFSET $offset";
            
            $stmt = $pdo->prepare($dataSql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPages = (int)ceil($totalCount / $perPage);
            
            return [
                'logs' => $logs,
                'pagination' => [
                    'total_count' => $totalCount,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'has_prev' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ];
            
        } catch (\Throwable $e) {
            error_log("SystemLogger::getLogs Error: " . $e->getMessage());
            // Fallback: leeres Ergebnis zurückgeben
            return [
                'logs' => [],
                'pagination' => [
                    'total_count' => 0,
                    'total_pages' => 0,
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'has_prev' => false,
                    'has_next' => false
                ]
            ];
        }
    }
    
    /**
     * Ermittelt verfügbare Action Types für Filter
     */
    public static function getAvailableActions(): array
    {
        try {
            self::ensureTableExists();
            $pdo = Database::pdo();
            $stmt = $pdo->query("
                SELECT DISTINCT action_type 
                FROM system_log 
                ORDER BY action_type
            ");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            error_log("SystemLogger::getAvailableActions Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Ermittelt verfügbare Benutzer für Filter
     */
    public static function getAvailableUsers(): array
    {
        try {
            self::ensureTableExists();
            $pdo = Database::pdo();
            $stmt = $pdo->query("
                SELECT DISTINCT user_email 
                FROM system_log 
                WHERE user_email IS NOT NULL AND user_email != ''
                ORDER BY user_email
            ");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            error_log("SystemLogger::getAvailableUsers Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fügt initiale Test-Daten hinzu falls die Tabelle leer ist
     */
    public static function addInitialData(): void {
        try {
            self::ensureTableExists();
            $pdo = Database::pdo();
            
            // Prüfe ob bereits Daten vorhanden sind
            $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
            $count = (int)$stmt->fetchColumn();
            
            if ($count < 5) { // Füge mehr Test-Daten hinzu
                // Lösche existierende Test-Daten
                $pdo->exec("DELETE FROM system_log WHERE user_email = 'system'");
                
                // Füge umfassende Test-Einträge hinzu
                $stmt = $pdo->prepare("
                    INSERT INTO system_log (id, user_email, action_type, action_description, resource_type, resource_id, user_ip, created_at) VALUES 
                    (UUID(), 'admin@example.com', 'login', 'Administrator hat sich angemeldet', NULL, NULL, '192.168.1.100', NOW() - INTERVAL 2 HOUR),
                    (UUID(), 'admin@example.com', 'settings_viewed', 'System-Log Seite aufgerufen', NULL, NULL, '192.168.1.100', NOW() - INTERVAL 1 HOUR),
                    (UUID(), 'user@example.com', 'protocol_created', 'Neues Einzug-Protokoll für Mustermann erstellt', 'protocol', UUID(), '192.168.1.101', NOW() - INTERVAL 45 MINUTE),
                    (UUID(), 'admin@example.com', 'pdf_generated', 'PDF für Protokoll generiert (Version 1)', 'protocol', UUID(), '192.168.1.100', NOW() - INTERVAL 30 MINUTE),
                    (UUID(), 'user@example.com', 'email_sent', 'E-Mail an Mieter (max@mustermann.de) erfolgreich versendet', 'protocol', UUID(), '192.168.1.101', NOW() - INTERVAL 15 MINUTE),
                    (UUID(), 'system', 'system_setup', 'AdminKit Theme erfolgreich implementiert', NULL, NULL, '127.0.0.1', NOW() - INTERVAL 10 MINUTE),
                    (UUID(), 'admin@example.com', 'settings_updated', 'Einstellungen aktualisiert: branding', NULL, NULL, '192.168.1.100', NOW() - INTERVAL 5 MINUTE),
                    (UUID(), 'system', 'migration_executed', 'SystemLogger erfolgreich konfiguriert', NULL, NULL, '127.0.0.1', NOW())
                ");
                $stmt->execute();
                
                // Füge noch mehr Test-Einträge für bessere Demo hinzu
                for ($i = 0; $i < 15; $i++) {
                    $actions = ['protocol_viewed', 'pdf_viewed', 'export_generated', 'protocol_updated', 'login', 'logout'];
                    $users = ['admin@example.com', 'user@example.com', 'manager@example.com'];
                    $entities = ['protocol', 'draft', 'user', 'object'];
                    
                    $action = $actions[array_rand($actions)];
                    $user = $users[array_rand($users)];
                    $entity = $entities[array_rand($entities)];
                    $description = ucfirst(str_replace('_', ' ', $action)) . ' - Test entry #' . ($i + 1);
                    $ip = '192.168.1.' . (100 + ($i % 10));
                    $minutes = $i * 10 + 20;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO system_log (id, user_email, action_type, action_description, resource_type, resource_id, user_ip, created_at) 
                        VALUES (UUID(), ?, ?, ?, ?, UUID(), ?, NOW() - INTERVAL ? MINUTE)
                    ");
                    $stmt->execute([$user, $action, $description, $entity, $ip, $minutes]);
                }
            }
        } catch (\Throwable $e) {
            error_log("SystemLogger::addInitialData Error: " . $e->getMessage());
        }
    }
}
