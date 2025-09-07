<?php
declare(strict_types=1);

namespace App;

use App\Database;
use App\Auth;

/**
 * System Logger
 * 
 * Zentrale Klasse für das Logging aller Systemaktivitäten
 * Schreibt Einträge in die system_log Tabelle
 */
final class SystemLogger
{
    /**
     * Loggt eine Benutzeraktion
     */
    public static function log(
        string $actionType,
        string $description,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $additionalData = null
    ): void {
        try {
            $pdo = Database::pdo();
            
            // Erst prüfen ob Tabelle existiert
            try {
                $pdo->query("SELECT 1 FROM system_log LIMIT 1");
            } catch (\PDOException $e) {
                // Tabelle existiert nicht - einfach zurück
                return;
            }
            
            $user = Auth::user();
            
            // Benutzer-Informationen sammeln
            $userEmail = $user ? (string)$user['email'] : 'system';
            $userIp = self::getUserIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
            $requestUrl = $_SERVER['REQUEST_URI'] ?? null;
            
            // JSON-Daten für zusätzliche Informationen
            $jsonData = $additionalData ? json_encode($additionalData, JSON_UNESCAPED_UNICODE) : null;
            
            // Einfacherer Insert-Versuch zuerst
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO system_log (
                        id, user_email, user_ip, action_type, action_description,
                        resource_type, resource_id, additional_data,
                        request_method, request_url, user_agent, created_at
                    ) VALUES (
                        UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                    )
                ');
                
                $stmt->execute([
                    $userEmail,
                    $userIp,
                    $actionType,
                    $description,
                    $resourceType,
                    $resourceId,
                    $jsonData,
                    $requestMethod,
                    $requestUrl,
                    $userAgent
                ]);
            } catch (\PDOException $e) {
                // Fallback: Nur die wichtigsten Felder
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO system_log (user_email, action_type, action_description, user_ip, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([$userEmail, $actionType, $description, $userIp]);
                } catch (\PDOException $e2) {
                    // Letzter Fallback: Nur die absolut nötigen Felder
                    try {
                        $stmt = $pdo->prepare('
                            INSERT INTO system_log (action_type, action_description) 
                            VALUES (?, ?)
                        ');
                        $stmt->execute([$actionType, $description]);
                    } catch (\PDOException $e3) {
                        // Komplett fehlgeschlagen - nur Error-Log
                        error_log("SystemLogger: Alle Insert-Versuche fehlgeschlagen: " . $e3->getMessage());
                    }
                }
            }
            
        } catch (\Throwable $e) {
            // Logging-Fehler sollten die Anwendung nicht zum Absturz bringen
            error_log("SystemLogger Error: " . $e->getMessage());
        }
    }
    
    /**
     * Protokoll-spezifische Logging-Methoden
     */
    
    public static function logProtocolViewed(string $protocolId, array $protocolData = []): void
    {
        self::log(
            'protocol_viewed',
            'Protokoll angezeigt: ' . ($protocolData['tenant_name'] ?? 'Unbekannt'),
            'protocol',
            $protocolId,
            [
                'protocol_type' => $protocolData['type'] ?? null,
                'property_address' => ($protocolData['city'] ?? '') . ' ' . ($protocolData['street'] ?? ''),
                'unit' => $protocolData['unit'] ?? null
            ]
        );
    }
    
    public static function logProtocolCreated(string $protocolId, array $protocolData = []): void
    {
        self::log(
            'protocol_created',
            'Neues Protokoll erstellt: ' . ($protocolData['type'] ?? 'Unbekannt') . ' - ' . ($protocolData['tenant_name'] ?? 'Unbekannt'),
            'protocol',
            $protocolId,
            [
                'protocol_type' => $protocolData['type'] ?? null,
                'property_address' => ($protocolData['city'] ?? '') . ' ' . ($protocolData['street'] ?? ''),
                'unit' => $protocolData['unit'] ?? null,
                'tenant_name' => $protocolData['tenant_name'] ?? null
            ]
        );
    }
    
    public static function logProtocolUpdated(string $protocolId, array $protocolData = [], array $changes = []): void
    {
        $changeDescription = !empty($changes) ? ' (Änderungen: ' . implode(', ', array_keys($changes)) . ')' : '';
        
        self::log(
            'protocol_updated',
            'Protokoll bearbeitet: ' . ($protocolData['tenant_name'] ?? 'Unbekannt') . $changeDescription,
            'protocol',
            $protocolId,
            [
                'protocol_type' => $protocolData['type'] ?? null,
                'property_address' => ($protocolData['city'] ?? '') . ' ' . ($protocolData['street'] ?? ''),
                'unit' => $protocolData['unit'] ?? null,
                'changes' => $changes
            ]
        );
    }
    
