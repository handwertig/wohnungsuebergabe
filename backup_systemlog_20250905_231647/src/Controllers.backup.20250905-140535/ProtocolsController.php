<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\View;
use App\Settings;
use PDO;

final class ProtocolsController
{
    /** Übersicht: Suche/Filter + gruppierte Anzeige (Haus → Einheit → Protokolle) */
    public function index(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();

        // Filter
        $q     = trim((string)($_GET['q'] ?? ''));
        $type  = (string)($_GET['type'] ?? ''); // einzug|auszug|zwischen|''
        $from  = (string)($_GET['from'] ?? ''); // YYYY-MM-DD
        $to    = (string)($_GET['to'] ?? '');   // YYYY-MM-DD

        $where = ["p.deleted_at IS NULL"];
        $args  = [];

        if ($q !== '') {
            $where[] = "(o.city LIKE ? OR o.street LIKE ? OR o.house_no LIKE ? OR u.label LIKE ? OR p.tenant_name LIKE ?)";
            $like = '%'.$q.'%'; array_push($args,$like,$like,$like,$like,$like);
        }
        if ($type !== '' && in_array($type, ['einzug','auszug','zwischen'], true)) {
            $where[] = "p.type = ?"; $args[] = $type;
        }
        if ($from !== '') { $where[] = "DATE(p.created_at) >= ?"; $args[] = $from; }
        if ($to   !== '') { $where[] = "DATE(p.created_at) <= ?"; $args[] = $to;   }

        $sql = "
          SELECT p.id,p.type,p.tenant_name,p.created_at,p.unit_id,
                 u.label AS unit_label, o.city,o.street,o.house_no
          FROM protocols p
          JOIN units u   ON u.id=p.unit_id
          JOIN objects o ON o.id=u.object_id
          WHERE ".implode(" AND ", $where)."
          ORDER BY o.city,o.street,o.house_no,u.label,p.created_at DESC
        ";
        $st = $pdo->prepare($sql); $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Gruppieren: Haus → Einheit → Protokolle
        $grp = [];
        foreach ($rows as $r) {
            $hk = $r['city'].'|'.$r['street'].'|'.$r['house_no'];
            $uk = $r['unit_label'];
            if (!isset($grp[$hk])) $grp[$hk] = ['title'=>$r['city'].', '.$r['street'].' '.$r['house_no'], 'units'=>[]];
            if (!isset($grp[$hk]['units'][$uk])) $grp[$hk]['units'][$uk] = ['title'=>$r['unit_label'],'items'=>[]];
            $grp[$hk]['units'][$uk]['items'][] = $r;
        }

        $h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $badge = function(string $t): string {
            $map = ['einzug'=>['success','Einzugsprotokoll'],'auszug'=>['danger','Auszugsprotokoll'],'zwischen'=>['warning','Zwischenprotokoll']];
            [$cls,$lbl] = $map[$t] ?? ['secondary',$t];
            return '<span class="badge bg-'.$cls.'">'.$lbl.'</span>';
        };

        ob_start(); ?>
        <h1 class="h4 mb-3">Protokolle</h1>

        <form class="row g-2 mb-3" method="get" action="/protocols">
          <div class="col-md-5">
            <input class="form-control" type="search" name="q" value="<?= $h($q) ?>" placeholder="Suche: Ort, Straße, Nr., Einheit, Mieter …">
          </div>
          <div class="col-md-3">
            <select class="form-select" name="type">
              <option value="">— Art (alle) —</option>
              <option value="einzug"   <?= $type==='einzug'?'selected':'' ?>>Einzugsprotokoll</option>
              <option value="auszug"   <?= $type==='auszug'?'selected':'' ?>>Auszugsprotokoll</option>
              <option value="zwischen" <?= $type==='zwischen'?'selected':'' ?>>Zwischenprotokoll</option>
            </select>
          </div>
          <div class="col-md-2"><input class="form-control" type="date" name="from" value="<?= $h($from) ?>" placeholder="ab"></div>
          <div class="col-md-2"><input class="form-control" type="date" name="to"   value="<?= $h($to)   ?>" placeholder="bis"></div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">Filtern</button>
            <a class="btn btn-outline-secondary" href="/protocols">Zurücksetzen</a>
            <a class="btn btn-success ms-auto" href="/protocols/wizard/start"><i class="bi bi-plus-circle"></i> Neues Protokoll</a>
          </div>
        </form>

        <div class="accordion" id="acc-houses">
          <?php $hid=0; foreach ($grp as $house): $hid++; ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="h<?= $hid ?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c<?= $hid ?>">
                <?= $h($house['title']) ?>
              </button>
            </h2>
            <div id="c<?= $hid ?>" class="accordion-collapse collapse" data-bs-parent="#acc-houses">
              <div class="accordion-body">
                <div class="accordion" id="acc-units-<?= $hid ?>">
                  <?php $uid=0; foreach ($house['units'] as $unit): $uid++; ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="u<?= $hid.'-'.$uid ?>">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cu<?= $hid.'-'.$uid ?>">
                        Einheit <?= $h($unit['title']) ?>
                      </button>
                    </h2>
                    <div id="cu<?= $hid.'-'.$uid ?>" class="accordion-collapse collapse" data-bs-parent="#acc-units-<?= $hid ?>">
                      <div class="accordion-body">
                        <div class="table-responsive">
                          <table class="table table-sm align-middle mb-0">
                            <thead><tr><th style="width:160px">Erstellt</th><th style="width:200px">Art</th><th>Mieter</th><th style="width:120px"></th></tr></thead>
                            <tbody>
                              <?php foreach ($unit['items'] as $v): ?>
                              <tr>
                                <td><?= $h((string)$v['created_at']) ?></td>
                                <td><?= $badge((string)$v['type']) ?></td>
                                <td><?= $h((string)$v['tenant_name']) ?></td>
                                <td class="text-end"><a class="btn btn-sm btn-primary" href="/protocols/edit?id=<?= $h((string)$v['id']) ?>">Öffnen</a></td>
                              </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; if (!$house['units']) echo '<div class="text-muted small">Keine Einträge.</div>'; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; if (!$grp) echo '<div class="text-muted">Keine Einträge.</div>'; ?>
        </div>
        <?php View::render('Protokolle', ob_get_clean());
    }

