#!/bin/bash

echo "🚨 Parse-Error beheben und Stammdaten korrigieren"
echo "================================================"

# 1. Neuestes Backup finden und wiederherstellen
echo "📦 Suche neuestes Backup..."
latest_backup=$(ls -t backend/src/Controllers/SettingsController.php.backup_* 2>/dev/null | head -n1)

if [ -n "$latest_backup" ]; then
    echo "📁 Verwende Backup: $latest_backup"
    cp "$latest_backup" backend/src/Controllers/SettingsController.php
    echo "✅ Backup wiederhergestellt"
else
    echo "❌ Kein Backup gefunden!"
    exit 1
fi

# 2. Komplett neue, fehlerfreie SettingsController.php erstellen
echo "📝 Erstelle neue, fehlerfreie SettingsController.php..."

cat > backend/src/Controllers/SettingsController.php << 'EOF'
<?php

namespace App\Controllers;

use App\Auth;
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
            'general'   => ['Stammdaten', '/settings'],
            'mail'      => ['Mail', '/settings/mail'],
            'docusign'  => ['DocuSign', '/settings/docusign'],
            'texts'     => ['Textbausteine', '/settings/texts'],
            'users'     => ['Benutzer', '/settings/users'],
            'branding'  => ['Gestaltung', '/settings/branding']
        ];

        $html = '<ul class="nav nav-tabs mb-4">';
        foreach ($tabs as $key => [$label, $url]) {
            $class = ($key === $active) ? 'nav-link active' : 'nav-link';
            $html .= '<li class="nav-item"><a class="'.$class.'" href="'.$url.'">'.$label.'</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /* ---------- Stammdaten (Links zu Objekten, Eigentümer, Hausverwaltungen) ---------- */
    public function general(): void {
        Auth::requireAuth();
        
        $body = $this->tabs('general');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">Stammdaten</h1>';
        $body .= '<p class="text-muted">Hier finden Sie die Verwaltung aller Stammdaten Ihrer Immobilien und Kontakte.</p>';
        $body .= '</div></div>';
        
        $body .= '<div class="row g-3 mt-3">';
        
        // Objekte
        $body .= '<div class="col-md-4"><div class="card"><div class="card-body">';
        $body .= '<div class="h6 text-primary">Objekte</div>';
        $body .= '<p class="text-muted small mb-2">Immobilienobjekte und Wohneinheiten verwalten.</p>';
        $body .= '<a class="btn btn-outline-primary" href="/objects">Objekte öffnen</a>';
        $body .= '</div></div></div>';
        
        // Eigentümer
        $body .= '<div class="col-md-4"><div class="card"><div class="card-body">';
        $body .= '<div class="h6 text-success">Eigentümer</div>';
        $body .= '<p class="text-muted small mb-2">Eigentümer anlegen und bearbeiten.</p>';
        $body .= '<a class="btn btn-outline-success" href="/owners">Eigentümer öffnen</a>';
        $body .= '</div></div></div>';
        
        // Hausverwaltungen
        $body .= '<div class="col-md-4"><div class="card"><div class="card-body">';
        $body .= '<div class="h6 text-warning">Hausverwaltungen</div>';
        $body .= '<p class="text-muted small mb-2">Hausverwaltungen anlegen und bearbeiten.</p>';
        $body .= '<a class="btn btn-outline-warning" href="/managers">Hausverwaltungen öffnen</a>';
        $body .= '</div></div></div>';
        
        $body .= '</div>';
        
        View::render('Einstellungen – Stammdaten', $body);
    }

    public function generalSave(): void {
        Auth::requireAuth();
        \App\Flash::add('info', 'Stammdaten werden über die jeweiligen Bereiche verwaltet.');
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

    /* ---------- Benutzer (eigenes Profil) ---------- */
    public function users(): void {
        Auth::requireAuth();
        $user = Auth::user(); 
        if (!$user) { 
            header('Location: /login'); 
            return; 
        }
        
        $uid = (string)$user['id'];
        $company = Settings::get("user:$uid:company", '');
        $addr = Settings::get("user:$uid:address", '');
        $phone = Settings::get("user:$uid:phone", '');
        $email = (string)$user['email'];

        $body = $this->tabs('users');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">Eigenes Profil</h1>';
        $body .= '<form method="post" action="/settings/users/save" class="row g-3">';
        $body .= '<div class="col-md-6"><label class="form-label">Firma</label><input class="form-control" name="company" value="'.$this->esc($company).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Telefon</label><input class="form-control" name="phone" value="'.$this->esc($phone).'"></div>';
        $body .= '<div class="col-12"><label class="form-label">Adresse</label><textarea class="form-control" rows="3" name="address">'.$this->esc($addr).'</textarea></div>';
        $body .= '<div class="col-md-6"><label class="form-label">E‑Mail</label><input type="email" class="form-control" name="email" value="'.$this->esc($email).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Neues Passwort</label><input type="password" class="form-control" name="password" placeholder="Leer lassen für keine Änderung"></div>';
        $body .= '<div class="col-12"><button class="btn btn-primary">Speichern</button></div>';
        $body .= '</form></div></div>';
        View::render('Einstellungen – Benutzer', $body);
    }

    public function usersSave(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            return;
        }
        
        $uid = (string)$user['id'];
        
        Settings::setMany([
            "user:$uid:company" => (string)(isset($_POST['company']) ? $_POST['company'] : ''),
            "user:$uid:address" => (string)(isset($_POST['address']) ? $_POST['address'] : ''),
            "user:$uid:phone" => (string)(isset($_POST['phone']) ? $_POST['phone'] : ''),
        ]);

        $newEmail = (string)(isset($_POST['email']) ? $_POST['email'] : '');
        if ($newEmail !== '' && $newEmail !== $user['email']) {
            $st = $pdo->prepare('UPDATE users SET email=?, updated_at=NOW() WHERE id=?');
            $st->execute(array($newEmail, $uid));
            $_SESSION['user']['email'] = $newEmail;
        }
        
        $pw = (string)(isset($_POST['password']) ? $_POST['password'] : '');
        if ($pw !== '') {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $st = $pdo->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?');
            $st->execute(array($hash, $uid));
        }

        \App\Flash::add('success', 'Profil gespeichert.');
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
}
EOF

echo "✅ Neue SettingsController.php erstellt"

# Prüfe die Route für Logo-Löschung
echo "🔗 Prüfe Route für Logo-Löschung..."
if ! grep -q "/settings/branding/delete-logo" backend/public/index.php; then
    echo "📝 Füge Route zur index.php hinzu..."
    
    cp backend/public/index.php backend/public/index.php.backup_safe_$(date +%Y%m%d_%H%M%S)
    
    sed -i '/case.*\/settings\/branding\/save.*:/,/break;/a\\n    case "/settings/branding/delete-logo":\n        Auth::requireAuth();\n        (new SettingsController())->brandingDeleteLogo();\n        break;' backend/public/index.php
    
    echo "✅ Route hinzugefügt!"
else
    echo "✅ Route bereits vorhanden"
fi

echo ""
echo "🎉 PARSE-ERROR BEHOBEN UND STAMMDATEN KORRIGIERT!"
echo "================================================="
echo "✅ Alle Funktionen wiederhergestellt:"
echo "   - Parse-Error behoben"
echo "   - Stammdaten zeigen Links zu: Objekte, Eigentümer, Hausverwaltungen"
echo "   - Textbausteine mit allen 4 Bereichen"
echo "   - Benutzerprofil mit Firmenangaben"
echo "   - Logo-Löschfunktion"
echo ""
echo "🧪 Testen Sie jetzt:"
echo "👉 http://127.0.0.1:8080/settings (3 Karten mit Links)"
echo "👉 http://127.0.0.1:8080/settings/texts (4 Textbausteine)"
echo "👉 http://127.0.0.1:8080/settings/users (Firmenangaben)"
echo "👉 http://127.0.0.1:8080/settings/branding (Logo-Löschfunktion)"