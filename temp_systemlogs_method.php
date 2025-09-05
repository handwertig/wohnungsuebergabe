    /**
     * System-Logs anzeigen (vereinfachte Version ohne komplexe Queries)
     */
    public function systemLogs(): void {
        Auth::requireAuth();
        
        $logs = [];
        $totalCount = 0;
        
        try {
            $pdo = Database::pdo();
            
            // Erst prüfen ob die Tabelle überhaupt existiert
            $stmt = $pdo->query("SHOW TABLES LIKE 'system_log'");
            if (!$stmt->fetch()) {
                throw new \Exception("Tabelle system_log existiert nicht");
            }
            
            // Einfachste Anzahl-Abfrage
            $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
            $totalCount = (int)$stmt->fetchColumn();
            
            // Einfachste Daten-Abfrage mit minimalen Feldern
            $stmt = $pdo->query("
                SELECT 
                    COALESCE(user_email, 'system') as user_email,
                    COALESCE(user_ip, '127.0.0.1') as ip_address,
                    COALESCE(action_type, 'unknown') as action,
                    COALESCE(action_description, 'No description') as details,
                    created_at as timestamp
                FROM system_log 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
        } catch (\Throwable $e) {
            // Bei jedem Fehler: Einfachen Fallback-Eintrag anzeigen
            $logs = [[
                "user_email" => "SYSTEM",
                "ip_address" => "127.0.0.1",
                "action" => "error",
                "details" => "Fehler beim Laden der System-Logs: " . $e->getMessage(),
                "timestamp" => date("Y-m-d H:i:s")
            ]];
            $totalCount = 1;
        }
        
        $body = $this->tabs("systemlogs");
        
        // Einfacher Header
        $body .= '<div class="card mb-3">';
        $body .= '<div class="card-body">';
        $body .= '<h3 class="mb-1">System-Logs</h3>';
        $body .= '<p class="text-muted mb-0">Gesamt: ' . $totalCount . ' Einträge</p>';
        $body .= '</div></div>';
        
        // Einfache Tabelle
        if (empty($logs)) {
            $body .= '<div class="alert alert-info">Keine Log-Einträge gefunden.</div>';
        } else {
            $body .= '<div class="card">';
            $body .= '<div class="table-responsive">';
            $body .= '<table class="table table-striped">';
            $body .= '<thead class="table-dark">';
            $body .= '<tr>';
            $body .= '<th>Zeit</th>';
            $body .= '<th>Benutzer</th>';
            $body .= '<th>Aktion</th>';
            $body .= '<th>Details</th>';
            $body .= '<th>IP</th>';
            $body .= '</tr>';
            $body .= '</thead>';
            $body .= '<tbody>';
            
            foreach ($logs as $log) {
                $timestamp = date("H:i:s", strtotime($log["timestamp"]));
                $badgeClass = $this->getActionBadgeClass($log["action"]);
                
                $body .= '<tr>';
                $body .= '<td>' . $this->esc($timestamp) . '</td>';
                $body .= '<td>' . $this->esc($log["user_email"]) . '</td>';
                $body .= '<td><span class="badge ' . $badgeClass . '">' . $this->esc($log["action"]) . '</span></td>';
                $body .= '<td>' . $this->esc(substr($log["details"], 0, 60)) . (strlen($log["details"]) > 60 ? '...' : '') . '</td>';
                $body .= '<td>' . $this->esc($log["ip_address"]) . '</td>';
                $body .= '</tr>';
            }
            
            $body .= '</tbody>';
            $body .= '</table>';
            $body .= '</div></div>';
        }
        
        View::render("Einstellungen – System-Log", $body);
    }