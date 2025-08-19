<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth;
use App\View;
use App\Settings;
use App\Flash;

final class SettingsController {
    public function index(): void {
        Auth::requireAuth();
        $isAdmin = \App\Auth::isAdmin();

        // Aktuelle DocuSign-Werte laden
        $ds = [
            'mode'           => Settings::get('docusign.mode', 'sandbox'), // sandbox|production
            'account_id'     => Settings::get('docusign.account_id', ''),
            'base_url'       => Settings::get('docusign.base_url', 'https://demo.docusign.net'),
            'client_id'      => Settings::get('docusign.client_id', ''),
            'client_secret'  => Settings::get('docusign.client_secret', ''),
            'redirect_uri'   => Settings::get('docusign.redirect_uri', 'http://localhost:8080'),
            'webhook_secret' => Settings::get('docusign.webhook_secret', ''),
        ];

        ob_start(); ?>
        <h1 class="h4 mb-3">Einstellungen</h1>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <div class="fw-bold mb-2">Stammdaten</div>
                <div class="d-grid gap-2">
                  <a class="btn btn-outline-secondary" href="/owners">Eigentümer</a>
                  <a class="btn btn-outline-secondary" href="/managers">Hausverwaltungen</a>
                  <a class="btn btn-outline-secondary" href="/objects">Objekte</a>
                  <a class="btn btn-outline-secondary" href="/units">Wohneinheiten</a>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <div class="fw-bold mb-2">DocuSign</div>
                <form method="post" action="/settings/docusign-save" class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Modus</label>
                    <select name="mode" class="form-select">
                      <option value="sandbox"<?= $ds['mode']==='sandbox'?' selected':''; ?>>Sandbox</option>
                      <option value="production"<?= $ds['mode']==='production'?' selected':''; ?>>Production</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Account-ID</label>
                    <input name="account_id" class="form-control" value="<?= htmlspecialchars((string)$ds['account_id']) ?>">
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Base-URL</label>
                    <input name="base_url" class="form-control" value="<?= htmlspecialchars((string)$ds['base_url']) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Client-ID</label>
                    <input name="client_id" class="form-control" value="<?= htmlspecialchars((string)$ds['client_id']) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Client-Secret</label>
                    <input name="client_secret" type="password" class="form-control" value="<?= htmlspecialchars((string)$ds['client_secret']) ?>">
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Redirect-URI</label>
                    <input name="redirect_uri" class="form-control" value="<?= htmlspecialchars((string)$ds['redirect_uri']) ?>">
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Webhook-Secret</label>
                    <input name="webhook_secret" class="form-control" value="<?= htmlspecialchars((string)$ds['webhook_secret']) ?>">
                  </div>
                  <div class="col-12 text-end">
                    <button class="btn btn-primary">Speichern</button>
                  </div>
                </form>
                <div class="small text-muted mt-2">Hinweis: Dies ist die Konfiguration für die spätere DocuSign-Anbindung. Der Versand-Button prüft bereits, ob alle Felder gesetzt sind.</div>
              </div>
            </div>
          </div>

          <?php if ($isAdmin): ?>
          <div class="col-md-6">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <div class="fw-bold mb-2">Benutzer & Rollen</div>
                <a class="btn btn-outline-secondary" href="/users">Benutzer verwalten</a>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php View::render('Einstellungen – Wohnungsübergabe', ob_get_clean());
    }

    public function saveDocusign(): void
    {
        Auth::requireAuth();
        \App\Settings::setMany([
            'docusign.mode'           => (string)($_POST['mode'] ?? 'sandbox'),
            'docusign.account_id'     => trim((string)($_POST['account_id'] ?? '')),
            'docusign.base_url'       => trim((string)($_POST['base_url'] ?? '')),
            'docusign.client_id'      => trim((string)($_POST['client_id'] ?? '')),
            'docusign.client_secret'  => trim((string)($_POST['client_secret'] ?? '')),
            'docusign.redirect_uri'   => trim((string)($_POST['redirect_uri'] ?? '')),
            'docusign.webhook_secret' => trim((string)($_POST['webhook_secret'] ?? '')),
        ]);
        Flash::add('success','DocuSign-Einstellungen gespeichert.');
        header('Location: /settings'); exit;
    }
}
