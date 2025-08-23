<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth;
use App\View;
use App\Settings;
use App\Flash;
use App\Database;
use PDO;

final class SettingsController {
    public function index(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();

        // DocuSign Settings
        $ds = [
            'mode'           => Settings::get('docusign.mode', 'sandbox'),
            'account_id'     => Settings::get('docusign.account_id', ''),
            'base_url'       => Settings::get('docusign.base_url', 'https://demo.docusign.net'),
            'client_id'      => Settings::get('docusign.client_id', ''),
            'client_secret'  => Settings::get('docusign.client_secret', ''),
            'redirect_uri'   => Settings::get('docusign.redirect_uri', 'http://localhost:8080/docusign/callback'),
            'webhook_secret' => Settings::get('docusign.webhook_secret', ''),
        ];

        // SMTP Settings
        $smtp = [
            'host'      => Settings::get('smtp.host', 'mailpit'),
            'port'      => Settings::get('smtp.port', '1025'),
            'secure'    => Settings::get('smtp.secure', ''), // '', 'tls', 'ssl'
            'user'      => Settings::get('smtp.user', ''),
            'pass'      => Settings::get('smtp.pass', ''),
            'from_addr' => Settings::get('smtp.from_addr', 'app@example.com'),
            'from_name' => Settings::get('smtp.from_name', 'Wohnungsübergabe'),
        ];

        // aktuelle Legal-Text Versionen
        $lt = [];
        foreach (['datenschutz','entsorgung','marketing'] as $name) {
            $st = $pdo->prepare("SELECT name, version, title, content FROM legal_texts WHERE name=? ORDER BY version DESC LIMIT 1");
            $st->execute([$name]);
            $lt[$name] = $st->fetch(PDO::FETCH_ASSOC) ?: ['name'=>$name,'version'=>0,'title'=>'','content'=>''];
        }

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

          <!-- SMTP -->
          <div class="col-md-6">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <div class="fw-bold mb-2">SMTP</div>
                <form method="post" action="/settings/smtp-save" class="row g-3">
                  <div class="col-md-6"><label class="form-label">Host</label><input name="host" class="form-control" value="<?= htmlspecialchars((string)$smtp['host']) ?>"></div>
                  <div class="col-md-2"><label class="form-label">Port</label><input name="port" class="form-control" value="<?= htmlspecialchars((string)$smtp['port']) ?>"></div>
                  <div class="col-md-4"><label class="form-label">Sicherheit</label>
                    <select name="secure" class="form-select">
                      <option value=""<?= $smtp['secure']===''?' selected':''; ?>>Keine</option>
                      <option value="tls"<?= $smtp['secure']==='tls'?' selected':''; ?>>TLS</option>
                      <option value="ssl"<?= $smtp['secure']==='ssl'?' selected':''; ?>>SSL</option>
                    </select>
                  </div>
                  <div class="col-md-6"><label class="form-label">User</label><input name="user" class="form-control" value="<?= htmlspecialchars((string)$smtp['user']) ?>"></div>
                  <div class="col-md-6"><label class="form-label">Passwort</label><input type="password" name="pass" class="form-control" value="<?= htmlspecialchars((string)$smtp['pass']) ?>"></div>
                  <div class="col-md-6"><label class="form-label">Absender‑Adresse</label><input name="from_addr" class="form-control" value="<?= htmlspecialchars((string)$smtp['from_addr']) ?>"></div>
                  <div class="col-md-6"><label class="form-label">Absender‑Name</label><input name="from_name" class="form-control" value="<?= htmlspecialchars((string)$smtp['from_name']) ?>"></div>
                  <div class="col-12 text-end"><button class="btn btn-primary">Speichern</button></div>
                </form>
              </div>
            </div>
          </div>

          <!-- DocuSign -->
          <div class="col-md-6">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <div class="fw-bold mb-2">DocuSign</div>
                <form method="post" action="/settings/docusign-save" class="row g-3">
                  <div class="col-md-6"><label class="form-label">Modus</label>
                    <select name="mode" class="form-select">
                      <option value="sandbox"<?= $ds['mode']==='sandbox'?' selected':''; ?>>Sandbox</option>
                      <option value="production"<?= $ds['mode']==='production'?' selected':''; ?>>Production</option>
                    </select>
                  </div>
                  <div class="col-md-6"><label class="form-label">Account-ID</label><input name="account_id" class="form-control" value="<?= htmlspecialchars((string)$ds['account_id']) ?>"></div>
                  <div class="col-md-12"><label class="form-label">Base-URL</label><input name="base_url" class="form-control" value="<?= htmlspecialchars((string)$ds['base_url']) ?>"></div>
                  <div class="col-md-6"><label class="form-label">Client-ID</label><input name="client_id" class="form-control" value="<?= htmlspecialchars((string)$ds['client_id']) ?>"></div>
                  <div class="col-md-6"><label class="form-label">Client-Secret</label><input name="client_secret" type="password" class="form-control" value="<?= htmlspecialchars((string)$ds['client_secret']) ?>"></div>
                  <div class="col-md-12"><label class="form-label">Redirect-URI</label><input name="redirect_uri" class="form-control" value="<?= htmlspecialchars((string)$ds['redirect_uri']) ?>"></div>
                  <div class="col-md-12"><label class="form-label">Webhook-Secret</label><input name="webhook_secret" class="form-control" value="<?= htmlspecialchars((string)$ds['webhook_secret']) ?>"></div>
                  <div class="col-12 text-end"><button class="btn btn-primary">Speichern</button></div>
                </form>
              </div>
            </div>
          </div>

          <!-- Rechtstexte -->
          <div class="col-md-12">
            <div class="card h-100 shadow-sm mt-3">
              <div class="card-body">
                <div class="fw-bold mb-3">Rechtstexte (Versionierung)</div>
                <form method="post" action="/settings/legal-save" class="row g-3">
                  <?php foreach (['datenschutz'=>'Datenschutz','entsorgung'=>'Entsorgungs-Einverständnis','marketing'=>'E-Mail-Marketing'] as $key=>$label): 
                      $cur = $lt[$key]; ?>
                    <div class="col-12">
                      <label class="form-label"><?= $label ?> (aktuelle Version: <?= (int)$cur['version'] ?>)</label>
                      <input type="text" class="form-control mb-2" name="legal[<?= $key ?>][title]" value="<?= htmlspecialchars((string)$cur['title']) ?>" placeholder="Titel">
                      <textarea class="form-control" rows="6" name="legal[<?= $key ?>][content]" placeholder="HTML/Text ..."><?= htmlspecialchars((string)$cur['content']) ?></textarea>
                    </div>
                  <?php endforeach; ?>
                  <div class="col-12 text-end">
                    <button class="btn btn-primary">Neue Versionen speichern</button>
                  </div>
                </form>
                <div class="small text-muted">Speichern erzeugt je Text eine neue Version (version+1), sofern Inhalt/Titel geändert wurden.</div>
              </div>
            </div>
          </div>
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

    public function saveLegal(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        foreach ((array)($_POST['legal'] ?? []) as $name => $vals) {
            $name = in_array($name, ['datenschutz','entsorgung','marketing'], true) ? $name : null;
            if (!$name) continue;
            $title = trim((string)($vals['title'] ?? ''));
            $content = (string)($vals['content'] ?? '');
            $st = $pdo->prepare("SELECT version, title, content FROM legal_texts WHERE name=? ORDER BY version DESC LIMIT 1");
            $st->execute([$name]); $cur = $st->fetch(PDO::FETCH_ASSOC);
            $curVer = $cur ? (int)$cur['version'] : 0;
            $changed = !$cur || $cur['title'] !== $title || $cur['content'] !== $content;
            if ($changed) {
                $ins = $pdo->prepare("INSERT INTO legal_texts (id,name,version,title,content,valid_from,created_at) VALUES (UUID(),?,?,?,?,?,?)");
                $ins->execute([$name, $curVer+1, $title, $content, $now, $now]);
            }
        }
        Flash::add('success','Rechtstexte versioniert gespeichert.');
        header('Location: /settings'); exit;
    }

    public function saveSmtp(): void
    {
        Auth::requireAuth();
        \App\Settings::setMany([
            'smtp.host'      => trim((string)($_POST['host'] ?? '')),
            'smtp.port'      => trim((string)($_POST['port'] ?? '')),
            'smtp.secure'    => trim((string)($_POST['secure'] ?? '')),
            'smtp.user'      => trim((string)($_POST['user'] ?? '')),
            'smtp.pass'      => trim((string)($_POST['pass'] ?? '')),
            'smtp.from_addr' => trim((string)($_POST['from_addr'] ?? '')),
            'smtp.from_name' => trim((string)($_POST['from_name'] ?? '')),
        ]);
        Flash::add('success','SMTP-Einstellungen gespeichert.');
        header('Location: /settings'); exit;
    }
}
