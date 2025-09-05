<?php

namespace App\Controllers;

use App\Auth;
use App\UserAuth;
use App\Settings;
use App\View;
use App\Database;
use PDO;

class SettingsController {
    
    private function esc(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function tabs(string $active = ''): string {
        $tabs = [
            'general'   => ['Stammdaten', '/settings', 'bi-database'],
            'mail'      => ['E-Mail', '/settings/mail', 'bi-envelope'],
            'docusign'  => ['DocuSign', '/settings/docusign', 'bi-file-earmark-text'],
            'texts'     => ['Textbausteine', '/settings/texts', 'bi-card-text'],
            'users'     => ['Benutzer', '/settings/users', 'bi-people'],
            'branding'  => ['Design', '/settings/branding', 'bi-palette'],
            'systemlogs' => ['System-Log', '/settings/systemlogs', 'bi-list-ul']
        ];

        $html = '<div class="mb-4">';
        $html .= '<ul class="nav nav-pills nav-fill bg-light rounded-3 p-1">';
        foreach ($tabs as $key => [$label, $url, $icon]) {
            $isActive = ($key === $active);
            $class = $isActive ? 'nav-link active text-white' : 'nav-link text-muted';
            $html .= '<li class="nav-item">';
            $html .= '<a class="'.$class.'" href="'.$url.'">';
            $html .= '<i class="'.$icon.' me-2"></i>'.$label;
            $html .= '</a></li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        return $html;
    }

    /* ---------- Stammdaten (Links zu Objekten, Eigentümer, Hausverwaltungen + Eigenes Profil) ---------- */
    public function general(): void {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            return;
        }
        
        $body = $this->tabs('general');
        
        // Sauberer AdminKit-Header ohne große Icons
        $body .= '<div class="mb-4">';
        $body .= '<h2 class="h4 mb-1">Stammdaten</h2>';
        $body .= '<p class="text-muted">Verwalten Sie Ihre Immobilien, Kontakte und Ihr Profil</p>';
        $body .= '</div>';
        
        // Kompakte Stammdaten-Karten (AdminKit-Style)
        $body .= '<div class="row g-3 mb-4">';
        
        // Objekte
        $body .= '<div class="col-md-4">';
        $body .= '<div class="card h-100">';
        $body .= '<div class="card-body p-3">';
        $body .= '<div class="d-flex align-items-center mb-2">';
        $body .= '<i class="bi bi-building text-primary me-2"></i>';
        $body .= '<h6 class="card-title mb-0">Objekte</h6>';
        $body .= '</div>';
        $body .= '<p class="text-muted small mb-3">Immobilienobjekte und Wohneinheiten verwalten</p>';
        $body .= '<a class="btn btn-outline-primary btn-sm" href="/objects">Verwalten</a>';
        $body .= '</div></div></div>';
        
        // Eigentümer
        $body .= '<div class="col-md-4">';
        $body .= '<div class="card h-100">';
        $body .= '<div class="card-body p-3">';
        $body .= '<div class="d-flex align-items-center mb-2">';
        $body .= '<i class="bi bi-person-check text-success me-2"></i>';
        $body .= '<h6 class="card-title mb-0">Eigentümer</h6>';
        $body .= '</div>';
        $body .= '<p class="text-muted small mb-3">Eigentümer anlegen und bearbeiten</p>';
        $body .= '<a class="btn btn-outline-primary btn-sm" href="/owners">Verwalten</a>';
        $body .= '</div></div></div>';
        
        // Hausverwaltungen
        $body .= '<div class="col-md-4">';
        $body .= '<div class="card h-100">';
        $body .= '<div class="card-body p-3">';
        $body .= '<div class="d-flex align-items-center mb-2">';
        $body .= '<i class="bi bi-briefcase text-warning me-2"></i>';
        $body .= '<h6 class="card-title mb-0">Hausverwaltungen</h6>';
        $body .= '</div>';
        $body .= '<p class="text-muted small mb-3">Hausverwaltungen anlegen und bearbeiten</p>';
        $body .= '<a class="btn btn-outline-primary btn-sm" href="/managers">Verwalten</a>';
        $body .= '</div></div></div>';
        
        $body .= '</div>';
        
        // Eigenes Profil (Stammdaten)
        $body .= $this->renderOwnProfile($user);
        
        View::render('Einstellungen – Stammdaten', $body);
    }

    public function generalSave(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            return;
        }
        
        $uid = (string)$user['id'];
        
        $company = trim((string)($_POST['company'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $newEmail = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        
        // Aktualisiere Profildaten in der users-Tabelle
        $stmt = $pdo->prepare('UPDATE users SET company=?, phone=?, address=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([$company, $phone, $address, $uid]);
        
        // E-Mail aktualisieren falls geändert
        if ($newEmail !== '' && $newEmail !== $user['email']) {
            $stmt = $pdo->prepare('UPDATE users SET email=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$newEmail, $uid]);
            $_SESSION['user']['email'] = $newEmail;
        }
        
        // Passwort aktualisieren falls angegeben
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$hash, $uid]);
        }

        \App\Flash::add('success', 'Profil gespeichert.');
        header('Location: /settings');
    }

    /* ---------- Mail-Einstellungen ---------- */
    public function mail(): void {
        Auth::requireAuth();
        $smtpHost = Settings::get('smtp_host', '');
        $smtpPort = Settings::get('smtp_port', '587');
        $smtpUser = Settings::get('smtp_user', '');
        $smtpPass = Settings::get('smtp_pass', '');
        $smtpSecure = Settings::get('smtp_secure', 'tls');

        $body = $this->tabs('mail');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">E-Mail-Konfiguration</h1>';
        $body .= '<form method="post" action="/settings/mail/save" class="row g-3">';
        $body .= '<div class="col-md-6"><label class="form-label">SMTP-Host</label><input class="form-control" name="smtp_host" value="'.$this->esc($smtpHost).'"></div>';
        $body .= '<div class="col-md-3"><label class="form-label">Port</label><input class="form-control" name="smtp_port" value="'.$this->esc($smtpPort).'"></div>';
        $body .= '<div class="col-md-3"><label class="form-label">Verschlüsselung</label><select class="form-select" name="smtp_secure">';
        $body .= '<option value="tls"'.($smtpSecure === 'tls' ? ' selected' : '').'>TLS</option>';
        $body .= '<option value="ssl"'.($smtpSecure === 'ssl' ? ' selected' : '').'>SSL</option>';
        $body .= '</select></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Benutzername</label><input class="form-control" name="smtp_user" value="'.$this->esc($smtpUser).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Passwort</label><input class="form-control" type="password" name="smtp_pass" value="'.$this->esc($smtpPass).'"></div>';
        $body .= '<div class="col-12"><button class="btn btn-primary">Speichern</button></div>';
        $body .= '</form></div></div>';
        View::render('Einstellungen – Mail', $body);
    }

    public function mailSave(): void {
        Auth::requireAuth();
        Settings::setMany([
            'smtp_host' => (string)(isset($_POST['smtp_host']) ? $_POST['smtp_host'] : ''),
            'smtp_port' => (string)(isset($_POST['smtp_port']) ? $_POST['smtp_port'] : '587'),
            'smtp_user' => (string)(isset($_POST['smtp_user']) ? $_POST['smtp_user'] : ''),
            'smtp_pass' => (string)(isset($_POST['smtp_pass']) ? $_POST['smtp_pass'] : ''),
            'smtp_secure' => (string)(isset($_POST['smtp_secure']) ? $_POST['smtp_secure'] : 'tls')
        ]);
        \App\Flash::add('success', 'Mail-Einstellungen gespeichert.');
        header('Location: /settings/mail');
    }

    /* ---------- DocuSign-Einstellungen ---------- */
    public function docusign(): void {
        Auth::requireAuth();
        $baseUri = Settings::get('ds_base_uri', '');
        $userId = Settings::get('ds_user_id', '');
        $clientId = Settings::get('ds_client_id', '');
        $clientSecret = Settings::get('ds_client_secret', '');

        $body = $this->tabs('docusign');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">DocuSign-Integration</h1>';
        $body .= '<form method="post" action="/settings/docusign/save" class="row g-3">';
        $body .= '<div class="col-md-6"><label class="form-label">Base URI</label><input class="form-control" name="ds_base_uri" value="'.$this->esc($baseUri).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">User ID</label><input class="form-control" name="ds_user_id" value="'.$this->esc($userId).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Client ID</label><input class="form-control" name="ds_client_id" value="'.$this->esc($clientId).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Client Secret</label><input class="form-control" type="password" name="ds_client_secret" value="'.$this->esc($clientSecret).'"></div>';
        $body .= '<div class="col-12"><button class="btn btn-primary">Speichern</button></div>';
        $body .= '</form></div></div>';
        View::render('Einstellungen – DocuSign', $body);
    }

    public function docusignSave(): void {
        Auth::requireAuth();
        Settings::setMany([
            'ds_base_uri' => (string)(isset($_POST['ds_base_uri']) ? $_POST['ds_base_uri'] : ''),
            'ds_user_id' => (string)(isset($_POST['ds_user_id']) ? $_POST['ds_user_id'] : ''),
            'ds_client_id' => (string)(isset($_POST['ds_client_id']) ? $_POST['ds_client_id'] : ''),
            'ds_client_secret' => (string)(isset($_POST['ds_client_secret']) ? $_POST['ds_client_secret'] : ''),
        ]);
        \App\Flash::add('success', 'DocuSign-Einstellungen gespeichert.');
        header('Location: /settings/docusign');
    }

    /* ---------- Textbausteine (legal_texts versioniert) ---------- */
    public function texts(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $getLatest = function(string $name) use ($pdo) {
            $st = $pdo->prepare('SELECT title, content, version FROM legal_texts WHERE name=? ORDER BY version DESC LIMIT 1');
            $st->execute(array($name));
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : array('title'=>'','content'=>'','version'=>0);
        };
        
        $datenschutz = $getLatest('datenschutz');
        $entsorgung = $getLatest('entsorgung');
        $marketing = $getLatest('marketing');
        $kaution = $getLatest('kaution_hinweis');

        $body = $this->tabs('texts');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">Textbausteine (versioniert)</h1>';
        $body .= '<form method="post" action="/settings/texts/save" class="row g-4">';
        
        $body .= '<div class="col-12"><h2 class="h6">Datenschutz (v'.$this->esc((string)$datenschutz['version']).')</h2>';
        $body .= '<label class="form-label">Titel</label><input class="form-control" name="ds_title" value="'.$this->esc((string)$datenschutz['title']).'">';
        $body .= '<label class="form-label mt-2">Inhalt</label><textarea class="form-control" rows="6" name="ds_content">'.$this->esc((string)$datenschutz['content']).'</textarea></div>';
        
        $body .= '<div class="col-12"><h2 class="h6">Entsorgung (v'.$this->esc((string)$entsorgung['version']).')</h2>';
        $body .= '<label class="form-label">Titel</label><input class="form-control" name="en_title" value="'.$this->esc((string)$entsorgung['title']).'">';
        $body .= '<label class="form-label mt-2">Inhalt</label><textarea class="form-control" rows="6" name="en_content">'.$this->esc((string)$entsorgung['content']).'</textarea></div>';
        
        $body .= '<div class="col-12"><h2 class="h6">Marketing (v'.$this->esc((string)$marketing['version']).')</h2>';
        $body .= '<label class="form-label">Titel</label><input class="form-control" name="mk_title" value="'.$this->esc((string)$marketing['title']).'">';
        $body .= '<label class="form-label mt-2">Inhalt</label><textarea class="form-control" rows="6" name="mk_content">'.$this->esc((string)$marketing['content']).'</textarea></div>';
        
        $body .= '<div class="col-12"><h2 class="h6">Hinweis zur Kautionsrückzahlung (v'.$this->esc((string)$kaution['version']).')</h2>';
        $body .= '<label class="form-label">Titel</label><input class="form-control" name="ka_title" value="'.$this->esc((string)$kaution['title']).'">';
        $body .= '<label class="form-label mt-2">Inhalt</label><textarea class="form-control" rows="6" name="ka_content">'.$this->esc((string)$kaution['content']).'</textarea></div>';
        
        $body .= '<div class="col-12"><button class="btn btn-primary">Neue Versionen speichern</button></div>';
        $body .= '</form></div></div>';
        View::render('Einstellungen – Textbausteine', $body);
    }

    public function textsSave(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');

        $insert = function(string $name, string $title, string $content) use ($pdo, $now) {
            $st = $pdo->prepare('SELECT COALESCE(MAX(version),0)+1 FROM legal_texts WHERE name=?');
            $st->execute(array($name));
            $ver = (int)$st->fetchColumn();
            $st = $pdo->prepare('INSERT INTO legal_texts (id,name,version,title,content,created_at) VALUES (UUID(),?,?,?,?,?)');
            $st->execute(array($name,$ver,$title,$content,$now));
        };

        $insert('datenschutz', (string)(isset($_POST['ds_title']) ? $_POST['ds_title'] : ''), (string)(isset($_POST['ds_content']) ? $_POST['ds_content'] : ''));
        $insert('entsorgung', (string)(isset($_POST['en_title']) ? $_POST['en_title'] : ''), (string)(isset($_POST['en_content']) ? $_POST['en_content'] : ''));
        $insert('marketing', (string)(isset($_POST['mk_title']) ? $_POST['mk_title'] : ''), (string)(isset($_POST['mk_content']) ? $_POST['mk_content'] : ''));
        $insert('kaution_hinweis', (string)(isset($_POST['ka_title']) ? $_POST['ka_title'] : ''), (string)(isset($_POST['ka_content']) ? $_POST['ka_content'] : ''));
        
        \App\Flash::add('success', 'Neue Versionen gespeichert.');
        header('Location: /settings/texts');
    }

    /* ---------- Benutzer (nur Benutzerverwaltung für Admins) ---------- */
    public function users(): void {
        Auth::requireAuth();
        $user = Auth::user(); 
        if (!$user) { 
            header('Location: /login'); 
            return; 
        }
        
        $body = $this->tabs('users');
        
        // Nur Admin sieht die Benutzerverwaltung
        if (UserAuth::isAdmin()) {
            $body .= '<div class="card">';
            $body .= '<div class="card-body">';
            $body .= '<h5 class="card-title text-primary mb-3">Benutzerverwaltung</h5>';
            $body .= '<p class="card-text text-muted mb-4">Verwalten Sie alle Benutzer und deren Rechte im System.</p>';
            
            $body .= '<div class="row g-3 mb-4">';
            $body .= '<div class="col-md-4">';
            $body .= '<div class="card bg-light">';
            $body .= '<div class="card-body text-center">';
            $body .= '<div class="h6 text-danger">Administrator</div>';
            $body .= '<p class="small text-muted mb-0">Vollzugriff auf alle Funktionen</p>';
            $body .= '</div></div></div>';
            
            $body .= '<div class="col-md-4">';
            $body .= '<div class="card bg-light">';
            $body .= '<div class="card-body text-center">';
            $body .= '<div class="h6 text-warning">Hausverwaltung</div>';
            $body .= '<p class="small text-muted mb-0">Nur zugewiesene Verwaltungen</p>';
            $body .= '</div></div></div>';
            
            $body .= '<div class="col-md-4">';
            $body .= '<div class="card bg-light">';
            $body .= '<div class="card-body text-center">';
            $body .= '<div class="h6 text-success">Eigentümer</div>';
            $body .= '<p class="small text-muted mb-0">Nur zugewiesene Eigentümer</p>';
            $body .= '</div></div></div>';
            $body .= '</div>';
            
            $body .= '<div class="d-flex gap-2">';
            $body .= '<a href="/users" class="btn btn-primary">Benutzerverwaltung öffnen</a>';
            $body .= '<a href="/settings" class="btn btn-outline-secondary">Eigenes Profil bearbeiten</a>';
            $body .= '</div>';
            
            $body .= '</div></div>';
        } else {
            // Normale Benutzer werden zu Stammdaten weitergeleitet
            $body .= '<div class="card">';
            $body .= '<div class="card-body text-center py-5">';
            $body .= '<h5 class="text-muted mb-3">Zugriff verweigert</h5>';
            $body .= '<p class="text-muted mb-4">Die Benutzerverwaltung ist nur für Administratoren verfügbar.</p>';
            $body .= '<a href="/settings" class="btn btn-primary">Zu den Stammdaten</a>';
            $body .= '</div></div>';
        }
        
        View::render('Einstellungen – Benutzer', $body);
    }
    
    private function renderOwnProfile(array $user): string {
        $uid = (string)$user['id'];
        $pdo = Database::pdo();
        
        // Hole aktuelle Daten aus der users-Tabelle
        try {
            $stmt = $pdo->prepare('SELECT email, company, phone, address FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            // Fallback: Falls neue Spalten noch nicht existieren
            $userData = ['email' => $user['email'], 'company' => '', 'phone' => '', 'address' => ''];
        }
        
        $email = $userData['email'] ?? $user['email'];
        $company = $userData['company'] ?? '';
        $phone = $userData['phone'] ?? '';
        $address = $userData['address'] ?? '';
        
        $html = '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Mein Profil</h5>';
        $html .= '<small class="text-muted">Bearbeiten Sie Ihre persönlichen Angaben</small>';
        $html .= '</div>';
        
        $html .= '<div class="card-body p-4">';
        $html .= '<form method="post" action="/settings/general/save" class="row g-3">';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Firma</label>';
        $html .= '<input class="form-control" name="company" value="'.$this->esc($company).'" placeholder="Firmenname (optional)">';
        $html .= '</div>';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Telefon</label>';
        $html .= '<input class="form-control" name="phone" value="'.$this->esc($phone).'" placeholder="+49 xxx xxxxxxx">';
        $html .= '</div>';
        
        $html .= '<div class="col-12">';
        $html .= '<label class="form-label">Adresse</label>';
        $html .= '<textarea class="form-control" rows="3" name="address" placeholder="Straße, PLZ Ort">'.$this->esc($address).'</textarea>';
        $html .= '</div>';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">E-Mail</label>';
        $html .= '<input type="email" class="form-control" name="email" value="'.$this->esc($email).'" required>';
        $html .= '</div>';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Neues Passwort</label>';
        $html .= '<input type="password" class="form-control" name="password" placeholder="Leer lassen für keine Änderung">';
        $html .= '</div>';
        
        $html .= '<div class="col-12 mt-4">';
        $html .= '<button class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Profil speichern</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div></div>';
        
        return $html;
    }

    public function usersSave(): void {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            return;
        }
        
        // Nur Admins können diese Route verwenden (Fallback-Schutz)
        if (!UserAuth::isAdmin()) {
            \App\Flash::add('error', 'Zugriff verweigert.');
            header('Location: /settings');
            return;
        }
        
        \App\Flash::add('info', 'Die Benutzerverwaltung erfolgt unter /users. Eigenes Profil unter Stammdaten.');
        header('Location: /settings/users');
    }

    /* ---------- Branding / Personalisierung (mit Logo-Löschfunktion) ---------- */
    public function branding(): void {
        Auth::requireAuth();
        $css = (string)Settings::get('custom_css', '');
        $logo = (string)Settings::get('pdf_logo_path', '');

        $body = $this->tabs('branding');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">Gestaltung (Personalisierungen)</h1>';
        
        $body .= '<form method="post" action="/settings/branding/save" enctype="multipart/form-data" class="row g-3">';
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Logo für PDF/Backend</label>';
        $body .= '<input class="form-control" type="file" name="pdf_logo" accept="image/*">';
        
        if ($logo && is_file($logo)) {
            $body .= '<div class="mt-2 p-2 border rounded bg-light">';
            $body .= '<div class="d-flex justify-content-between align-items-center">';
            $body .= '<span class="small text-muted">Aktuelles Logo: ' . $this->esc(basename($logo)) . '</span>';
            $body .= '<button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteLogo()">Entfernen</button>';
            $body .= '</div></div>';
        } else {
            $body .= '<div class="form-text">Kein Logo hochgeladen. Bei fehlendem Logo wird "Wohnungsübergabe" als Text angezeigt.</div>';
        }
        
        $body .= '</div>';
        
        $body .= '<div class="col-12">';
        $body .= '<label class="form-label">Eigenes CSS (Backend‑Theme)</label>';
        $body .= '<textarea class="form-control" rows="6" name="custom_css">' . $this->esc($css) . '</textarea>';
        $body .= '<div class="form-text">Wird als &lt;style&gt; mitgeladen – vorsichtig einsetzen.</div>';
        $body .= '</div>';
        
        $body .= '<div class="col-12"><button class="btn btn-primary">Speichern</button></div>';
        $body .= '</form>';
        
        $body .= '<form id="deleteLogoForm" method="post" action="/settings/branding/delete-logo" style="display: none;">';
        $body .= '<input type="hidden" name="delete_logo" value="1">';
        $body .= '</form>';
        
        $body .= '<script>';
        $body .= 'function deleteLogo() {';
        $body .= '  if (confirm("Möchten Sie das Logo wirklich entfernen? Es wird dann wieder der Text \\"Wohnungsübergabe\\" angezeigt.")) {';
        $body .= '    document.getElementById("deleteLogoForm").submit();';
        $body .= '  }';
        $body .= '}';
        $body .= '</script>';
        
        $body .= '</div></div>';
        View::render('Einstellungen – Gestaltung', $body);
    }

    public function brandingSave(): void {
        Auth::requireAuth();
        Settings::set('custom_css', (string)(isset($_POST['custom_css']) ? $_POST['custom_css'] : ''));

        if (!empty($_FILES['pdf_logo']['tmp_name']) && is_uploaded_file($_FILES['pdf_logo']['tmp_name'])) {
            $dir = realpath(__DIR__ . '/../../storage/branding');
            if ($dir === false) {
                $dir = __DIR__ . '/../../storage/branding';
                @mkdir($dir, 0775, true);
            }
            $ext = strtolower(pathinfo((string)(isset($_FILES['pdf_logo']['name']) ? $_FILES['pdf_logo']['name'] : ''), PATHINFO_EXTENSION) ?: 'png');
            $dest = rtrim($dir, '/') . '/logo.' . $ext;
            if (move_uploaded_file($_FILES['pdf_logo']['tmp_name'], $dest)) {
                $real = realpath($dest);
                Settings::set('pdf_logo_path', $real ? $real : $dest);
            }
        }

        \App\Flash::add('success', 'Gestaltung gespeichert.');
        header('Location: /settings/branding');
    }

    public function brandingDeleteLogo(): void {
        Auth::requireAuth();
        
        if (!isset($_POST['delete_logo']) || $_POST['delete_logo'] !== '1') {
            \App\Flash::add('error', 'Ungültige Anfrage.');
            header('Location: /settings/branding');
            return;
        }

        $logo = (string)Settings::get('pdf_logo_path', '');
        
        if ($logo && is_file($logo)) {
            if (unlink($logo)) {
                Settings::set('pdf_logo_path', '');
                \App\Flash::add('success', 'Logo wurde erfolgreich entfernt. Es wird nun wieder "Wohnungsübergabe" als Text angezeigt.');
            } else {
                \App\Flash::add('error', 'Logo-Datei konnte nicht gelöscht werden.');
            }
        } else {
            Settings::set('pdf_logo_path', '');
            \App\Flash::add('info', 'Logo-Einstellung wurde zurückgesetzt.');
        }

        header('Location: /settings/branding');
    }

    /* ---------- System-Log (technische Darstellung) ---------- */
    public function systemLogs(): void {
        Auth::requireAuth();
        
        // Stelle sicher, dass initiale Daten vorhanden sind
        \App\SystemLogger::addInitialData();
        
        // Logge den Besuch dieser Seite
        \App\SystemLogger::log('settings_viewed', 'System-Log Seite aufgerufen');
        
        // Filter-Parameter
        $page = max(1, (int)($_GET['page'] ?? 1));
        $search = trim((string)($_GET['search'] ?? ''));
        $actionFilter = (string)($_GET['action'] ?? '');
        $userFilter = (string)($_GET['user'] ?? '');
        $dateFrom = (string)($_GET['date_from'] ?? '');
        $dateTo = (string)($_GET['date_to'] ?? '');
        
        // Logs laden
        $result = \App\SystemLogger::getLogs(
            $page,
            50, // Pro Seite - weniger für bessere Performance
            $search ?: null,
            $actionFilter ?: null,
            $userFilter ?: null,
            $dateFrom ?: null,
            $dateTo ?: null
        );
        
        $logs = $result['logs'];
        $pagination = $result['pagination'];
        
        // Verfügbare Filter-Optionen
        $availableActions = \App\SystemLogger::getAvailableActions();
        $availableUsers = \App\SystemLogger::getAvailableUsers();
        
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
        $body .= 'Records: <strong>'.$pagination['total_count'].'</strong>';
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
        $body .= 'Total: <strong>'.$pagination['total_count'].'</strong> | ';
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
    
    /**
     * Bestimmt die Bootstrap-Badge-Klasse basierend auf der Aktion
     */
    private function getActionBadgeClass(string $action): string {
        if (str_contains($action, 'login')) return 'bg-success action-login';
        if (str_contains($action, 'logout')) return 'bg-warning action-logout';
        if (str_contains($action, 'failed')) return 'bg-danger action-failed';
        if (str_contains($action, 'created')) return 'bg-primary action-created';
        if (str_contains($action, 'deleted')) return 'bg-danger action-deleted';
        if (str_contains($action, 'updated') || str_contains($action, 'changed')) return 'bg-info action-updated';
        if (str_contains($action, 'sent') || str_contains($action, 'email')) return 'bg-success action-sent';
        if (str_contains($action, 'pdf') || str_contains($action, 'generated')) return 'bg-secondary action-generated';
        if (str_contains($action, 'exported')) return 'bg-warning action-exported';
        if (str_contains($action, 'viewed')) return 'bg-light text-dark action-viewed';
        return 'bg-light text-dark';
    }
}