    /** Editor – (gekürzt) enthält Kopf/Adresse/WE Prefill, Tabs, Versand/Logs */
    public function form(): void
    {
        Auth::requireAuth();
        $pdo=Database::pdo();
        $id=(string)($_GET['id'] ?? ''); if($id===''){ \App\Flash::add('error','ID fehlt.'); header('Location:/protocols'); return; }

        $st=$pdo->prepare("SELECT p.*, u.label AS unit_label, o.city,o.street,o.house_no
                           FROM protocols p JOIN units u ON u.id=p.unit_id JOIN objects o ON o.id=u.object_id
                           WHERE p.id=? LIMIT 1");
        $st->execute([$id]); $p=$st->fetch(PDO::FETCH_ASSOC);
        if(!$p){ \App\Flash::add('error','Protokoll nicht gefunden.'); header('Location:/protocols'); return; }

        $payload = json_decode((string)($p['payload'] ?? '{}'), true) ?: [];
        $addr    = (array)($payload['address'] ?? []);
        $rooms   = (array)($payload['rooms'] ?? []);
        $meters  = (array)($payload['meters'] ?? []);
        $keys    = (array)($payload['keys'] ?? []);
        $meta    = (array)($payload['meta'] ?? []);
        $ts      = (string)($payload['timestamp'] ?? '');

        $owners   = $pdo->query("SELECT id,name,company FROM owners WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $managers = $pdo->query("SELECT id,name,company FROM managers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $h=fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $sel=fn($v,$cur)=>((string)$v===(string)$cur)?' selected':'';

        $title = $p['city'].', '.$p['street'].' '.$p['house_no'].' – '.$p['unit_label'];

        ob_start(); ?>
        <h1 class="h5 mb-2">Protokoll bearbeiten</h1>
        <div class="text-muted mb-3"><?= $h($title) ?></div>

        <form method="post" action="/protocols/save" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?= $h($p['id']) ?>">

          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-kopf" type="button">Kopf</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-raeume" type="button">Räume</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-zaehler" type="button">Zähler</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-schluessel" type="button">Schlüssel & Meta</button></li>
          </ul>

          <div class="tab-content border-start border-end border-bottom p-3">
            <div class="tab-pane fade show active" id="tab-kopf">
              <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Art</label>
                  <select class="form-select" name="type">
                    <option value="einzug"   <?= $sel('einzug',  (string)$p['type']) ?>>Einzugsprotokoll</option>
                    <option value="auszug"   <?= $sel('auszug',  (string)$p['type']) ?>>Auszugsprotokoll</option>
                    <option value="zwischen" <?= $sel('zwischen',(string)$p['type']) ?>>Zwischenabnahme</option>
                  </select>
                </div>
                <div class="col-md-6"><label class="form-label">Mietername</label><input class="form-control" name="tenant_name" value="<?= $h((string)($p['tenant_name'] ?? '')) ?>"></div>
                <div class="col-md-2"><label class="form-label">Zeitstempel</label><input class="form-control" type="datetime-local" name="timestamp" value="<?= $h($ts) ?>"></div>

                <hr class="mt-3"><h6>Adresse & Wohneinheit</h6>
                <div class="col-md-5"><label class="form-label">Ort</label><input class="form-control" name="address[city]" value="<?= $h((string)($addr['city'] ?? (string)$p['city'])) ?>"></div>
                <div class="col-md-2"><label class="form-label">PLZ</label><input class="form-control" name="address[postal_code]" value="<?= $h((string)($addr['postal_code'] ?? '')) ?>"></div>
                <div class="col-md-5"><label class="form-label">Straße</label><input class="form-control" name="address[street]" value="<?= $h((string)($addr['street'] ?? (string)$p['street'])) ?>"></div>
                <div class="col-md-3"><label class="form-label">Haus‑Nr.</label><input class="form-control" name="address[house_no]" value="<?= $h((string)($addr['house_no'] ?? (string)$p['house_no'])) ?>"></div>
                <div class="col-md-4"><label class="form-label">WE‑Bezeichnung</label><input class="form-control" name="address[unit_label]" value="<?= $h((string)($addr['unit_label'] ?? (string)$p['unit_label'])) ?>"></div>
                <div class="col-md-3"><label class="form-label">Etage</label><input class="form-control" name="address[floor]" value="<?= $h((string)($addr['floor'] ?? '')) ?>"></div>

                <div class="col-md-6"><label class="form-label">Eigentümer</label><select class="form-select" name="owner_id"><option value="">— bitte wählen —</option>
                  <?php foreach ($owners as $o): $label=$o['name'].(!empty($o['company'])?' ('.$o['company'].')':''); ?>
                    <option value="<?= $h($o['id']) ?>"<?= $sel($o['id'], (string)($p['owner_id'] ?? '')) ?>><?= $h($label) ?></option>
                  <?php endforeach; ?>
                </select></div>
                <div class="col-md-6"><label class="form-label">Hausverwaltung</label><select class="form-select" name="manager_id"><option value="">— bitte wählen —</option>
                  <?php foreach ($managers as $m): $label=$m['name'].(!empty($m['company'])?' ('.$m['company'].')':''); ?>
                    <option value="<?= $h($m['id']) ?>"<?= $sel($m['id'], (string)($p['manager_id'] ?? '')) ?>><?= $h($label) ?></option>
                  <?php endforeach; ?>
                </select></div>
              </div>
            </div>

            <!-- Räume -->
            <div class="tab-pane fade" id="tab-raeume">
              <?php if (!$rooms) $rooms=[['name'=>'','state'=>'','smell'=>'','accepted'=>false,'wmz_no'=>'','wmz_val'=>'']]; $i=0; ?>
              <?php foreach ($rooms as $r): $i++; ?>
              <div class="card mb-3"><div class="card-body"><div class="row g-3">
                <div class="col-md-4"><label class="form-label">Raumname</label><input class="form-control" name="rooms[<?= $i ?>][name]" value="<?= $h((string)($r['name'] ?? '')) ?>" list="room-presets"></div>
                <div class="col-md-4"><label class="form-label">Geruch</label><input class="form-control" name="rooms[<?= $i ?>][smell]" value="<?= $h((string)($r['smell'] ?? '')) ?>"></div>
                <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="rooms[<?= $i ?>][accepted]" <?= !empty($r['accepted'])?'checked':'' ?>> <label class="form-check-label">Abnahme erfolgt</label></div></div>
                <div class="col-12"><label class="form-label">IST‑Zustand</label><textarea class="form-control" rows="3" name="rooms[<?= $i ?>][state]"><?= $h((string)($r['state'] ?? '')) ?></textarea></div>
                <div class="col-md-6"><label class="form-label">WMZ Nummer</label><input class="form-control" name="rooms[<?= $i ?>][wmz_no]" value="<?= $h((string)($r['wmz_no'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">WMZ Stand</label><input class="form-control" name="rooms[<?= $i ?>][wmz_val]" value="<?= $h((string)($r['wmz_val'] ?? '')) ?>"></div>
                <div class="col-12"><label class="form-label">Fotos (JPG/PNG)</label><input class="form-control" type="file" name="room_photos[<?= $i ?>][]" multiple accept="image/*"></div>
              </div></div></div>
              <?php endforeach; ?>
            </div>

            <!-- Zähler -->
            <div class="tab-pane fade" id="tab-zaehler">
              <?php $labels=['strom_we'=>'Strom (Wohneinheit)','strom_allg'=>'Strom (Haus allgemein)','gas_we'=>'Gas (Wohneinheit)','gas_allg'=>'Gas (Haus allgemein)','wasser_kueche_kalt'=>'Wasser Küche (kalt)','wasser_kueche_warm'=>'Wasser Küche (warm)','wasser_bad_kalt'=>'Wasser Bad (kalt)','wasser_bad_warm'=>'Wasser Bad (warm)','wasser_wm'=>'Wasser Waschmaschine (blau)']; ?>
              <?php foreach ($labels as $k=>$lbl): $m=$meters[$k] ?? ['no'=>'','val'=>'']; ?>
              <div class="row g-3 align-items-end mb-2">
                <div class="col-md-5"><label class="form-label"><?= $h($lbl) ?> – Nummer</label><input class="form-control" name="meters[<?= $k ?>][no]" value="<?= $h((string)$m['no']) ?>"></div>
                <div class="col-md-5"><label class="form-label"><?= $h($lbl) ?> – Stand</label><input class="form-control" name="meters[<?= $k ?>][val]" value="<?= $h((string)$m['val']) ?>"></div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Schlüssel & Meta -->
            <div class="tab-pane fade" id="tab-schluessel">
              <h6>Schlüssel</h6>
              <?php if (!$keys) $keys=[['label'=>'','qty'=>0,'no'=>'']]; $i=0; ?>
              <?php foreach ($keys as $k): $i++; ?>
              <div class="row g-2 align-items-end mb-2">
                <div class="col-md-5"><label class="form-label">Bezeichnung</label><input class="form-control" name="keys[<?= $i ?>][label]" value="<?= $h((string)($k['label'] ?? '')) ?>" list="key-presets"></div>
                <div class="col-md-3"><label class="form-label">Anzahl</label><input type="number" min="0" class="form-control" name="keys[<?= $i ?>][qty]" value="<?= $h((string)($k['qty'] ?? '0')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Schlüssel‑Nr.</label><input class="form-control" name="keys[<?= $i ?>][no]" value="<?= $h((string)($k['no'] ?? '')) ?>"></div>
              </div>
              <?php endforeach; ?>

              <?php $bank=(array)($meta['bank'] ?? []); $tc=(array)($meta['tenant_contact'] ?? []); $na=(array)($meta['tenant_new_addr'] ?? []); $cons=(array)($meta['consents'] ?? []); $third=(string)($meta['third_attendee'] ?? ''); ?>
              <hr><h6>Weitere Angaben</h6><div class="row g-3">
                <div class="col-md-4"><label class="form-label">Bank</label><input class="form-control" name="meta[bank][bank]" value="<?= $h((string)$bank['bank']) ?>"></div>
                <div class="col-md-4"><label class="form-label">IBAN</label><input class="form-control" name="meta[bank][iban]" value="<?= $h((string)$bank['iban']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Kontoinhaber</label><input class="form-control" name="meta[bank][holder]" value="<?= $h((string)$bank['holder']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Mieter E‑Mail</label><input type="email" class="form-control" name="meta[tenant_contact][email]" value="<?= $h((string)$tc['email']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Mieter Telefon</label><input class="form-control" name="meta[tenant_contact][phone]" value="<?= $h((string)$tc['phone']) ?>"></div>
                <div class="col-12"><label class="form-label">Neue Meldeadresse</label></div>
                <div class="col-md-6"><input class="form-control" placeholder="Straße" name="meta[tenant_new_addr][street]" value="<?= $h((string)$na['street']) ?>"></div>
                <div class="col-md-2"><input class="form-control" placeholder="Haus‑Nr." name="meta[tenant_new_addr][house_no]" value="<?= $h((string)$na['house_no']) ?>"></div>
                <div class="col-md-2"><input class="form-control" placeholder="PLZ" name="meta[tenant_new_addr][postal_code]" value="<?= $h((string)$na['postal_code']) ?>"></div>
                <div class="col-md-2"><input class="form-control" placeholder="Ort" name="meta[tenant_new_addr][city]" value="<?= $h((string)$na['city']) ?>"></div>

                <div class="col-12"><label class="form-label">Dritte anwesende Person (optional)</label><input class="form-control" name="meta[third_attendee]" value="<?= $h($third) ?>"></div>

                <div class="col-12"><label class="form-label">Einwilligungen</label></div>
                <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[consents][privacy]"  <?= !empty($cons['privacy'])?'checked':'' ?>> <label class="form-check-label">Datenschutzerklärung akzeptiert</label></div></div>
                <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[consents][marketing]"<?= !empty($cons['marketing'])?'checked':'' ?>> <label class="form-check-label">E‑Mail‑Marketing</label></div></div>
                <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[consents][disposal]" <?= !empty($cons['disposal'])?'checked':'' ?>> <label class="form-check-label">Entsorgung zurückgelassener Gegenstände</label></div></div>
              </div>
            </div>
          </div>

          <div class="mt-3 d-flex justify-content-between">
            <a class="btn btn-ghost" href="/protocols"><i class="bi bi-x-lg"></i> Zurück</a>
            <button class="btn btn-primary">Speichern (neue Version)</button>
          </div>
        </form>

        <hr>
        <?php
        // Versand + Log
        $ev = $pdo->prepare("SELECT type, message, created_at FROM protocol_events WHERE protocol_id=? ORDER BY created_at DESC LIMIT 50");
        $ev->execute([$p["id"]]); $events = $ev->fetchAll(PDO::FETCH_ASSOC);
        $ml = $pdo->prepare("SELECT recipient_type, to_email, subject, status, sent_at, created_at FROM email_log WHERE protocol_id=? ORDER BY created_at DESC LIMIT 50");
        $ml->execute([$p["id"]]); $mails = $ml->fetchAll(PDO::FETCH_ASSOC);
        $pidEsc = $h($p["id"]); ?>

        <h2 class="h6" id="send">Versand</h2>
        <div class="d-flex gap-2 mb-3">
          <a class="btn btn-outline-primary" href="/protocols/send?protocol_id=<?= $pidEsc ?>&to=owner">PDF an Eigentümer senden</a>
          <a class="btn btn-outline-primary" href="/protocols/send?protocol_id=<?= $pidEsc ?>&to=manager">PDF an Hausverwaltung senden</a>
          <a class="btn btn-outline-primary" href="/protocols/send?protocol_id=<?= $pidEsc ?>&to=tenant">PDF an Mieter senden</a>
          <a class="btn btn-outline-secondary" href="/protocols/pdf?protocol_id=<?= $pidEsc ?>&version=latest" target="_blank">PDF ansehen</a>
        </div>

        <h2 class="h6">Status</h2>
        <div class="row g-3">
          <div class="col-md-6"><div class="card"><div class="card-body"><div class="fw-bold mb-2">Ereignisse</div>
            <?php if ($events): ?><ul class="list-group">
              <?php foreach ($events as $e): ?><li class="list-group-item d-flex justify-content-between"><span><?= $h($e["type"]) ?><?= $e["message"]?": ".$h((string)$e["message"]):"" ?></span><span class="text-muted small"><?= $h((string)$e["created_at"]) ?></span></li><?php endforeach; ?>
            </ul><?php else: ?><div class="text-muted">Noch keine Ereignisse.</div><?php endif; ?>
          </div></div></div>

          <div class="col-md-6"><div class="card"><div class="card-body"><div class="fw-bold mb-2">E‑Mail‑Versand</div>
            <?php if ($mails): ?>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Empfänger</th><th>E‑Mail</th><th>Betreff</th><th>Status</th><th>Gesendet</th></tr></thead><tbody>
              <?php foreach ($mails as $m): ?><tr><td><?= $h((string)$m["recipient_type"]) ?></td><td><?= $h((string)$m["to_email"]) ?></td><td><?= $h((string)$m["subject"]) ?></td><td><?= $h((string)$m["status"]) ?></td><td><?= $h((string)($m["sent_at"] ?? $m["created_at"])) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php else: ?><div class="text-muted">Noch kein Versand.</div><?php endif; ?>
          </div></div></div>
        </div>

        <script src="/assets/presets.js"></script>
        <?php View::render('Protokoll – Bearbeiten', ob_get_clean());
    }

    // Platzhalter – damit Routing nicht bricht (optional an dein Projekt anpassen)
    public function save(): void { \App\Auth::requireAuth(); \App\Flash::add('info','Speichern (Stub) – bitte projektweit anpassen.'); header('Location:/protocols'); }
    public function delete(): void { \App\Auth::requireAuth(); \App\Flash::add('info','Löschen (Stub)'); header('Location:/protocols'); }
    public function export(): void { \App\Auth::requireAuth(); \App\Flash::add('info','Export (Stub)'); header('Location:/protocols'); }

    /** PDF anzeigen (bevorzugt signiert) */
    public function pdf(): void
    {
        Auth::requireAuth();
        $pdo=Database::pdo();
        $pid=(string)($_GET['protocol_id'] ?? ''); $ver=(string)($_GET['version'] ?? 'latest');
        if ($pid===''){ http_response_code(400); echo 'protocol_id fehlt'; return; }
        if ($ver==='latest'){ $st=$pdo->prepare("SELECT COALESCE(MAX(version_no),1) FROM protocol_versions WHERE protocol_id=?"); $st->execute([$pid]); $ver=(string)((int)$st->fetchColumn() ?: 1); }
        $v=(int)$ver; if($v<=0) $v=1;
        try { $path=\App\PdfService::getOrRender($pid,$v,true);
              if(!$path || !is_file($path)){ http_response_code(404); echo 'PDF nicht gefunden'; return; }
              header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="protokoll-v'.$v.'.pdf"'); header('Content-Length: '.filesize($path)); readfile($path);
        } catch (\Throwable $e){ http_response_code(500); echo 'PDF-Fehler: '.$e->getMessage(); }
    }

    /** PDF per Mail versenden (Flash im Editor) */
    public function send(): void
    {
        Auth::requireAuth();
        $pdo=Database::pdo();
        $pid=(string)($_GET['protocol_id'] ?? ''); $to=(string)($_GET['to'] ?? 'owner');
        if ($pid===''){ http_response_code(400); header('Content-Type:application/json'); echo json_encode(['error'=>'protocol_id fehlt']); return; }

        $st=$pdo->prepare("SELECT p.*,u.label AS unit_label,o.city,o.street,o.house_no FROM protocols p JOIN units u ON u.id=p.unit_id JOIN objects o ON o.id=u.object_id WHERE p.id=? LIMIT 1");
        $st->execute([$pid]); $p=$st->fetch(PDO::FETCH_ASSOC);
        if(!$p){ http_response_code(404); header('Content-Type:application/json'); echo json_encode(['error'=>'Protokoll nicht gefunden']); return; }
        $payload=json_decode((string)$p['payload'],true) ?: []; $addr=(array)($payload['address']??[]);

        // Empfänger
        $email=''; $name='';
        if ($to==='tenant'){ $email=(string)($payload['meta']['tenant_contact']['email'] ?? ''); $name=(string)($p['tenant_name'] ?? ''); }
        elseif ($to==='manager'){ $id=(string)($p['manager_id'] ?? ''); if($id!==''){ $q=$pdo->prepare("SELECT email,name FROM managers WHERE id=?"); $q->execute([$id]); if($r=$q->fetch(PDO::FETCH_ASSOC)){ $email=(string)($r['email']??''); $name=(string)($r['name']??''); } } }
        else { $id=(string)($p['owner_id'] ?? ''); if($id!==''){ $q=$pdo->prepare("SELECT email,name FROM owners WHERE id=?"); $q->execute([$id]); if($r=$q->fetch(PDO::FETCH_ASSOC)){ $email=(string)($r['email']??''); $name=(string)($r['name']??''); } } }

        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $redirect = (strpos($referer, '/protocols/edit') !== false);

        if ($email===''){
            if ($redirect){ \App\Flash::add('error','Empfängeradresse fehlt – bitte im Kopf/Kontakt ergänzen.'); header('Location: '.$referer); }
            else { http_response_code(400); header('Content-Type:application/json'); echo json_encode(['error'=>'Empfängeradresse fehlt']); }
            return;
        }

        // Version & PDF
        $st=$pdo->prepare("SELECT COALESCE(MAX(version_no),1) FROM protocol_versions WHERE protocol_id=?"); $st->execute([$pid]); $versionNo=(int)$st->fetchColumn(); if($versionNo<=0) $versionNo=1;
        try { $pdfPath=\App\PdfService::getOrRender($pid,$versionNo,true); } catch (\Throwable $e){
            if ($redirect){ \App\Flash::add('error','PDF-Fehler: '.$e->getMessage()); header('Location: '.$referer); } else { http_response_code(500); header('Content-Type:application/json'); echo json_encode(['error'=>'PDF-Fehler: '.$e->getMessage()]); } return;
        }

        // SMTP (Mailpit default)
        $host=(string)Settings::get('smtp_host',''); if($host==='') $host='mailpit';
        $port=(int)Settings::get('smtp_port','1025'); $user=(string)Settings::get('smtp_user',''); $pass=(string)Settings::get('smtp_pass',''); $sec=(string)Settings::get('smtp_secure','');
        $fromName=(string)Settings::get('smtp_from_name','Wohnungsübergabe'); $fromMail=(string)Settings::get('smtp_from_email','no-reply@example.com');

        $betreff='Übergabeprotokoll – '.(string)($addr['street']??'').' '.(string)($addr['house_no']??'').' – '.(string)($addr['city']??'').' (v'.$versionNo.')';
        $text="Guten Tag\n\nim Anhang erhalten Sie das Übergabeprotokoll (v$versionNo).\nObjekt: ".(string)($addr['street']??'').' '.(string)($addr['house_no']??'').', '.(string)($addr['city']??'')."\n\nMit freundlichen Grüßen\nWohnungsübergabe";

        $ok=false; $err='';
        try {
            $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP(); $mail->Host=$host; $mail->Port=$port;
            if ($sec!=='') $mail->SMTPSecure=$sec;
            if ($user!==''){ $mail->SMTPAuth=true; $mail->Username=$user; $mail->Password=$pass; }
            $mail->setFrom($fromMail,$fromName); $mail->addAddress($email,$name);
            $mail->Subject=$betreff; $mail->Body=$text;
            $mail->addAttachment($pdfPath,'uebergabeprotokoll_v'.$versionNo.'.pdf'); $mail->send(); $ok=true;
        } catch (\Throwable $e) { $err=$e->getMessage(); $ok=false; }

        // Log (optional)
        try {
            $pdo->prepare("INSERT INTO email_log (id,protocol_id,recipient_type,to_email,subject,status,sent_at,created_at) VALUES (UUID(),?,?,?,?,?,NOW(),NOW())")
                ->execute([$pid,$to,$email,$betreff,$ok?'sent':'error: '.$err]);
            $pdo->prepare("INSERT INTO protocol_events (id,protocol_id,type,message,created_at) VALUES (UUID(),?,?,?,NOW())")
                ->execute([$pid,'mail_'.$to,$ok?'sent':'error: '.$err]);
        } catch (\Throwable $e) {}

        if ($redirect){ \App\Flash::add($ok?'success':'error', $ok?'E‑Mail versendet.':'Versand fehlgeschlagen: '.$err); header('Location: '.$referer); }
        else { header('Content-Type:application/json'); echo json_encode(['ok'=>$ok,'error'=>$ok?null:$err]); }
    }
}
