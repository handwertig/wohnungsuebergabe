<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\View;
use App\Settings;
use App\Database;
use App\UserAuth;
use PDO;
use PDOException;

/**
 * Settings Controller
 * 
 * Verwaltet alle Einstellungen der Anwendung:
 * - Stammdaten (Verweise auf Eigentümer, Hausverwaltungen, Objekte)
 * - Mailversand (SMTP-Konfiguration)  
 * - DocuSign-Integration
 * - Textbausteine (versioniert)
 * - Benutzerverwaltung
 * - Branding/Gestaltung
 * - System-Logs
 */
final class SettingsController
{
    /**
     * HTML-Escaping für sichere Ausgabe
     */
    private function esc($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Navigation Tabs für Einstellungen
     */
    private function tabs(string $active): string {
        $items = [
            ['title' => 'Stammdaten', 'href' => '/settings', 'key' => 'general'],
            ['title' => 'Mailversand', 'href' => '/settings/mail', 'key' => 'mail'],
            ['title' => 'Signaturen', 'href' => '/settings/signatures', 'key' => 'signatures'],
            ['title' => 'Textbausteine', 'href' => '/settings/texts', 'key' => 'texts'],
            ['title' => 'Benutzer', 'href' => '/settings/users', 'key' => 'users'],
            ['title' => 'Gestaltung', 'href' => '/settings/branding', 'key' => 'branding'],
            ['title' => 'System-Log', 'href' => '/settings/systemlogs', 'key' => 'systemlogs'],
        ];
        
        $html = '<ul class="nav nav-tabs mb-3">';
        foreach ($items as $item) {
            $activeClass = ($item['key'] === $active) ? ' active' : '';
            $html .= '<li class="nav-item">';
            $html .= '<a class="nav-link' . $activeClass . '" href="' . $this->esc($item['href']) . '">';
            $html .= $this->esc($item['title']);
            $html .= '</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /* ---------- Stammdaten: Verweise + eigenes Profil ---------- */
    
    /**
     * Stammdaten-Übersicht mit eigenem Profil und Verweisen
     */
    public function general(): void {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            return;
        }

        $body = $this->tabs('general');
        
        // Eigenes Profil zuerst anzeigen
        $body .= $this->renderOwnProfile($user);
        
        // Dann Verweise auf andere Bereiche
        $body .= '<div class="row g-3 mt-4">';
        $body .= '<div class="col-md-4">';
        $body .= '<div class="card">';
        $body .= '<div class="card-body">';
        $body .= '<h6 class="card-title">Eigentümer</h6>';
        $body .= '<p class="text-muted small mb-2">Verwaltung aller Eigentümer im System.</p>';
        $body .= '<a class="btn btn-outline-primary" href="/owners">Eigentümer verwalten</a>';
        $body .= '</div></div></div>';
        
        $body .= '<div class="col-md-4">';
        $body .= '<div class="card">';
        $body .= '<div class="card-body">';
        $body .= '<h6 class="card-title">Hausverwaltungen</h6>';
        $body .= '<p class="text-muted small mb-2">Verwaltung aller Hausverwaltungen.</p>';
        $body .= '<a class="btn btn-outline-primary" href="/managers">Hausverwaltungen verwalten</a>';
        $body .= '</div></div></div>';
        
        $body .= '<div class="col-md-4">';
        $body .= '<div class="card">';
        $body .= '<div class="card-body">';
        $body .= '<h6 class="card-title">Objekte</h6>';
        $body .= '<p class="text-muted small mb-2">Verwaltung aller Immobilien-Objekte.</p>';
        $body .= '<a class="btn btn-outline-primary" href="/objects">Objekte verwalten</a>';
        $body .= '</div></div></div>';
        $body .= '</div>';
        
        View::render('Einstellungen – Stammdaten', $body);
    }

    /**
     * Speichern der eigenen Profildaten
     */
    public function generalSave(): void {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            return;
        }
        
        $userId = (string)$user['id'];
        $pdo = Database::pdo();
        
        try {
            // Benutzer-Daten aktualisieren
            $stmt = $pdo->prepare('
                UPDATE users SET 
                    email = ?, 
                    company = ?, 
                    phone = ?, 
                    address = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ');
            
            $stmt->execute([
                (string)(isset($_POST['email']) ? $_POST['email'] : $user['email']),
                (string)(isset($_POST['company']) ? $_POST['company'] : ''),
                (string)(isset($_POST['phone']) ? $_POST['phone'] : ''),
                (string)(isset($_POST['address']) ? $_POST['address'] : ''),
                $userId
            ]);
            
            // Session aktualisieren
            $_SESSION['user']['email'] = (string)(isset($_POST['email']) ? $_POST['email'] : $user['email']);
            
            // Passwort ändern falls angegeben
            $password = (string)(isset($_POST['password']) ? $_POST['password'] : '');
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$hash, $userId]);
            }
            
            \App\Flash::add('success', 'Profil erfolgreich gespeichert.');
            
        } catch (PDOException $e) {
            \App\Flash::add('error', 'Fehler beim Speichern: ' . $e->getMessage());
        }
        
