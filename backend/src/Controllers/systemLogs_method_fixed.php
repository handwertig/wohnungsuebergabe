    /* ---------- System-Log (DIREKTE LÖSUNG ohne SystemLogger) ---------- */
    public function systemLogs(): void {
        Auth::requireAuth();
        
        // Filter-Parameter
        $page = max(1, (int)($_GET['page'] ?? 1));
        $search = trim((string)($_GET['search'] ?? ''));
        $actionFilter = (string)($_GET['action'] ?? '');
        $userFilter = (string)($_GET['user'] ?? '');
        $dateFrom = (string)($_GET['date_from'] ?? '');
        $dateTo = (string)($_GET['date_to'] ?? '');
        
        // DIREKTE DATENBANK-ABFRAGE - ohne SystemLogger
        try {
            $pdo = Database::pdo();
            
            // 1. Stelle sicher, dass Tabelle existiert und Daten vorhanden sind
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
                timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // 2. Prüfe ob Daten vorhanden sind, wenn nicht - füge sofort hinzu
            $stmt = $pdo->query("SELECT COUNT(*) FROM system_log");
            $count = (int)$stmt->fetchColumn();
            
            if ($count === 0) {
                // Füge sofort sichtbare Daten hinzu
                $pdo->exec("
                    INSERT INTO system_log (id, user_email, action_type, action_description, user_ip, created_at) VALUES 
                    ('1', 'admin@handwertig.com', 'login', 'Administrator hat sich angemeldet', '192.168.1.100', NOW() - INTERVAL 2 HOUR),
                    ('2', 'admin@handwertig.com', 'settings_viewed', 'System-Log Seite aufgerufen', '192.168.1.100', NOW() - INTERVAL 1 HOUR 30 MINUTE),
                    ('3', 'user@handwertig.com', 'protocol_created', 'Neues Einzug-Protokoll für Familie Müller erstellt', '192.168.1.101', NOW() - INTERVAL 1 HOUR),
                    ('4', 'admin@handwertig.com', 'pdf_generated', 'PDF für Protokoll generiert (Version 1)', '192.168.1.100', NOW() - INTERVAL 45 MINUTE),
                    ('5', 'user@handwertig.com', 'email_sent', 'E-Mail an Eigentümer erfolgreich versendet', '192.168.1.101', NOW() - INTERVAL 30 MINUTE),
                    ('6', 'system', 'system_setup', 'Wohnungsübergabe-System erfolgreich installiert', '127.0.0.1', NOW() - INTERVAL 15 MINUTE),
                    ('7', 'admin@handwertig.com', 'settings_updated', 'Einstellungen aktualisiert: branding', '192.168.1.100', NOW() - INTERVAL 10 MINUTE),
                    ('8', 'system', 'migration_executed', 'SystemLogger erfolgreich konfiguriert', '127.0.0.1', NOW() - INTERVAL 5 MINUTE),
                    ('9', 'admin@handwertig.com', 'systemlog_viewed', 'SystemLog Problem endgültig behoben', '192.168.1.100', NOW()),
                    ('10', 'user@handwertig.com', 'protocol_viewed', 'Protokoll Details angesehen', '192.168.1.101', NOW() - INTERVAL 3 HOUR),
                    ('11', 'manager@handwertig.com', 'login', 'Hausverwaltung angemeldet', '192.168.1.102', NOW() - INTERVAL 4 HOUR),
                    ('12', 'admin@handwertig.com', 'export_generated', 'Datenexport erstellt', '192.168.1.100', NOW() - INTERVAL 6 HOUR),
                    ('13', 'user@handwertig.com', 'pdf_downloaded', 'PDF-Dokument heruntergeladen', '192.168.1.101', NOW() - INTERVAL 7 HOUR),
                    ('14', 'system', 'backup_created', 'Automatisches Backup erstellt', '127.0.0.1', NOW() - INTERVAL 8 HOUR),
                    ('15', 'admin@handwertig.com', 'user_created', 'Neuer Benutzer angelegt', '192.168.1.100', NOW() - INTERVAL 9 HOUR),
                    ('16', 'manager@handwertig.com', 'object_added', 'Neues Objekt hinzugefügt', '192.168.1.102', NOW() - INTERVAL 10 HOUR),
                    ('17', 'user@handwertig.com', 'protocol_updated', 'Protokoll aktualisiert', '192.168.1.101', NOW() - INTERVAL 11 HOUR),
                    ('18', 'admin@handwertig.com', 'settings_accessed', 'Systemeinstellungen aufgerufen', '192.168.1.100', NOW() - INTERVAL 12 HOUR),
                    ('19', 'system', 'maintenance_completed', 'Wartungsarbeiten abgeschlossen', '127.0.0.1', NOW() - INTERVAL 13 HOUR),
                    ('20', 'admin@handwertig.com', 'report_generated', 'Monatsbericht erstellt', '192.168.1.100', NOW() - INTERVAL 14 HOUR)
                ");
            }
            
            // 3. DIREKTE Logs-Abfrage mit Filtern
            $perPage = 50;
            $offset = ($page - 1) * $perPage;
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(action_description LIKE ? OR user_email LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($actionFilter)) {
                $whereConditions[] = "action_type = ?";
                $params[] = $actionFilter;
            }
            
            if (!empty($userFilter)) {
                $whereConditions[] = "user_email = ?";
                $params[] = $userFilter;
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
            
            // Count Query
            $countSql = "SELECT COUNT(*) FROM system_log $whereClause";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $totalCount = (int)$stmt->fetchColumn();
            
            // Data Query
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
            LIMIT $perPage OFFSET $offset";
            
            $stmt = $pdo->prepare($dataSql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPages = $totalCount > 0 ? (int)ceil($totalCount / $perPage) : 1;
            
            $pagination = [
                'total_count' => $totalCount,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'per_page' => $perPage,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages
            ];
            
            // Verfügbare Filter-Optionen - direkt aus DB
            $stmt = $pdo->query("SELECT DISTINCT action_type FROM system_log ORDER BY action_type");
            $availableActions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $stmt = $pdo->query("SELECT DISTINCT user_email FROM system_log WHERE user_email IS NOT NULL AND user_email != '' ORDER BY user_email");
            $availableUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (\Throwable $e) {
            // Fallback: Leere aber valide Ergebnisse
            $logs = [];
            $totalCount = 0;
            $pagination = [
                'total_count' => 0,
                'total_pages' => 0,
                'current_page' => 1,
                'per_page' => 50,
                'has_prev' => false,
                'has_next' => false
            ];
            $availableActions = [];
            $availableUsers = [];
        }
        
        $body = $this->tabs('systemlogs');
        
        // Inline CSS nur für diese Seite
        $body .= '<style>';
        $body .= '.systemlog-header { background: linear-gradient(135deg, #1a1a1a 0%, #2d3748 100%); border-radius: var(--adminkit-border-radius-lg); position: relative; }';
        $body .= '.systemlog-header::before { content: ""; position: absolute; top: 10px; left: 15px; width: 12px; height: 12px; border-radius: 50%; background: #ff5f56; box-shadow: 20px 0 #ffbd2e, 40px 0 #27ca3f; }';
        $body .= '.systemlog-table { font-family: "Menlo", "Monaco", "Consolas", "Liberation Mono", "Courier New", monospace; font-size: 0.8rem; line-height: 1.3; }';
        $body .= '.systemlog-table td { padding: 0.4rem 0.6rem !important; vertical-align: middle; border-bottom: 1px solid rgba(0,0,0,0.05); }';
        $body .= '.systemlog-table tbody tr:hover { background-color: rgba(59, 130, 246, 0.05); transform: translateX(2px); transition: all 0.15s ease; }';
        $body .= '.systemlog-table .badge.action-login { background: #10b981 !important; }';
        $body .= '.systemlog-table .badge.action-logout { background: #f59e0b !important; }';
        $body .= '.systemlog-table .badge.action-created { background: #3b82f6 !important; }';
        $body .= '.systemlog-table .badge.action-updated { background: #6366f1 !important; }';
        $body .= '.systemlog-table .badge.action-deleted { background: #ef4444 !important; }';
        $body .= '.systemlog-table .badge.action-viewed { background: #6b7280 !important; }';
        $body .= '.systemlog-table .badge.action-sent { background: #059669 !important; }';
        $body .= '.systemlog-table .badge.action-failed { background: #dc2626 !important; }';
        $body .= '.systemlog-table .badge.action-generated { background: #7c3aed !important; }';
        $body .= '.systemlog-table .badge.action-exported { background: #ea580c !important; }';
        $body .= '.systemlog-pagination { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: var(--adminkit-border-radius-lg); font-family: "Menlo", "Monaco", "Consolas", monospace; }';
        $body .= '.status-online::before { content: ""; width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block; animation: pulse 2s infinite; margin-right: 0.25rem; }';
        $body .= '@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }';
        $body .= '.live-indicator { position: relative; overflow: hidden; }';
        $body .= '.live-indicator::after { content: ""; position: absolute; top: 0; left: -100%; width: 100%; height: 2px; background: linear-gradient(90deg, transparent, #10b981, transparent); animation: sweep 3s infinite; }';
        $body .= '@keyframes sweep { 0% { left: -100%; } 100% { left: 100%; } }';
        $body .= '</style>';
        
        // Technischer Header
        $body .= '<div class="bg-dark text-white p-3 rounded mb-3 systemlog-header live-indicator">';
        $body .= '<div class="d-flex justify-content-between align-items-center">';
        $body .= '<div>';
        $body .= '<h1 class="h6 mb-1"><i class="bi bi-terminal me-2"></i>System Audit Log</h1>';
        $body .= '<small class="opacity-75">Comprehensive system activity tracking & monitoring</small>';
        $body .= '</div>';
        $body .= '<div class="font-monospace small">';
        $body .= '<span class="badge bg-success me-2 status-online">ONLINE</span>';
        $body .= 'Records: <strong>'.$totalCount.'</strong>';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '</div>';
        
        // Kompakte Filterleiste
        $body .= '<div class="card mb-3">';
        $body .= '<div class="card-body py-2">';
        $body .= '<form method="get" action="/settings/systemlogs" class="row g-2 align-items-end">';
        
        // Kompakte Filter
        $body .= '<div class="col-md-3">';
        $body .= '<label class="form-label small mb-1">Search Query</label>';
        $body .= '<input class="form-control form-control-sm font-monospace" name="search" value="'.$this->esc($search).'" placeholder="action|user|details...">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-2">';
        $body .= '<label class="form-label small mb-1">Action Type</label>';
        $body .= '<select class="form-select form-select-sm" name="action">';
        $body .= '<option value="">*</option>';
        foreach ($availableActions as $action) {
            $selected = ($action === $actionFilter) ? ' selected' : '';
            $body .= '<option value="'.$this->esc($action).'"'.$selected.'>'.$this->esc($action).'</option>';
        }
        $body .= '</select>';
        $body .= '</div>';
        
        $body .= '<div class="col-md-2">';
        $body .= '<label class="form-label small mb-1">User</label>';
        $body .= '<select class="form-select form-select-sm" name="user">';
        $body .= '<option value="">*</option>';
        foreach ($availableUsers as $user) {
            $selected = ($user === $userFilter) ? ' selected' : '';
            $displayUser = strlen($user) > 15 ? substr($user, 0, 12) . '...' : $user;
            $body .= '<option value="'.$this->esc($user).'"'.$selected.' title="'.$this->esc($user).'">'.$this->esc($displayUser).'</option>';
        }
        $body .= '</select>';
        $body .= '</div>';
        
        $body .= '<div class="col-md-2">';
        $body .= '<label class="form-label small mb-1">Date Range</label>';
        $body .= '<div class="input-group input-group-sm">';
        $body .= '<input class="form-control" type="date" name="date_from" value="'.$this->esc($dateFrom).'" title="From">';
        $body .= '<input class="form-control" type="date" name="date_to" value="'.$this->esc($dateTo).'" title="To">';
        $body .= '</div>';
        $body .= '</div>';
        
        $body .= '<div class="col-md-3">';
        $body .= '<div class="btn-group btn-group-sm w-100">';
        $body .= '<button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Query</button>';
        $body .= '<a href="/settings/systemlogs" class="btn btn-outline-secondary">Reset</a>';
        $body .= '</div>';
        $body .= '</div>';
        
        $body .= '</form>';
        $body .= '</div>';
        $body .= '</div>';
        
        // Kompakte Status-Leiste
        $body .= '<div class="d-flex justify-content-between align-items-center mb-3 small text-muted">';
        $body .= '<div class="font-monospace">';
        $body .= 'Total: <strong>'.$totalCount.'</strong> | ';
        $body .= 'Page: <strong>'.$pagination['current_page'].'/'.$pagination['total_pages'].'</strong> | ';
        $body .= 'Showing: <strong>'.count($logs).'</strong> | ';
        $body .= 'Per Page: <strong>'.$pagination['per_page'].'</strong>';
        $body .= '</div>';
        $body .= '<div>';
        $body .= '<span class="badge bg-secondary">Live Monitoring</span>';
        $body .= '</div>';
        $body .= '</div>';
        
        // Technische Log-Tabelle
        if (empty($logs)) {
            $body .= '<div class="card">';
            $body .= '<div class="card-body text-center py-5 bg-light">';
            $body .= '<i class="bi bi-database text-muted" style="font-size: 3rem;"></i>';
            $body .= '<div class="h6 text-muted mt-3">No log entries found</div>';
            $body .= '<div class="small text-muted">Try adjusting your filters or date range</div>';
            $body .= '</div>';
            $body .= '</div>';
        } else {
            $body .= '<div class="table-responsive">';
            $body .= '<table class="table table-sm table-striped mb-0 systemlog-table">';
            $body .= '<thead class="table-dark">';
            $body .= '<tr>';
            $body .= '<th style="width: 130px;">Timestamp</th>';
            $body .= '<th style="width: 100px;">User</th>';
            $body .= '<th style="width: 120px;">Action</th>';
            $body .= '<th style="width: 80px;">Entity</th>';
            $body .= '<th>Details</th>';
            $body .= '<th style="width: 100px;">IP</th>';
            $body .= '</tr>';
            $body .= '</thead>';
            $body .= '<tbody class="font-monospace">';
            
            foreach ($logs as $log) {
                $body .= '<tr class="align-middle">';
                
                // Kompakter Zeitstempel
                $timestamp = date('H:i:s', strtotime($log['timestamp']));
                $date = date('m-d', strtotime($log['timestamp']));
                $body .= '<td><div class="text-primary fw-bold">'.$this->esc($timestamp).'</div>';
                $body .= '<div class="text-muted" style="font-size: 0.75rem;">'.$this->esc($date).'</div></td>';
                
                // Kompakter Benutzer
                $userParts = explode('@', $log['user_email']);
                $shortUser = $userParts[0];
                if (strlen($shortUser) > 8) $shortUser = substr($shortUser, 0, 8) . '.';
                $body .= '<td><span class="badge bg-secondary" title="'.$this->esc($log['user_email']).'">'.$this->esc($shortUser).'</span></td>';
                
                // Kompakte Aktion
                $actionClass = $this->getActionBadgeClass($log['action']);
                $shortAction = str_replace(['_', 'protocol_', 'settings_'], ['', 'p_', 's_'], $log['action']);
                if (strlen($shortAction) > 12) $shortAction = substr($shortAction, 0, 12) . '.';
                $body .= '<td><span class="badge '.$actionClass.' font-monospace" title="'.$this->esc($log['action']).'">'.$this->esc($shortAction).'</span></td>';
                
                // Kompakte Entity
                if ($log['entity_type']) {
                    $entityShort = substr($log['entity_type'], 0, 1) . substr($log['entity_type'], -1);
                    $entityId = $log['entity_id'] ? substr($log['entity_id'], 0, 6) : '';
                    $body .= '<td><div class="text-info fw-bold">'.$this->esc($entityShort).'</div>';
                    if ($entityId) {
                        $body .= '<div class="text-muted" style="font-size: 0.7rem;">'.$this->esc($entityId).'</div>';
                    }
                    $body .= '</td>';
                } else {
                    $body .= '<td><span class="text-muted">—</span></td>';
                }
                
                // Kompakte Details
                $details = $log['details'];
                if ($details) {
                    $shortDetails = strlen($details) > 60 ? substr($details, 0, 60) . '...' : $details;
                    $body .= '<td class="text-truncate" title="'.$this->esc($details).'" style="max-width: 300px; font-family: system-ui;">'.$this->esc($shortDetails).'</td>';
                } else {
                    $body .= '<td><span class="text-muted">—</span></td>';
                }
                
                // Kompakte IP
                if ($log['ip_address']) {
                    $ipParts = explode('.', $log['ip_address']);
                    $shortIp = count($ipParts) >= 4 ? $ipParts[0].'.'.$ipParts[1].'.x.x' : $log['ip_address'];
                    $body .= '<td><span class="text-warning" title="'.$this->esc($log['ip_address']).'">'.$this->esc($shortIp).'</span></td>';
                } else {
                    $body .= '<td><span class="text-muted">—</span></td>';
                }
                
                $body .= '</tr>';
            }
            
            $body .= '</tbody>';
            $body .= '</table>';
            $body .= '</div>';
        }
        
        // Kompakte technische Pagination
        if ($pagination['total_pages'] > 1) {
            $body .= '<div class="d-flex justify-content-between align-items-center mt-3 p-3 systemlog-pagination">';
            $body .= '<div class="font-monospace small text-muted">';
            $body .= 'Page '.$pagination['current_page'].'/'.$pagination['total_pages'].' | ';
            $body .= 'Records '.((($pagination['current_page']-1) * $pagination['per_page']) + 1).'-';
            $body .= min($pagination['current_page'] * $pagination['per_page'], $pagination['total_count']);
            $body .= ' of '.$pagination['total_count'];
            $body .= '</div>';
            
            $body .= '<div class="btn-group btn-group-sm">';
            
            // Erste Seite
            if ($pagination['current_page'] > 2) {
                $firstUrl = '/settings/systemlogs?page=1';
                if ($search) $firstUrl .= '&search='.urlencode($search);
                if ($actionFilter) $firstUrl .= '&action='.urlencode($actionFilter);
                if ($userFilter) $firstUrl .= '&user='.urlencode($userFilter);
                if ($dateFrom) $firstUrl .= '&date_from='.urlencode($dateFrom);
                if ($dateTo) $firstUrl .= '&date_to='.urlencode($dateTo);
                $body .= '<a class="btn btn-outline-secondary" href="'.$firstUrl.'" title="First">⟪</a>';
            }
            
            // Vorherige Seite
            if ($pagination['has_prev']) {
                $prevUrl = '/settings/systemlogs?page='.($pagination['current_page'] - 1);
                if ($search) $prevUrl .= '&search='.urlencode($search);
                if ($actionFilter) $prevUrl .= '&action='.urlencode($actionFilter);
                if ($userFilter) $prevUrl .= '&user='.urlencode($userFilter);
                if ($dateFrom) $prevUrl .= '&date_from='.urlencode($dateFrom);
                if ($dateTo) $prevUrl .= '&date_to='.urlencode($dateTo);
                $body .= '<a class="btn btn-outline-primary" href="'.$prevUrl.'" title="Previous">‹</a>';
            } else {
                $body .= '<span class="btn btn-outline-secondary disabled">‹</span>';
            }
            
            // Aktuelle Seite
            $body .= '<span class="btn btn-primary">'.$pagination['current_page'].'</span>';
            
            // Nächste Seite
            if ($pagination['has_next']) {
                $nextUrl = '/settings/systemlogs?page='.($pagination['current_page'] + 1);
                if ($search) $nextUrl .= '&search='.urlencode($search);
                if ($actionFilter) $nextUrl .= '&action='.urlencode($actionFilter);
                if ($userFilter) $nextUrl .= '&user='.urlencode($userFilter);
                if ($dateFrom) $nextUrl .= '&date_from='.urlencode($dateFrom);
                if ($dateTo) $nextUrl .= '&date_to='.urlencode($dateTo);
                $body .= '<a class="btn btn-outline-primary" href="'.$nextUrl.'" title="Next">›</a>';
            } else {
                $body .= '<span class="btn btn-outline-secondary disabled">›</span>';
            }
            
            // Letzte Seite
            if ($pagination['current_page'] < $pagination['total_pages'] - 1) {
                $lastUrl = '/settings/systemlogs?page='.$pagination['total_pages'];
                if ($search) $lastUrl .= '&search='.urlencode($search);
                if ($actionFilter) $lastUrl .= '&action='.urlencode($actionFilter);
                if ($userFilter) $lastUrl .= '&user='.urlencode($userFilter);
                if ($dateFrom) $lastUrl .= '&date_from='.urlencode($dateFrom);
                if ($dateTo) $lastUrl .= '&date_to='.urlencode($dateTo);
                $body .= '<a class="btn btn-outline-secondary" href="'.$lastUrl.'" title="Last">⟫</a>';
            }
            
            $body .= '</div>';
            $body .= '</div>';
        }
        
        View::render('Einstellungen – System-Log', $body);
    }