    public static function logProtocolDeleted(string $protocolId, array $protocolData = []): void
    {
        self::log(
            'protocol_deleted',
            'Protokoll gelöscht: ' . ($protocolData['tenant_name'] ?? 'Unbekannt'),
            'protocol',
            $protocolId,
            [
                'protocol_type' => $protocolData['type'] ?? null,
                'property_address' => ($protocolData['city'] ?? '') . ' ' . ($protocolData['street'] ?? ''),
                'unit' => $protocolData['unit'] ?? null
            ]
        );
    }
    
    public static function logPdfGenerated(string $protocolId, array $protocolData = [], string $pdfType = 'protocol'): void
    {
        self::log(
            'pdf_generated',
            'PDF erstellt: ' . ($protocolData['tenant_name'] ?? 'Unbekannt') . ' (' . $pdfType . ')',
            'protocol',
            $protocolId,
            [
                'pdf_type' => $pdfType,
                'protocol_type' => $protocolData['type'] ?? null,
                'property_address' => ($protocolData['city'] ?? '') . ' ' . ($protocolData['street'] ?? ''),
                'unit' => $protocolData['unit'] ?? null,
                'file_size' => $_SERVER['CONTENT_LENGTH'] ?? null
            ]
        );
    }
    
    public static function logPdfDownloaded(string $protocolId, array $protocolData = [], string $pdfType = 'protocol'): void
    {
        self::log(
            'pdf_downloaded',
            'PDF heruntergeladen: ' . ($protocolData['tenant_name'] ?? 'Unbekannt') . ' (' . $pdfType . ')',
            'protocol',
            $protocolId,
            [
                'pdf_type' => $pdfType,
                'protocol_type' => $protocolData['type'] ?? null,
                'property_address' => ($protocolData['city'] ?? '') . ' ' . ($protocolData['street'] ?? ''),
                'unit' => $protocolData['unit'] ?? null,
                'download_method' => 'direct' // oder 'email', 'attachment', etc.
            ]
        );
    }
    
    public static function logEmailSent(string $protocolId, array $protocolData = [], string $recipient = '', string $emailType = 'protocol'): void
    {
        self::log(
            'email_sent',
            'E-Mail versendet: ' . $emailType . ' an ' . $recipient . ' für Protokoll ' . ($protocolData['tenant_name'] ?? 'Unbekannt'),
            'protocol',
            $protocolId,
            [
                'email_type' => $emailType,
                'recipient' => $recipient,
                'protocol_type' => $protocolData['type'] ?? null,
                'property_address' => ($protocolData['city'] ?? '') . ' ' . ($protocolData['street'] ?? ''),
                'unit' => $protocolData['unit'] ?? null
            ]
        );
    }
    
    /**
     * Allgemeine System-Aktionen
     */
    
    public static function logLogin(string $userEmail): void
    {
        self::log(
            'user_login',
            'Benutzer angemeldet: ' . $userEmail,
            'user',
            null,
            ['login_method' => 'password']
        );
    }
    
    public static function logLogout(string $userEmail): void
    {
        self::log(
            'user_logout',
            'Benutzer abgemeldet: ' . $userEmail,
            'user',
            null
        );
    }
    
    public static function logSettingsChanged(string $settingSection, array $changes = []): void
    {
        self::log(
            'settings_updated',
            'Einstellungen geändert: ' . $settingSection,
            'settings',
            $settingSection,
            ['changes' => $changes]
        );
    }
    
    public static function logDataExport(string $exportType, int $recordCount = 0): void
    {
        self::log(
            'data_exported',
            'Daten exportiert: ' . $exportType . ' (' . $recordCount . ' Datensätze)',
            'export',
            null,
            [
                'export_type' => $exportType,
                'record_count' => $recordCount,
                'format' => 'csv' // oder 'pdf', 'xlsx', etc.
            ]
        );
    }
    
    /**
     * Hilfsmethoden
     */
    
    private static function getUserIp(): ?string
    {
        // IP-Adresse ermitteln (auch hinter Proxies)
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Loggt Fehler und Exceptions
     */
    public static function logError(string $message, ?\Throwable $exception = null, string $context = ''): void
    {
        $description = 'Fehler' . ($context ? ' in ' . $context : '') . ': ' . $message;
        
        $additionalData = [];
        if ($exception) {
            $additionalData = [
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
                'stack_trace' => $exception->getTraceAsString()
            ];
        }
        
        self::log(
            'system_error',
            $description,
            'error',
            null,
            $additionalData
        );
    }
    
    /**
     * Loggt Performance-relevante Aktionen
     */
    public static function logPerformance(string $action, float $executionTime, array $metrics = []): void
    {
        self::log(
            'performance_metric',
            $action . ' ausgeführt in ' . round($executionTime, 3) . 's',
            'performance',
            null,
            array_merge($metrics, [
                'execution_time' => $executionTime,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true)
            ])
        );
    }
}
