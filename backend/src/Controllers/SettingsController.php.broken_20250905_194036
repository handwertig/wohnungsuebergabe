<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\View;
use App\Settings;
use App\Database;
use PDO;

final class SettingsController
{
    private function esc($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function tabs(string $active): string {
        $items = array(
            array('title'=>'Stammdaten','href'=>'/settings','key'=>'general'),
            array('title'=>'Mailversand','href'=>'/settings/mail','key'=>'mail'),
            array('title'=>'DocuSign','href'=>'/settings/docusign','key'=>'docusign'),
            array('title'=>'Textbausteine','href'=>'/settings/texts','key'=>'texts'),
            array('title'=>'Benutzer','href'=>'/settings/users','key'=>'users'),
            array('title'=>'Gestaltung','href'=>'/settings/branding','key'=>'branding'),
        );
        $html = '<ul class="nav nav-tabs mb-3">';
        foreach ($items as $it) {
            $act = ($it['key'] === $active) ? ' active' : '';
            $html .= '<li class="nav-item"><a class="nav-link'.$act.'" href="'.$this->esc($it['href']).'">'.$this->esc($it['title']).'</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /* ---------- Stammdaten: nur Verweise auf Owners / Managers / Objects ---------- */
    public function general(): void {
        Auth::requireAuth();
        $body  = $this->tabs('general');
        $body .= '<div class="row g-3">';
        $body .= '<div class="col-md-4"><div class="card"><div class="card-body">';
        $body .= '<div class="h6">Eigentümer</div><p class="text-muted small mb-2">Anlegen & bearbeiten.</p><a class="btn btn-outline-primary" href="/owners">Eigentümer öffnen</a>';
        $body .= '</div></div></div>';
        $body .= '<div class="col-md-4"><div class="card"><div class="card-body">';
        $body .= '<div class="h6">Hausverwaltungen</div><p class="text-muted small mb-2">Anlegen & bearbeiten.</p><a class="btn btn-outline-primary" href="/managers">Hausverwaltungen öffnen</a>';
        $body .= '</div></div></div>';
        $body .= '<div class="col-md-4"><div class="card"><div class="card-body">';
        $body .= '<div class="h6">Objekte</div><p class="text-muted small mb-2">Straße/Hausnummer/Ort pro Haus verwalten.</p><a class="btn btn-outline-primary" href="/objects">Objekte öffnen</a>';
        $body .= '</div></div></div>';
        $body .= '</div>';
        View::render('Einstellungen – Stammdaten', $body);
    }

    public function generalSave(): void {
        Auth::requireAuth();
        \App\Flash::add('success', 'Gespeichert.');
        header('Location: /settings');
    }

    /* ---------- Mail ---------- */
    public function mail(): void {
        Auth::requireAuth();
        $vals = array(
            'smtp_host'      => Settings::get('smtp_host',''),
            'smtp_port'      => Settings::get('smtp_port','1025'),
            'smtp_user'      => Settings::get('smtp_user',''),
            'smtp_pass'      => Settings::get('smtp_pass',''),
            'smtp_secure'    => Settings::get('smtp_secure',''),
            'smtp_from_name' => Settings::get('smtp_from_name','Wohnungsübergabe'),
            'smtp_from_email'=> Settings::get('smtp_from_email','no-reply@example.com'),
        );
        $body  = $this->tabs('mail');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">Mailversand (SMTP)</h1>';
        $body .= '<form method="post" action="/settings/mail/save" class="row g-3">';
        $body .= '<div class="col-md-6"><label class="form-label">Host</label><input class="form-control" name="smtp_host" value="'.$this->esc($vals['smtp_host']).'"></div>';
        $body .= '<div class="col-md-2"><label class="form-label">Port</label><input class="form-control" name="smtp_port" value="'.$this->esc($vals['smtp_port']).'"></div>';
        $body .= '<div class="col-md-4"><label class="form-label">Sicherheit</label><select class="form-select" name="smtp_secure">';
        $opts = array(''=>'(keine)','tls'=>'TLS','ssl'=>'SSL');
        foreach ($opts as $v=>$lbl) {
            $sel = ($v === $vals['smtp_secure']) ? ' selected' : '';
            $body .= '<option value="'.$this->esc($v).'"'.$sel.'>'.$this->esc($lbl).'</option>';
        }
        $body .= '</select></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Benutzer</label><input class="form-control" name="smtp_user" value="'.$this->esc($vals['smtp_user']).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Passwort</label><input class="form-control" type="password" name="smtp_pass" value="'.$this->esc($vals['smtp_pass']).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Absender‑Name</label><input class="form-control" name="smtp_from_name" value="'.$this->esc($vals['smtp_from_name']).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Absender‑E‑Mail</label><input class="form-control" type="email" name="smtp_from_email" value="'.$this->esc($vals['smtp_from_email']).'"></div>';
        $body .= '<div class="col-12"><button class="btn btn-primary">Speichern</button></div></form></div></div>';
        View::render('Einstellungen – Mailversand', $body);
    }

    public function mailSave(): void {
        Auth::requireAuth();
        Settings::setMany(array(
            'smtp_host'       => isset($_POST['smtp_host']) ? (string)$_POST['smtp_host'] : '',
            'smtp_port'       => isset($_POST['smtp_port']) ? (string)$_POST['smtp_port'] : '',
            'smtp_user'       => isset($_POST['smtp_user']) ? (string)$_POST['smtp_user'] : '',
            'smtp_pass'       => isset($_POST['smtp_pass']) ? (string)$_POST['smtp_pass'] : '',
            'smtp_secure'     => isset($_POST['smtp_secure']) ? (string)$_POST['smtp_secure'] : '',
            'smtp_from_name'  => isset($_POST['smtp_from_name']) ? (string)$_POST['smtp_from_name'] : '',
            'smtp_from_email' => isset($_POST['smtp_from_email']) ? (string)$_POST['smtp_from_email'] : '',
        ));
        \App\Flash::add('success','SMTP gespeichert.');
        header('Location: /settings/mail');
    }

    /* ---------- DocuSign ---------- */
    public function docusign(): void {
        Auth::requireAuth();
        $vals = array(
            'ds_account_id'    => Settings::get('ds_account_id',''),
            'ds_base_uri'      => Settings::get('ds_base_uri','https://eu.docusign.net'),
            'ds_user_id'       => Settings::get('ds_user_id',''),
            'ds_client_id'     => Settings::get('ds_client_id',''),
            'ds_client_secret' => Settings::get('ds_client_secret',''),
        );
        $body  = $this->tabs('docusign');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">DocuSign</h1>';
        $body .= '<form method="post" action="/settings/docusign/save" class="row g-3">';
        $body .= '<div class="col-md-6"><label class="form-label">API Account ID</label><input class="form-control" name="ds_account_id" value="'.$this->esc($vals['ds_account_id']).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Account Base URI</label><input class="form-control" name="ds_base_uri" value="'.$this->esc($vals['ds_base_uri']).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">User ID</label><input class="form-control" name="ds_user_id" value="'.$this->esc($vals['ds_user_id']).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Client ID</label><input class="form-control" name="ds_client_id" value="'.$this->esc($vals['ds_client_id']).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Client Secret</label><input class="form-control" type="password" name="ds_client_secret" value="'.$this->esc($vals['ds_client_secret']).'"></div>';
        $body .= '<div class="col-12"><button class="btn btn-primary">Speichern</button></div></form></div></div>';
        View::render('Einstellungen – DocuSign', $body);
    }

    public function docusignSave(): void {
        Auth::requireAuth();
        Settings::setMany(array(
            'ds_account_id'    => isset($_POST['ds_account_id']) ? (string)$_POST['ds_account_id'] : '',
            'ds_base_uri'      => isset($_POST['ds_base_uri']) ? (string)$_POST['ds_base_uri'] : '',
            'ds_user_id'       => isset($_POST['ds_user_id']) ? (string)$_POST['ds_user_id'] : '',
            'ds_client_id'     => isset($_POST['ds_client_id']) ? (string)$_POST['ds_client_id'] : '',
            'ds_client_secret' => isset($_POST['ds_client_secret']) ? (string)$_POST['ds_client_secret'] : '',
        ));
        \App\Flash::add('success','DocuSign gespeichert.');
        header('Location: /settings/docusign');
    }

    /* ---------- Textbausteine (legal_texts versioniert) ---------- */
    public function texts(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $getLatest = function($name) use ($pdo) {
            $st=$pdo->prepare('SELECT title, content, version FROM legal_texts WHERE name=? ORDER BY version DESC LIMIT 1');
            $st->execute(array($name));
            $row=$st->fetch(PDO::FETCH_ASSOC);
            if (!$row) $row = array('title'=>'','content'=>'','version'=>0);
            return $row;
        };
        $datenschutz = $getLatest('datenschutz');
        $entsorgung  = $getLatest('entsorgung');
        $marketing   = $getLatest('marketing');

        $body  = $this->tabs('texts');
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
        $body .= '<div class="col-12"><button class="btn btn-primary">Neue Versionen speichern</button></div>';
        $body .= '</form></div></div>';
        View::render('Einstellungen – Textbausteine', $body);
    }

    public function textsSave(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');

        $insert = function($name, $title, $content) use ($pdo,$now) {
            $st=$pdo->prepare('SELECT COALESCE(MAX(version),0)+1 FROM legal_texts WHERE name=?');
            $st->execute(array($name));
            $ver = (int)$st->fetchColumn();
            $st=$pdo->prepare('INSERT INTO legal_texts (id,name,version,title,content,created_at) VALUES (UUID(),?,?,?,?,?)');
            $st->execute(array($name,$ver,$title,$content,$now));
        };

        $insert('datenschutz', (string)(isset($_POST['ds_title'])?$_POST['ds_title']:''), (string)(isset($_POST['ds_content'])?$_POST['ds_content']:''));
        $insert('entsorgung',  (string)(isset($_POST['en_title'])?$_POST['en_title']:''), (string)(isset($_POST['en_content'])?$_POST['en_content']:''));
        $insert('marketing',   (string)(isset($_POST['mk_title'])?$_POST['mk_title']:''), (string)(isset($_POST['mk_content'])?$_POST['mk_content']:''));
        \App\Flash::add('success','Neue Versionen gespeichert.');
        header('Location: /settings/texts');
    }

    /* ---------- Benutzer (eigenes Profil) ---------- */
    public function users(): void {
        Auth::requireAuth();
        $user = Auth::user(); if (!$user) { header('Location: /login'); return; }
        $uid = (string)$user['id'];
        $company = Settings::get("user:$uid:company", '');
        $addr    = Settings::get("user:$uid:address", '');
        $phone   = Settings::get("user:$uid:phone", '');
        $email   = (string)$user['email'];

        $body  = $this->tabs('users');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">Eigenes Profil</h1>';
        $body .= '<form method="post" action="/settings/users/save" class="row g-3">';
        $body .= '<div class="col-md-6"><label class="form-label">Firma</label><input class="form-control" name="company" value="'.$this->esc($company).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Telefon</label><input class="form-control" name="phone" value="'.$this->esc($phone).'"></div>';
        $body .= '<div class="col-12"><label class="form-label">Adresse</label><textarea class="form-control" rows="3" name="address">'.$this->esc($addr).'</textarea></div>';
        $body .= '<div class="col-md-6"><label class="form-label">E‑Mail</label><input type="email" class="form-control" name="email" value="'.$this->esc($email).'"></div>';
        $body .= '<div class="col-md-6"><label class="form-label">Neues Passwort (optional)</label><input type="password" class="form-control" name="password"></div>';
        $body .= '<div class="col-12"><button class="btn btn-primary">Speichern</button></div>';
        $body .= '</form></div></div>';
        View::render('Einstellungen – Benutzer', $body);
    }

    public function usersSave(): void {
        Auth::requireAuth();
        $user = Auth::user(); if (!$user) { header('Location: /login'); return; }
        $uid  = (string)$user['id'];

        Settings::setMany(array(
            "user:$uid:company" => (string)(isset($_POST['company'])?$_POST['company']:''),
            "user:$uid:address" => (string)(isset($_POST['address'])?$_POST['address']:''),
            "user:$uid:phone"   => (string)(isset($_POST['phone'])?$_POST['phone']:''),
        ));

        $pdo = Database::pdo();
        $newEmail = trim((string)(isset($_POST['email'])?$_POST['email']:''));
        if ($newEmail !== '' && $newEmail !== (string)$user['email']) {
            $st=$pdo->prepare('UPDATE users SET email=?, updated_at=NOW() WHERE id=?');
            $st->execute(array($newEmail, $uid));
            $_SESSION['user']['email'] = $newEmail;
        }
        $pw = (string)(isset($_POST['password'])?$_POST['password']:'');
        if ($pw !== '') {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $st=$pdo->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?');
            $st->execute(array($hash, $uid));
        }

        \App\Flash::add('success','Profil gespeichert.');
        header('Location: /settings/users');
    }

        /* ---------- Branding / Personalisierung (mit Logo-Löschfunktion) ---------- */
    public function branding(): void {
        Auth::requireAuth();
        $css = (string)Settings::get('custom_css', '');
        $logo = (string)Settings::get('pdf_logo_path', '');

        $body = $this->tabs('branding');
        $body .= '<div class="card"><div class="card-body"><h1 class="h6 mb-3">Gestaltung (Personalisierungen)</h1>';
        
        // Logo-Upload-Formular
        $body .= '<form method="post" action="/settings/branding/save" enctype="multipart/form-data" class="row g-3">';
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Logo für PDF/Backend</label>';
        $body .= '<input class="form-control" type="file" name="pdf_logo" accept="image/*">';
        
        // Aktuelles Logo anzeigen mit Lösch-Button
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
        
        // Custom CSS
        $body .= '<div class="col-12">';
        $body .= '<label class="form-label">Eigenes CSS (Backend‑Theme)</label>';
        $body .= '<textarea class="form-control" rows="6" name="custom_css">' . $this->esc($css) . '</textarea>';
        $body .= '<div class="form-text">Wird als &lt;style&gt; mitgeladen – vorsichtig einsetzen.</div>';
        $body .= '</div>';
        
        $body .= '<div class="col-12"><button class="btn btn-primary">Speichern</button></div>';
        $body .= '</form>';
        
        // Separates Formular für Logo-Löschung (unsichtbar)
        $body .= '<form id="deleteLogoForm" method="post" action="/settings/branding/delete-logo" style="display: none;">';
        $body .= '<input type="hidden" name="delete_logo" value="1">';
        $body .= '</form>';
        
        // JavaScript für Logo-Löschung
        $body .= '<script>';
        $body .= 'function deleteLogo() {';
        $body .= '  if (confirm("Möchten Sie das Logo wirklich entfernen? Es wird dann wieder der Text \"Wohnungsübergabe\" angezeigt.")) {';
        $body .= '    document.getElementById("deleteLogoForm").submit();';
        $body .= '  }';
        $body .= '}';
        $body .= '</script>';
        
        $body .= '</div></div>';
        View::render('Einstellungen – Gestaltung', $body);
    }
/*">';
        if ($logo && is_file($logo)) $body .= '<div class="small text-muted mt-1">Aktuelles Logo: '.$this->esc(basename($logo)).'</div>';
        $body .= '</div>';
        $body .= '<div class="col-12"><label class="form-label">Eigenes CSS (Backend‑Theme)</label><textarea class="form-control" rows="6" name="custom_css">'.$this->esc($css).'</textarea><div class="form-text">Wird als &lt;style&gt; mitgeladen – vorsichtig einsetzen.</div></div>';
        $body .= '<div class="col-12"><button class="btn btn-primary">Speichern</button></div>';
        $body .= '</form></div></div>';
        View::render('Einstellungen – Gestaltung', $body);
    }

    public function brandingSave(): void {
        Auth::requireAuth();
        Settings::set('custom_css', (string)(isset($_POST['custom_css'])?$_POST['custom_css']:''));

        if (!empty($_FILES['pdf_logo']['tmp_name']) && is_uploaded_file($_FILES['pdf_logo']['tmp_name'])) {
            $dir = realpath(__DIR__.'/../../storage/branding');
            if ($dir === false) { $dir = __DIR__.'/../../storage/branding'; @mkdir($dir, 0775, true); }
            $ext = strtolower(pathinfo((string)(isset($_FILES['pdf_logo']['name'])?$_FILES['pdf_logo']['name']:''), PATHINFO_EXTENSION) ?: 'png');
            $dest = rtrim($dir, '/').'/logo.'.$ext;
            if (move_uploaded_file($_FILES['pdf_logo']['tmp_name'], $dest)) {
                $real = realpath($dest);
                Settings::set('pdf_logo_path', $real ? $real : $dest);
            }
        }

        \App\Flash::add('success','Gestaltung gespeichert.');
        header('Location: /settings/branding');
    }
}
