<?php
/**
 * MINIMALE SYSTEMLOG-METHODE
 * Einfachste mögliche Implementierung für sofortigen Erfolg
 */

// Neue systemLogs() Methode für SettingsController
function systemLogs_minimal() {
    echo '/* ---------- MINIMALE SYSTEM-LOG METHODE ---------- */
    public function systemLogs(): void {
        Auth::requireAuth();
        
        // Alle Parameter erstmal ignorieren - nur Basis-Funktionalität
        $logs = [];
        $totalCount = 0;
        
        try {
            $pdo = Database::pdo();
            
            // Einfachste mögliche Abfrage
            $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
            $totalCount = (int)$stmt->fetchColumn();
            
            // Einfachste Daten-Abfrage
            $stmt = $pdo->query("SELECT 
                user_email, 
                IFNULL(user_ip, \"\") as ip_address, 
                action_type as action, 
                action_description as details, 
                \"\" as entity_type, 
                \"\" as entity_id, 
                created_at as timestamp
            FROM system_log 
            ORDER BY created_at DESC 
            LIMIT 20");
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (\Throwable $e) {
            // Bei Fehler: Zeige Fehler-Information
            $logs = [[
                "user_email" => "ERROR",
                "ip_address" => "127.0.0.1",
                "action" => "error",
                "details" => "Database Error: " . $e->getMessage(),
                "entity_type" => "",
                "entity_id" => "",
                "timestamp" => date("Y-m-d H:i:s")
            ]];
            $totalCount = 1;
        }
        
        $body = $this->tabs("systemlogs");
        
        // Minimaler Header
        $body .= "<div class=\"card mb-3\">";
        $body .= "<div class=\"card-body\">";
        $body .= "<h3>System-Log <span class=\"badge bg-primary\">Records: $totalCount</span></h3>";
        $body .= "</div>";
        $body .= "</div>";
        
        // Einfachste Tabelle
        if (empty($logs)) {
            $body .= "<div class=\"alert alert-warning\">Keine Log-Einträge gefunden.</div>";
        } else {
            $body .= "<div class=\"table-responsive\">";
            $body .= "<table class=\"table table-striped\">";
            $body .= "<thead class=\"table-dark\">";
            $body .= "<tr>";
            $body .= "<th>Zeit</th>";
            $body .= "<th>Benutzer</th>";
            $body .= "<th>Aktion</th>";
            $body .= "<th>Details</th>";
            $body .= "<th>IP</th>";
            $body .= "</tr>";
            $body .= "</thead>";
            $body .= "<tbody>";
            
            foreach ($logs as $log) {
                $body .= "<tr>";
                $body .= "<td>" . $this->esc(date("H:i:s", strtotime($log["timestamp"]))) . "</td>";
                $body .= "<td>" . $this->esc($log["user_email"]) . "</td>";
                $body .= "<td><span class=\"badge bg-secondary\">" . $this->esc($log["action"]) . "</span></td>";
                $body .= "<td>" . $this->esc(substr($log["details"], 0, 50)) . "...</td>";
                $body .= "<td>" . $this->esc($log["ip_address"]) . "</td>";
                $body .= "</tr>";
            }
            
            $body .= "</tbody>";
            $body .= "</table>";
            $body .= "</div>";
        }
        
        View::render("Einstellungen – System-Log", $body);
    }';
}

echo "MINIMALE SYSTEMLOG-METHODE GENERIERT\n";
echo "====================================\n\n";
systemLogs_minimal();
echo "\n\n";
echo "📋 Diese Methode können Sie in den SettingsController kopieren.\n";
echo "Sie ist bewusst minimal gehalten und sollte garantiert funktionieren.\n";
