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
            
            // Robuste Daten-Abfrage mit mehreren Fallbacks
            $queries = [
                // Vollständige Abfrage
                "SELECT 
                    COALESCE(user_email, 'system') as user_email,
                    COALESCE(user_ip, '127.0.0.1') as ip_address,
                    COALESCE(action_type, 'unknown') as action,
                    COALESCE(action_description, 'No description') as details,
                    created_at as timestamp
                FROM system_log 
                ORDER BY created_at DESC 
                LIMIT 20",
                
                // Fallback ohne COALESCE
                "SELECT 
                    user_email,
                    user_ip as ip_address,
                    action_type as action,
                    action_description as details,
                    created_at as timestamp
                FROM system_log 
                ORDER BY created_at DESC 
                LIMIT 20",
                
                // Minimaler Fallback
                "SELECT 
                    'system' as user_email,
                    '127.0.0.1' as ip_address,
                    'log' as action,
                    'System-Log Eintrag' as details,
                    created_at as timestamp
                FROM system_log 
                ORDER BY created_at DESC 
                LIMIT 20"
            ];
            
            $logs = [];
            foreach ($queries as $query) {
                try {
                    $stmt = $pdo->query($query);
                    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($logs)) {
                        break; // Erfolgreich - Schleife verlassen
                    }
                } catch (\PDOException $e) {
                    // Nächsten Query versuchen
                    continue;
                }
            }
            
            // Falls immer noch leer, Test-Eintrag hinzufügen
            if (empty($logs) && $totalCount > 0) {
                $logs = [[
                    "user_email" => "system",
                    "ip_address" => "127.0.0.1",
                    "action" => "test",
                    "details" => "System-Log Daten verfügbar, aber Anzeigeformat inkompatibel",
                    "timestamp" => date("Y-m-d H:i:s")
                ]];
            }
            
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
     * Bootstrap-Badge-Klasse für Aktionen bestimmen
     */
    private function getActionBadgeClass(string $action): string {
        $action = strtolower($action);
        
        if (str_contains($action, 'login')) return 'bg-success';
        if (str_contains($action, 'logout')) return 'bg-warning text-dark';
        if (str_contains($action, 'failed') || str_contains($action, 'error')) return 'bg-danger';
        if (str_contains($action, 'created') || str_contains($action, 'added')) return 'bg-primary';
        if (str_contains($action, 'deleted') || str_contains($action, 'removed')) return 'bg-danger';
        if (str_contains($action, 'updated') || str_contains($action, 'changed')) return 'bg-info';
        if (str_contains($action, 'sent') || str_contains($action, 'email')) return 'bg-success';
        if (str_contains($action, 'pdf') || str_contains($action, 'generated')) return 'bg-secondary';
        if (str_contains($action, 'exported')) return 'bg-warning text-dark';
        if (str_contains($action, 'viewed') || str_contains($action, 'accessed')) return 'bg-light text-dark';
        
        return 'bg-secondary';
    }
}