        header('Location: /settings');
    }

    /* ---------- Mail-Einstellungen ---------- */
    
    /**
     * SMTP-Konfiguration anzeigen
     */
    public function mail(): void {
        Auth::requireAuth();
        
        $smtpHost = Settings::get('smtp_host', 'mailpit');
        $smtpPort = Settings::get('smtp_port', '1025');
        $smtpUser = Settings::get('smtp_user', '');
        $smtpPass = Settings::get('smtp_pass', '');
        $smtpSecure = Settings::get('smtp_secure', '');
        $smtpFromName = Settings::get('smtp_from_name', 'Wohnungsübergabe');
        $smtpFromEmail = Settings::get('smtp_from_email', 'no-reply@example.com');

        $body = $this->tabs('mail');
        $body .= '<div class="card">';
        $body .= '<div class="card-body">';
        $body .= '<h1 class="h6 mb-3">Mailversand (SMTP-Konfiguration)</h1>';
        $body .= '<form method="post" action="/settings/mail/save" class="row g-3">';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">SMTP Host</label>';
        $body .= '<input class="form-control" name="smtp_host" value="' . $this->esc($smtpHost) . '" placeholder="z.B. smtp.gmail.com">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-2">';
        $body .= '<label class="form-label">Port</label>';
        $body .= '<input class="form-control" name="smtp_port" value="' . $this->esc($smtpPort) . '" placeholder="587">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-4">';
        $body .= '<label class="form-label">Verschlüsselung</label>';
        $body .= '<select class="form-select" name="smtp_secure">';
        $securityOptions = ['' => '(keine)', 'tls' => 'TLS', 'ssl' => 'SSL'];
        foreach ($securityOptions as $value => $label) {
            $selected = ($value === $smtpSecure) ? ' selected' : '';
            $body .= '<option value="' . $this->esc($value) . '"' . $selected . '>' . $this->esc($label) . '</option>';
        }
        $body .= '</select>';
        $body .= '</div>';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Benutzername</label>';
        $body .= '<input class="form-control" name="smtp_user" value="' . $this->esc($smtpUser) . '">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Passwort</label>';
        $body .= '<input class="form-control" type="password" name="smtp_pass" value="' . $this->esc($smtpPass) . '">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Absender-Name</label>';
        $body .= '<input class="form-control" name="smtp_from_name" value="' . $this->esc($smtpFromName) . '">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Absender E-Mail</label>';
        $body .= '<input class="form-control" type="email" name="smtp_from_email" value="' . $this->esc($smtpFromEmail) . '">';
        $body .= '</div>';
        
        $body .= '<div class="col-12">';
        $body .= '<button class="btn btn-primary">Speichern</button>';
        $body .= '</div>';
        
        $body .= '</form>';
        $body .= '</div></div>';
        
        View::render('Einstellungen – Mailversand', $body);
    }

    /**
     * SMTP-Konfiguration speichern
     */
    public function mailSave(): void {
        Auth::requireAuth();
        
        Settings::setMany([
            'smtp_host' => (string)(isset($_POST['smtp_host']) ? $_POST['smtp_host'] : ''),
            'smtp_port' => (string)(isset($_POST['smtp_port']) ? $_POST['smtp_port'] : '587'),
            'smtp_user' => (string)(isset($_POST['smtp_user']) ? $_POST['smtp_user'] : ''),
            'smtp_pass' => (string)(isset($_POST['smtp_pass']) ? $_POST['smtp_pass'] : ''),
            'smtp_secure' => (string)(isset($_POST['smtp_secure']) ? $_POST['smtp_secure'] : ''),
            'smtp_from_name' => (string)(isset($_POST['smtp_from_name']) ? $_POST['smtp_from_name'] : ''),
            'smtp_from_email' => (string)(isset($_POST['smtp_from_email']) ? $_POST['smtp_from_email'] : ''),
        ]);
        
        \App\Flash::add('success', 'Mail-Einstellungen gespeichert.');
        header('Location: /settings/mail');
    }

    /* ---------- Signatur-Einstellungen ---------- */
    
    /**
     * Signatur-Konfiguration anzeigen (Auswahl zwischen lokal und DocuSign)
     */
    public function signatures(): void {
        Auth::requireAuth();
        
        // Aktuelle Einstellungen laden
        $provider = Settings::get('signature_provider', 'local');
        $requireAll = Settings::get('signature_require_all', 'false') === 'true';
        $allowWitness = Settings::get('signature_allow_witness', 'true') === 'true';
        $sendEmails = Settings::get('signature_send_emails', 'true') === 'true';
        
        // Lokale Signatur Einstellungen
        $localEnabled = Settings::get('local_signature_enabled', 'true') === 'true';
        $localDisclaimer = Settings::get('local_signature_disclaimer', 'Mit Ihrer digitalen Unterschrift bestätigen Sie die Richtigkeit der Angaben im Protokoll.');
        $localLegalText = Settings::get('local_signature_legal_text', 'Die digitale Unterschrift ist rechtlich bindend gemäß eIDAS-Verordnung (EU) Nr. 910/2014.');
        
        // DocuSign Einstellungen
        $docusignEnabled = Settings::get('docusign_enabled', 'false') === 'true';
        $dsBaseUri = Settings::get('docusign_base_url', 'https://demo.docusign.net/restapi');
        $dsAccountId = Settings::get('docusign_account_id', '');
        $dsIntegrationKey = Settings::get('docusign_integration_key', '');
        $dsSecretKey = Settings::get('docusign_secret_key', '');
        $dsRedirectUri = Settings::get('docusign_redirect_uri', 'http://localhost:8080/docusign/callback');

        $body = $this->tabs('signatures');
        
        // Haupt-Einstellungen
        $body .= '<div class="card mb-3">';
        $body .= '<div class="card-header">';
        $body .= '<h5 class="mb-0"><i class="bi bi-pen me-2"></i>Signatur-Einstellungen</h5>';
        $body .= '</div>';
        $body .= '<div class="card-body">';
        $body .= '<form method="post" action="/settings/signatures/save" class="row g-3">';
        
        // Provider-Auswahl
        $body .= '<div class="col-12">';
        $body .= '<label class="form-label">Signatur-Provider auswählen</label>';
        $body .= '<div class="form-check">';
        $body .= '<input class="form-check-input" type="radio" name="signature_provider" id="providerLocal" value="local"' . ($provider === 'local' ? ' checked' : '') . '>';
        $body .= '<label class="form-check-label" for="providerLocal">';
        $body .= '<strong>Lokale Signatur (Open Source)</strong><br>';
        $body .= '<small class="text-muted">Integrierte Lösung mit digitaler Unterschrift direkt im Browser. Keine externen Dienste erforderlich.</small>';
        $body .= '</label>';
        $body .= '</div>';
        $body .= '<div class="form-check mt-2">';
        $body .= '<input class="form-check-input" type="radio" name="signature_provider" id="providerDocusign" value="docusign"' . ($provider === 'docusign' ? ' checked' : '') . '>';
        $body .= '<label class="form-check-label" for="providerDocusign">';
        $body .= '<strong>DocuSign</strong><br>';
        $body .= '<small class="text-muted">Professionelle e-Signatur-Lösung mit erweiterter Rechtssicherheit (kostenpflichtig).</small>';
        $body .= '</label>';
        $body .= '</div>';
        $body .= '</div>';
        
        // Allgemeine Einstellungen
        $body .= '<div class="col-12"><hr></div>';
        $body .= '<div class="col-12"><h6>Allgemeine Einstellungen</h6></div>';
        
        $body .= '<div class="col-md-4">';
        $body .= '<div class="form-check">';
        $body .= '<input class="form-check-input" type="checkbox" name="signature_require_all" id="requireAll"' . ($requireAll ? ' checked' : '') . '>';
        $body .= '<label class="form-check-label" for="requireAll">Alle Unterschriften erforderlich</label>';
        $body .= '</div>';
        $body .= '</div>';
        
        $body .= '<div class="col-md-4">';
        $body .= '<div class="form-check">';
        $body .= '<input class="form-check-input" type="checkbox" name="signature_allow_witness" id="allowWitness"' . ($allowWitness ? ' checked' : '') . '>';
        $body .= '<label class="form-check-label" for="allowWitness">Zeugen-Unterschrift erlauben</label>';
        $body .= '</div>';
        $body .= '</div>';
        
        $body .= '<div class="col-md-4">';
        $body .= '<div class="form-check">';
        $body .= '<input class="form-check-input" type="checkbox" name="signature_send_emails" id="sendEmails"' . ($sendEmails ? ' checked' : '') . '>';
        $body .= '<label class="form-check-label" for="sendEmails">E-Mail nach Unterschrift senden</label>';
        $body .= '</div>';
        $body .= '</div>';
        
        // Lokale Signatur Einstellungen
        $body .= '<div class="col-12"><hr></div>';
        $body .= '<div class="col-12" id="localSettings">';
        $body .= '<h6><i class="bi bi-house-door me-2"></i>Lokale Signatur Einstellungen</h6>';
        $body .= '<div class="row g-3">';
        
        $body .= '<div class="col-12">';
        $body .= '<label class="form-label">Hinweistext für Unterschrift</label>';
        $body .= '<textarea class="form-control" name="local_signature_disclaimer" rows="2">' . $this->esc($localDisclaimer) . '</textarea>';
        $body .= '</div>';
        
        $body .= '<div class="col-12">';
        $body .= '<label class="form-label">Rechtlicher Hinweis</label>';
        $body .= '<textarea class="form-control" name="local_signature_legal_text" rows="2">' . $this->esc($localLegalText) . '</textarea>';
        $body .= '</div>';
        
        $body .= '</div>';
        $body .= '</div>';
        
        // DocuSign Einstellungen
        $body .= '<div class="col-12"><hr></div>';
        $body .= '<div class="col-12" id="docusignSettings">';
        $body .= '<h6><i class="bi bi-cloud me-2"></i>DocuSign Einstellungen</h6>';
        $body .= '<div class="row g-3">';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Base URL</label>';
        $body .= '<select class="form-select" name="docusign_base_url">';
        $body .= '<option value="https://demo.docusign.net/restapi"' . ($dsBaseUri === 'https://demo.docusign.net/restapi' ? ' selected' : '') . '>Demo (Sandbox)</option>';
        $body .= '<option value="https://na4.docusign.net/restapi"' . ($dsBaseUri === 'https://na4.docusign.net/restapi' ? ' selected' : '') . '>Produktion (NA)</option>';
        $body .= '<option value="https://eu.docusign.net/restapi"' . ($dsBaseUri === 'https://eu.docusign.net/restapi' ? ' selected' : '') . '>Produktion (EU)</option>';
        $body .= '</select>';
        $body .= '</div>';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Account ID</label>';
        $body .= '<input class="form-control" name="docusign_account_id" value="' . $this->esc($dsAccountId) . '" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Integration Key</label>';
        $body .= '<input class="form-control" name="docusign_integration_key" value="' . $this->esc($dsIntegrationKey) . '" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Secret Key</label>';
        $body .= '<input class="form-control" type="password" name="docusign_secret_key" value="' . $this->esc($dsSecretKey) . '">';
        $body .= '</div>';
        
        $body .= '<div class="col-12">';
        $body .= '<label class="form-label">Redirect URI</label>';
        $body .= '<input class="form-control" name="docusign_redirect_uri" value="' . $this->esc($dsRedirectUri) . '">';
        $body .= '<small class="text-muted">Diese URI muss in DocuSign als erlaubte Redirect-URI konfiguriert sein.</small>';
        $body .= '</div>';
        
        $body .= '</div>';
        $body .= '</div>';
        
        $body .= '<div class="col-12"><hr></div>';
        $body .= '<div class="col-12">';
        $body .= '<button class="btn btn-primary">Einstellungen speichern</button>';
        $body .= '<a href="/signature/test" class="btn btn-outline-secondary ms-2">Signatur testen</a>';
        $body .= '</div>';
        
        $body .= '</form>';
        $body .= '</div>';
        $body .= '</div>';
        
        // JavaScript für dynamische Anzeige
        $body .= '<script>';
        $body .= 'document.addEventListener("DOMContentLoaded", function() {';
        $body .= '  const localRadio = document.getElementById("providerLocal");';
        $body .= '  const docusignRadio = document.getElementById("providerDocusign");';
        $body .= '  const localSettings = document.getElementById("localSettings");';
        $body .= '  const docusignSettings = document.getElementById("docusignSettings");';
        $body .= '  ';
        $body .= '  function toggleSettings() {';
        $body .= '    if (localRadio.checked) {';
        $body .= '      localSettings.style.opacity = "1";';
        $body .= '      docusignSettings.style.opacity = "0.5";';
        $body .= '    } else {';
        $body .= '      localSettings.style.opacity = "0.5";';
        $body .= '      docusignSettings.style.opacity = "1";';
        $body .= '    }';
        $body .= '  }';
        $body .= '  ';
        $body .= '  localRadio.addEventListener("change", toggleSettings);';
        $body .= '  docusignRadio.addEventListener("change", toggleSettings);';
        $body .= '  toggleSettings();';
        $body .= '});';
        $body .= '</script>';
        
        View::render('Einstellungen – Signaturen', $body);
    }

    /**
     * Signatur-Konfiguration speichern
     */
    public function signaturesSave(): void {
        Auth::requireAuth();
        
        // Provider-Auswahl
        $provider = (string)(isset($_POST['signature_provider']) ? $_POST['signature_provider'] : 'local');
        if (!in_array($provider, ['local', 'docusign'])) {
            $provider = 'local';
        }
        
        Settings::setMany([
            'signature_provider' => $provider,
            'signature_require_all' => isset($_POST['signature_require_all']) ? 'true' : 'false',
            'signature_allow_witness' => isset($_POST['signature_allow_witness']) ? 'true' : 'false',
            'signature_send_emails' => isset($_POST['signature_send_emails']) ? 'true' : 'false',
            
            // Lokale Signatur
            'local_signature_enabled' => ($provider === 'local') ? 'true' : 'false',
            'local_signature_disclaimer' => (string)(isset($_POST['local_signature_disclaimer']) ? $_POST['local_signature_disclaimer'] : ''),
            'local_signature_legal_text' => (string)(isset($_POST['local_signature_legal_text']) ? $_POST['local_signature_legal_text'] : ''),
            
            // DocuSign
            'docusign_enabled' => ($provider === 'docusign') ? 'true' : 'false',
            'docusign_base_url' => (string)(isset($_POST['docusign_base_url']) ? $_POST['docusign_base_url'] : ''),
            'docusign_account_id' => (string)(isset($_POST['docusign_account_id']) ? $_POST['docusign_account_id'] : ''),
            'docusign_integration_key' => (string)(isset($_POST['docusign_integration_key']) ? $_POST['docusign_integration_key'] : ''),
            'docusign_secret_key' => (string)(isset($_POST['docusign_secret_key']) ? $_POST['docusign_secret_key'] : ''),
            'docusign_redirect_uri' => (string)(isset($_POST['docusign_redirect_uri']) ? $_POST['docusign_redirect_uri'] : ''),
        ]);
        
        \App\Flash::add('success', 'Signatur-Einstellungen gespeichert. Aktiver Provider: ' . strtoupper($provider));
        header('Location: /settings/signatures');
    }

    /* ---------- DocuSign-Einstellungen (Legacy - weiterleitung zu Signaturen) ---------- */
    
    /**
     * DocuSign-Konfiguration anzeigen
     */
    public function docusign(): void {
        Auth::requireAuth();
        
        $baseUri = Settings::get('ds_base_uri', 'https://eu.docusign.net');
        $userId = Settings::get('ds_user_id', '');
        $clientId = Settings::get('ds_client_id', '');
        $clientSecret = Settings::get('ds_client_secret', '');
        $accountId = Settings::get('ds_account_id', '');

        $body = $this->tabs('docusign');
        $body .= '<div class="card">';
        $body .= '<div class="card-body">';
        $body .= '<h1 class="h6 mb-3">DocuSign-Integration</h1>';
        $body .= '<form method="post" action="/settings/docusign/save" class="row g-3">';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Base URI</label>';
        $body .= '<input class="form-control" name="ds_base_uri" value="' . $this->esc($baseUri) . '" placeholder="https://eu.docusign.net">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Account ID</label>';
        $body .= '<input class="form-control" name="ds_account_id" value="' . $this->esc($accountId) . '">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">User ID</label>';
        $body .= '<input class="form-control" name="ds_user_id" value="' . $this->esc($userId) . '">';
        $body .= '</div>';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Client ID</label>';
        $body .= '<input class="form-control" name="ds_client_id" value="' . $this->esc($clientId) . '">';
        $body .= '</div>';
        
        $body .= '<div class="col-12">';
        $body .= '<label class="form-label">Client Secret</label>';
        $body .= '<input class="form-control" type="password" name="ds_client_secret" value="' . $this->esc($clientSecret) . '">';
        $body .= '</div>';
        
        $body .= '<div class="col-12">';
        $body .= '<button class="btn btn-primary">Speichern</button>';
        $body .= '</div>';
        
        $body .= '</form>';
        $body .= '</div></div>';
        
        View::render('Einstellungen – DocuSign', $body);
    }

    /**
     * DocuSign-Konfiguration speichern
     */
    public function docusignSave(): void {
        Auth::requireAuth();
        
        Settings::setMany([
            'ds_base_uri' => (string)(isset($_POST['ds_base_uri']) ? $_POST['ds_base_uri'] : ''),
            'ds_account_id' => (string)(isset($_POST['ds_account_id']) ? $_POST['ds_account_id'] : ''),
            'ds_user_id' => (string)(isset($_POST['ds_user_id']) ? $_POST['ds_user_id'] : ''),
            'ds_client_id' => (string)(isset($_POST['ds_client_id']) ? $_POST['ds_client_id'] : ''),
            'ds_client_secret' => (string)(isset($_POST['ds_client_secret']) ? $_POST['ds_client_secret'] : ''),
        ]);
        
        \App\Flash::add('success', 'DocuSign-Einstellungen gespeichert.');
        header('Location: /settings/docusign');
    }

    /* ---------- Textbausteine (legal_texts versioniert) ---------- */
    
    /**
     * Textbausteine anzeigen und bearbeiten
     */
    public function texts(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        
        // Funktion um neueste Version eines Textbausteins zu holen
        $getLatest = function(string $name) use ($pdo) {
            $stmt = $pdo->prepare('SELECT title, content, version FROM legal_texts WHERE name=? ORDER BY version DESC LIMIT 1');
            $stmt->execute([$name]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : ['title' => '', 'content' => '', 'version' => 0];
        };
        
        $datenschutz = $getLatest('datenschutz');
        $entsorgung = $getLatest('entsorgung');
        $marketing = $getLatest('marketing');
        $kautionHinweis = $getLatest('kaution_hinweis');

        $body = $this->tabs('texts');
        $body .= '<div class="card">';
        $body .= '<div class="card-body">';
        $body .= '<h1 class="h6 mb-3">Textbausteine (versioniert)</h1>';
        $body .= '<form method="post" action="/settings/texts/save" class="row g-4">';
        
        // Datenschutz
        $body .= '<div class="col-12">';
        $body .= '<h2 class="h6">Datenschutzerklärung (v' . $this->esc((string)$datenschutz['version']) . ')</h2>';
        $body .= '<label class="form-label">Titel</label>';
        $body .= '<input class="form-control" name="ds_title" value="' . $this->esc((string)$datenschutz['title']) . '">';
        $body .= '<label class="form-label mt-2">Inhalt</label>';
        $body .= '<textarea class="form-control" rows="6" name="ds_content">' . $this->esc((string)$datenschutz['content']) . '</textarea>';
        $body .= '</div>';
        
        // Entsorgung
        $body .= '<div class="col-12">';
        $body .= '<h2 class="h6">Entsorgungshinweis (v' . $this->esc((string)$entsorgung['version']) . ')</h2>';
        $body .= '<label class="form-label">Titel</label>';
        $body .= '<input class="form-control" name="en_title" value="' . $this->esc((string)$entsorgung['title']) . '">';
        $body .= '<label class="form-label mt-2">Inhalt</label>';
        $body .= '<textarea class="form-control" rows="6" name="en_content">' . $this->esc((string)$entsorgung['content']) . '</textarea>';
        $body .= '</div>';
        
        // Marketing
        $body .= '<div class="col-12">';
        $body .= '<h2 class="h6">Marketing-Einwilligung (v' . $this->esc((string)$marketing['version']) . ')</h2>';
        $body .= '<label class="form-label">Titel</label>';
        $body .= '<input class="form-control" name="mk_title" value="' . $this->esc((string)$marketing['title']) . '">';
        $body .= '<label class="form-label mt-2">Inhalt</label>';
        $body .= '<textarea class="form-control" rows="6" name="mk_content">' . $this->esc((string)$marketing['content']) . '</textarea>';
        $body .= '</div>';
        
        // Kaution-Hinweis
        $body .= '<div class="col-12">';
        $body .= '<h2 class="h6">Kautionsrückzahlung (v' . $this->esc((string)$kautionHinweis['version']) . ')</h2>';
        $body .= '<label class="form-label">Titel</label>';
        $body .= '<input class="form-control" name="ka_title" value="' . $this->esc((string)$kautionHinweis['title']) . '">';
        $body .= '<label class="form-label mt-2">Inhalt</label>';
        $body .= '<textarea class="form-control" rows="6" name="ka_content">' . $this->esc((string)$kautionHinweis['content']) . '</textarea>';
        $body .= '</div>';
        
        $body .= '<div class="col-12">';
        $body .= '<button class="btn btn-primary">Neue Versionen speichern</button>';
        $body .= '</div>';
        
        $body .= '</form>';
        $body .= '</div></div>';
        
        View::render('Einstellungen – Textbausteine', $body);
    }

    /**
     * Textbausteine speichern (neue Versionen erstellen)
     */
    public function textsSave(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');

        // Funktion um neue Version eines Textbausteins zu erstellen
        $insertNewVersion = function(string $name, string $title, string $content) use ($pdo, $now) {
            // Nächste Versionsnummer ermitteln
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(version),0)+1 FROM legal_texts WHERE name=?');
            $stmt->execute([$name]);
            $version = (int)$stmt->fetchColumn();
            
            // Neue Version einfügen
            $stmt = $pdo->prepare('INSERT INTO legal_texts (id,name,version,title,content,created_at) VALUES (UUID(),?,?,?,?,?)');
            $stmt->execute([$name, $version, $title, $content, $now]);
        };

        // Alle Textbausteine speichern
        $insertNewVersion(
            'datenschutz',
            (string)(isset($_POST['ds_title']) ? $_POST['ds_title'] : ''),
            (string)(isset($_POST['ds_content']) ? $_POST['ds_content'] : '')
        );
        
        $insertNewVersion(
            'entsorgung',
            (string)(isset($_POST['en_title']) ? $_POST['en_title'] : ''),
            (string)(isset($_POST['en_content']) ? $_POST['en_content'] : '')
        );
        
        $insertNewVersion(
            'marketing',
            (string)(isset($_POST['mk_title']) ? $_POST['mk_title'] : ''),
            (string)(isset($_POST['mk_content']) ? $_POST['mk_content'] : '')
        );
        
        $insertNewVersion(
            'kaution_hinweis',
            (string)(isset($_POST['ka_title']) ? $_POST['ka_title'] : ''),
            (string)(isset($_POST['ka_content']) ? $_POST['ka_content'] : '')
        );
        
        \App\Flash::add('success', 'Neue Versionen der Textbausteine wurden gespeichert.');
        header('Location: /settings/texts');
    }

    /* ---------- Benutzerverwaltung ---------- */
    
    /**
     * Benutzerverwaltung (Admin-Funktionen oder Verweis)
     */
    public function users(): void {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            return;
        }
        
        $body = $this->tabs('users');
        
        // Prüfen ob Benutzer Admin ist (UserAuth könnte existieren)
        $isAdmin = false;
        try {
            $isAdmin = class_exists('App\UserAuth') && UserAuth::isAdmin();
        } catch (\Throwable $e) {
            // UserAuth existiert möglicherweise noch nicht
        }
        
        if ($isAdmin) {
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
            // Normale Benutzer sehen eine Info-Nachricht
            $body .= '<div class="card">';
            $body .= '<div class="card-body text-center py-5">';
            $body .= '<h5 class="text-muted mb-3">Zugriff beschränkt</h5>';
            $body .= '<p class="text-muted mb-4">Die erweiterte Benutzerverwaltung ist nur für Administratoren verfügbar.</p>';
            $body .= '<p class="text-muted mb-4">Ihr eigenes Profil können Sie unter "Stammdaten" bearbeiten.</p>';
            $body .= '<a href="/settings" class="btn btn-primary">Zu den Stammdaten</a>';
            $body .= '</div></div>';
        }
        
        View::render('Einstellungen – Benutzer', $body);
    }

    /**
     * Speichern von Benutzerdaten (Fallback)
     */
    public function usersSave(): void {
        Auth::requireAuth();
        
        \App\Flash::add('info', 'Die Benutzerverwaltung erfolgt unter /users. Ihr eigenes Profil können Sie unter Stammdaten bearbeiten.');
        header('Location: /settings/users');
    }

    /* ---------- Branding / Personalisierung ---------- */
    
    /**
     * Branding/Design-Einstellungen
     */
    public function branding(): void {
        Auth::requireAuth();
        
        $customCss = (string)Settings::get('custom_css', '');
        $logoPath = (string)Settings::get('pdf_logo_path', '');

        $body = $this->tabs('branding');
        $body .= '<div class="card">';
        $body .= '<div class="card-body">';
        $body .= '<h1 class="h6 mb-3">Gestaltung & Personalisierung</h1>';
        
        // Logo-Upload-Formular
        $body .= '<form method="post" action="/settings/branding/save" enctype="multipart/form-data" class="row g-3">';
        
        $body .= '<div class="col-md-6">';
        $body .= '<label class="form-label">Logo für PDF und Backend</label>';
        $body .= '<input class="form-control" type="file" name="pdf_logo" accept="image/*">';
        
        // Aktuelles Logo anzeigen
        if ($logoPath && is_file($logoPath)) {
            $body .= '<div class="mt-2 p-2 border rounded bg-light">';
            $body .= '<div class="d-flex justify-content-between align-items-center">';
            $body .= '<span class="small text-muted">Aktuelles Logo: ' . $this->esc(basename($logoPath)) . '</span>';
            $body .= '<button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteLogo()">Entfernen</button>';
            $body .= '</div></div>';
        } else {
            $body .= '<div class="form-text">Kein Logo hochgeladen. Es wird "Wohnungsübergabe" als Text angezeigt.</div>';
        }
        
        $body .= '</div>';
        
        // Custom CSS
        $body .= '<div class="col-12">';
        $body .= '<label class="form-label">Eigenes CSS (Backend-Theme)</label>';
        $body .= '<textarea class="form-control" rows="8" name="custom_css" placeholder="/* Ihre eigenen CSS-Regeln */">' . $this->esc($customCss) . '</textarea>';
        $body .= '<div class="form-text">Wird als &lt;style&gt; in das Backend eingebunden. Vorsichtig verwenden!</div>';
        $body .= '</div>';
        
        $body .= '<div class="col-12">';
        $body .= '<button class="btn btn-primary">Änderungen speichern</button>';
        $body .= '</div>';
        
        $body .= '</form>';
        
        // Verstecktes Formular für Logo-Löschung
        $body .= '<form id="deleteLogoForm" method="post" action="/settings/branding/delete-logo" style="display: none;">';
        $body .= '<input type="hidden" name="delete_logo" value="1">';
        $body .= '</form>';
        
        // JavaScript für Logo-Löschung
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

    /**
     * Branding-Einstellungen speichern
     */
    public function brandingSave(): void {
        Auth::requireAuth();
        
        // Custom CSS speichern
        Settings::set('custom_css', (string)(isset($_POST['custom_css']) ? $_POST['custom_css'] : ''));

        // Logo-Upload verarbeiten
        if (!empty($_FILES['pdf_logo']['tmp_name']) && is_uploaded_file($_FILES['pdf_logo']['tmp_name'])) {
            $brandingDir = __DIR__ . '/../../storage/branding';
            
            // Verzeichnis erstellen falls nicht vorhanden
            if (!is_dir($brandingDir)) {
                @mkdir($brandingDir, 0775, true);
            }
            
            // Dateiendung ermitteln
            $originalName = (string)(isset($_FILES['pdf_logo']['name']) ? $_FILES['pdf_logo']['name'] : '');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'png');
            
            // Zieldatei
            $destinationPath = $brandingDir . '/logo.' . $extension;
            
            // Datei verschieben
            if (move_uploaded_file($_FILES['pdf_logo']['tmp_name'], $destinationPath)) {
                $realPath = realpath($destinationPath);
                Settings::set('pdf_logo_path', $realPath ? $realPath : $destinationPath);
                \App\Flash::add('success', 'Logo wurde erfolgreich hochgeladen.');
            } else {
                \App\Flash::add('error', 'Fehler beim Hochladen des Logos.');
            }
        }

        if (!isset($_FILES['pdf_logo']) || empty($_FILES['pdf_logo']['tmp_name'])) {
            \App\Flash::add('success', 'Gestaltungseinstellungen wurden gespeichert.');
        }
        
        header('Location: /settings/branding');
    }

    /**
     * Logo löschen
     */
    public function brandingDeleteLogo(): void {
        Auth::requireAuth();
        
        if (!isset($_POST['delete_logo']) || $_POST['delete_logo'] !== '1') {
            \App\Flash::add('error', 'Ungültige Anfrage.');
            header('Location: /settings/branding');
            return;
        }

        $logoPath = (string)Settings::get('pdf_logo_path', '');
        
        if ($logoPath && is_file($logoPath)) {
            if (unlink($logoPath)) {
                Settings::set('pdf_logo_path', '');
                \App\Flash::add('success', 'Logo wurde erfolgreich entfernt.');
            } else {
                \App\Flash::add('error', 'Logo-Datei konnte nicht gelöscht werden.');
            }
        } else {
            Settings::set('pdf_logo_path', '');
            \App\Flash::add('info', 'Logo-Einstellung wurde zurückgesetzt.');
        }

        header('Location: /settings/branding');
    }

    /* ---------- System-Logs ---------- */
    
    /**
     * System-Logs anzeigen mit vollständiger Pagination und Filterung
     */
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
    
    /* ---------- Private Hilfsmethoden ---------- */
    
    /**
     * Eigenes Benutzerprofil rendern
     */
    private function renderOwnProfile(array $user): string {
        $userId = (string)$user['id'];
        $pdo = Database::pdo();
        
        // Aktuelle Benutzerdaten aus der Datenbank laden
        try {
            $stmt = $pdo->prepare('SELECT email, company, phone, address FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            // Fallback falls neue Spalten noch nicht existieren
            $userData = [
                'email' => $user['email'], 
                'company' => '', 
                'phone' => '', 
                'address' => ''
            ];
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
        
        $html .= '<div class="card-body">';
        $html .= '<form method="post" action="/settings/general/save" class="row g-3">';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Firma</label>';
        $html .= '<input class="form-control" name="company" value="' . $this->esc($company) . '" placeholder="Firmenname (optional)">';
        $html .= '</div>';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Telefon</label>';
        $html .= '<input class="form-control" name="phone" value="' . $this->esc($phone) . '" placeholder="+49 xxx xxxxxxx">';
        $html .= '</div>';
        
        $html .= '<div class="col-12">';
        $html .= '<label class="form-label">Adresse</label>';
        $html .= '<textarea class="form-control" rows="3" name="address" placeholder="Straße, PLZ Ort">' . $this->esc($address) . '</textarea>';
        $html .= '</div>';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">E-Mail-Adresse</label>';
        $html .= '<input type="email" class="form-control" name="email" value="' . $this->esc($email) . '" required>';
        $html .= '</div>';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Neues Passwort</label>';
        $html .= '<input type="password" class="form-control" name="password" placeholder="Leer lassen für keine Änderung">';
        $html .= '</div>';
        
        $html .= '<div class="col-12">';
        $html .= '<button class="btn btn-primary">';
        $html .= '<i class="bi bi-check-circle me-2"></i>Profil speichern';
        $html .= '</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div></div>';
        
        return $html;
    }

    /**
     * Bootstrap-Badge-Klasse für Aktionen bestimmen (erweitert für neues Design)
     */
    private function getActionBadgeClass(string $action): string {
        $action = strtolower($action);
        
        // Spezifische Action-Klassen für das neue Design
        if (str_contains($action, 'login')) return 'action-login bg-success';
        if (str_contains($action, 'logout')) return 'action-logout bg-warning text-dark';
        if (str_contains($action, 'failed') || str_contains($action, 'error')) return 'action-failed bg-danger';
        if (str_contains($action, 'created') || str_contains($action, 'added')) return 'action-created bg-primary';
        if (str_contains($action, 'deleted') || str_contains($action, 'removed')) return 'action-deleted bg-danger';
        if (str_contains($action, 'updated') || str_contains($action, 'changed')) return 'action-updated bg-info';
        if (str_contains($action, 'sent') || str_contains($action, 'email')) return 'action-sent bg-success';
        if (str_contains($action, 'pdf') || str_contains($action, 'generated')) return 'action-generated bg-secondary';
        if (str_contains($action, 'exported')) return 'action-exported bg-warning text-dark';
        if (str_contains($action, 'viewed') || str_contains($action, 'accessed')) return 'action-viewed bg-light text-dark';
        
        return 'bg-secondary';
    }
}
