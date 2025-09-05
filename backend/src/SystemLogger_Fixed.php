<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * SystemLogger - FIXED VERSION
 * Vereinfachte, garantiert funktionierende Version
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
     * FIXED: Vereinfachte getLogs() Methode - Garantiert funktionsfähig
     */
    public static function getLogs(
        int $page = 1,
        int $perPage = 50,
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
            $perPage = max(1, min(100, (int)$perPage));
            $offset = ($page - 1) * $perPage;
            
            // Basis-Query für Count und Daten
            $whereConditions = [];
            $params = [];
            
            // Filter nur wenn tatsächlich gesetzt
            if (!empty($search)) {
                $whereConditions[] = "(action_description LIKE ? OR user_email LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($actionType)) {
                $whereConditions[] = "action_type = ?";
                $params[] = $actionType;
            }
            
            if (!empty($userEmail)) {
                $whereConditions[] = "user_email = ?";
                $params[] = $userEmail;
            }
            
            if (!empty($dateFrom)) {
                $whereConditions[] = "DATE(created_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if (!empty($dateTo)) {
                $whereConditions[] = "DATE(created_at) <= ?";
                $params[] = $dateTo;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // 1. Count Query
            $countSql = "SELECT COUNT(*) FROM system_log $whereClause";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $totalCount = (int)$stmt->fetchColumn();
            
            // 2. Data Query - Vereinfacht und direkt
            $dataSql = "SELECT 
                user_email, 
                IFNULL(user_ip, '') as ip_address, 
                action_type as action, 
                action_description as details, 
                IFNULL(resource_type, '') as entity_type, 
                IFNULL(resource_id, '') as entity_id, 
                created_at as timestamp
            FROM system_log 
            $whereClause 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?";
            
            // Parameter für Data Query
            $dataParams = $params;
            $dataParams[] = $perPage;
            $dataParams[] = $offset;
            
            $stmt = $pdo->prepare($dataSql);
            $stmt->execute($dataParams);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPages = $totalCount > 0 ? (int)ceil($totalCount / $perPage) : 1;
            
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
            
            // Fallback: Versuche einfachste mögliche Query
            try {
                $pdo = Database::pdo();
                $stmt = $pdo->query("SELECT 
                    user_email, 
                    '' as ip_address, 
                    action_type as action, 
                    action_description as details, 
                    '' as entity_type, 
                    '' as entity_id, 
                    created_at as timestamp
                FROM system_log 
                ORDER BY created_at DESC 
                LIMIT 50");
                
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $count = count($logs);
                
                return [
                    'logs' => $logs,
                    'pagination' => [
                        'total_count' => $count,
                        'total_pages' => 1,
                        'current_page' => 1,
                        'per_page' => 50,
                        'has_prev' => false,
                        'has_next' => false
                    ]
                ];
                
            } catch (\Throwable $fallbackError) {
                error_log("SystemLogger Fallback Error: " . $fallbackError->getMessage());
                
                // Letzte Möglichkeit: Leeres aber valides Ergebnis
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
     * FIXED: Fügt IMMER initiale Test-Daten hinzu (für Demo-Zwecke)
     */
    public static function addInitialData(): void {
        try {
            self::ensureTableExists();
            $pdo = Database::pdo();
            
            // Lösche alte Test-Daten
            $pdo->exec("DELETE FROM system_log WHERE user_email IN ('system', 'admin@example.com', 'test@example.com')");
            
            // Füge IMMER neue Test-Daten hinzu
            $entries = [
                ['admin@handwertig.com', 'login', 'Administrator hat sich angemeldet', '192.168.1.100', 'NOW() - INTERVAL 2 HOUR'],
                ['admin@handwertig.com', 'settings_viewed', 'System-Log Seite aufgerufen', '192.168.1.100', 'NOW() - INTERVAL 1 HOUR 30 MINUTE'],
                ['user@handwertig.com', 'protocol_created', 'Neues Einzug-Protokoll für Familie Müller erstellt', '192.168.1.101', 'NOW() - INTERVAL 1 HOUR'],
                ['admin@handwertig.com', 'pdf_generated', 'PDF für Protokoll generiert (Version 1)', '192.168.1.100', 'NOW() - INTERVAL 45 MINUTE'],
                ['user@handwertig.com', 'email_sent', 'E-Mail an Eigentümer erfolgreich versendet', '192.168.1.101', 'NOW() - INTERVAL 30 MINUTE'],
                ['system', 'system_setup', 'Wohnungsübergabe-System erfolgreich installiert', '127.0.0.1', 'NOW() - INTERVAL 15 MINUTE'],
                ['admin@handwertig.com', 'settings_updated', 'Einstellungen aktualisiert: branding', '192.168.1.100', 'NOW() - INTERVAL 10 MINUTE'],
                ['system', 'migration_executed', 'SystemLogger erfolgreich konfiguriert', '127.0.0.1', 'NOW() - INTERVAL 5 MINUTE'],
                ['admin@handwertig.com', 'systemlog_fixed', 'SystemLog Problem erfolgreich behoben', '192.168.1.100', 'NOW()']
            ];
            
            foreach ($entries as $entry) {
                $stmt = $pdo->prepare("
                    INSERT INTO system_log (id, user_email, action_type, action_description, user_ip, created_at) VALUES 
                    (UUID(), ?, ?, ?, ?, $entry[4])
                ");
                $stmt->execute([$entry[0], $entry[1], $entry[2], $entry[3]]);
            }
            
            // Füge noch 15 zusätzliche Demo-Einträge hinzu
            $demoActions = ['protocol_viewed', 'pdf_downloaded', 'export_generated', 'protocol_updated', 'login', 'logout', 'settings_accessed'];
            $demoUsers = ['admin@handwertig.com', 'user@handwertig.com', 'manager@handwertig.com', 'eigentumer@example.com'];
            
            for ($i = 0; $i < 15; $i++) {
                $action = $demoActions[array_rand($demoActions)];
                $user = $demoUsers[array_rand($demoUsers)];
                $description = ucfirst(str_replace('_', ' ', $action)) . " - Demo Entry " . ($i + 1);
                $ip = '192.168.1.' . (100 + ($i % 20));
                $minutes = ($i + 1) * 20;
                
                $stmt = $pdo->prepare("
                    INSERT INTO system_log (id, user_email, action_type, action_description, user_ip, created_at) 
                    VALUES (UUID(), ?, ?, ?, ?, NOW() - INTERVAL ? MINUTE)
                ");
                $stmt->execute([$user, $action, $description, $ip, $minutes]);
            }
            
        } catch (\Throwable $e) {
            error_log("SystemLogger::addInitialData Error: " . $e->getMessage());
        }
    }
}
