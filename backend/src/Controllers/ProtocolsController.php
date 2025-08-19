<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Flash;
use App\View;
use App\AuditLogger;
use PDO;

final class ProtocolsController
{
    // Übersicht als Accordion (Objekt -> Einheit -> Versionen) + Badges je Typ + Filter + CSV-Export
    public function index(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();

        $q    = trim((string)($_GET['q'] ?? ''));
        $type = (string)($_GET['type'] ?? '');

        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($type !== '' && in_array($type, ['einzug','auszug','zwischen'], true)) {
            $where[] = 'p.type = ?';
            $params[] = $type;
        }
        if ($q !== '') {
            $where[] = '(p.tenant_name LIKE ? OR o.city LIKE ? OR o.street LIKE ? OR o.house_no LIKE ? OR u.label LIKE ?)';
            $like = '%'.$q.'%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $whereSql = implode(' AND ', $where);

        $sql = "SELECT 
                    o.city,o.street,o.house_no,
                    u.id AS unit_id, u.label AS unit_label,
                    p.id AS protocol_id, p.type, p.tenant_name,
                    pv.version_no, pv.created_at AS version_created_at
                FROM protocols p
                JOIN units u   ON u.id = p.unit_id
                JOIN objects o ON o.id = u.object_id
                LEFT JOIN protocol_versions pv ON pv.protocol_id = p.id
                WHERE $whereSql
                ORDER BY o.city, o.street, o.house_no, u.label, pv.created_at DESC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Gruppierung: Objekt -> Einheit -> Versionen
        $tree = [];
        foreach ($rows as $r) {
            $objKey = $r['city'].'|'.$r['street'].'|'.$r['house_no'];
            if (!isset($tree[$objKey])) {
                $tree[$objKey] = ['title'=>$r['city'].', '.$r['street'].' '.$r['house_no'], 'units'=>[]];
            }
            if (!isset($tree[$objKey]['units'][$r['unit_id']])) {
                $tree[$objKey]['units'][$r['unit_id']] = ['label'=>$r['unit_label'], 'versions'=>[]];
            }
            if ($r['version_no'] !== null) {
                $tree[$objKey]['units'][$r['unit_id']]['versions'][] = [
                    'protocol_id' => $r['protocol_id'],
                    'date'        => $r['version_created_at'],
                    'type'        => $r['type'],
                    'tenant'      => $r['tenant_name'],
                    'version_no'  => (int)$r['version_no'],
                ];
            }
        }

        // Kopf + Filter
        $html = '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h1 class="h4 mb-0">Protokoll‑Übersicht</h1>';
        $qs = $_GET; $exportUrl = '/protocols/export?'.http_build_query($qs);
        $html .= '<div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="'.$exportUrl.'">CSV‑Export</a>';
        $html .= '<a class="btn btn-primary" href="/protocols/wizard/start">Neues Protokoll</a></div></div>';

        $html .= '<form class="row g-2 mb-3" method="get" action="/protocols">';
        $html .= '<div class="col-md-4"><label class="form-label">Suche (Mieter/Adresse)</label><input class="form-control" name="q" value="'.htmlspecialchars($q).'"></div>';
        $html .= '<div class="col-md-3"><label class="form-label">Typ</label><select class="form-select" name="type">';
        foreach ([''=>'— alle —','einzug'=>'einzug','auszug'=>'auszug','zwischen'=>'zwischen'] as $val=>$lbl) {
            $sel = ($val === $type) ? ' selected' : '';
            $html .= '<option value="'.htmlspecialchars($val).'"'.$sel.'>'.$lbl.'</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Filtern</button></div>';
        $html .= '</form>';

        // Accordion
        $html .= '<div class="accordion" id="acc-objects">';
        if (!$tree) { $html .= '<div class="text-muted">Keine Einträge.</div>'; }
        $i=0;
        foreach ($tree as $obj) {
            $i++; $oid="obj-$i";
            $html .= '<div class="accordion-item">';
            $html .=   '<h2 class="accordion-header" id="h-'.$oid.'"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-'.$oid.'">'.htmlspecialchars($obj['title']).'</button></h2>';
            $html .=   '<div id="c-'.$oid.'" class="accordion-collapse collapse"><div class="accordion-body">';
            // Einheiten
            $html .=   '<div class="accordion" id="acc-units-'.$oid.'">';
            $j=0;
            foreach (($obj['units'] ?? []) as $unit) {
                $j++; $uid="$oid-u-$j";
                $html .= '<div class="accordion-item">';
                $html .=   '<h2 class="accordion-header" id="h-'.$uid.'"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-'.$uid.'">'.htmlspecialchars($unit['label']).'</button></h2>';
                $html .=   '<div id="c-'.$uid.'" class="accordion-collapse collapse"><div class="accordion-body">';
                // Versionen
                if (!empty($unit['versions'])) {
                    $html .= '<div class="table-responsive"><table class="table table-sm align-middle">';
                    $html .= '<thead><tr><th>Datum</th><th>Art</th><th>Mieter</th><th class="text-end">Aktion</th></tr></thead><tbody>';
                    foreach ($unit['versions'] as $v) {
                        $badge = '<span class="badge bg-secondary">'.htmlspecialchars($v['type']).'</span>';
                        if ($v['type']==='einzug')   $badge = '<span class="badge bg-success">einzug</span>';
                        if ($v['type']==='auszug')   $badge = '<span class="badge bg-danger">auszug</span>';
                        if ($v['type']==='zwischen') $badge = '<span class="badge bg-warning text-dark">zwischen</span>';
                        $html .= '<tr>';
                        $html .=   '<td>'.htmlspecialchars($v['date']).' (v'.$v['version_no'].')</td>';
                        $html .=   '<td>'.$badge.'</td>';
                        $html .=   '<td>'.htmlspecialchars($v['tenant']).'</td>';
                        $html .=   '<td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/protocols/edit?id='.$v['protocol_id'].'">Protokoll öffnen</a></td>';
                        $html .= '</tr>';
                    }
                    $html .= '</tbody></table></div>';
                } else {
                    $html .= '<div class="text-muted">Keine Versionen vorhanden.</div>';
                }
                $html .=   '</div></div></div>'; // unit body/item
            }
            $html .=   '</div>'; // acc-units
            $html .=   '</div></div>'; // obj body/collapse
            $html .= '</div>'; // obj item
        }
        $html .= '</div>'; // acc-objects

        View::render('Protokolle – Akkordeon', $html);
    }

    // CSV-Export berücksichtigt aktuelle Filter
    public function export(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();

        $q    = trim((string)($_GET['q'] ?? ''));
        $type = (string)($_GET['type'] ?? '');

        $where = ['p.deleted_at IS NULL']; $params=[];
        if ($type !== '' && in_array($type, ['einzug','auszug','zwischen'], true)) { $where[] = 'p.type = ?'; $params[] = $type; }
        if ($q !== '') {
            $where[] = '(p.tenant_name LIKE ? OR o.city LIKE ? OR o.street LIKE ? OR o.house_no LIKE ? OR u.label LIKE ?)';
            $like = '%'.$q.'%'; array_push($params,$like,$like,$like,$like,$like);
        }
        $whereSql = implode(' AND ', $where);

        $sql = "SELECT p.id,p.type,p.tenant_name,p.created_at,o.city,o.street,o.house_no,u.label AS unit_label
                FROM protocols p JOIN units u ON u.id=p.unit_id JOIN objects o ON o.id=u.object_id
                WHERE $whereSql
                ORDER BY o.city,o.street,o.house_no,u.label,p.created_at DESC";
        $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=protokolle_export.csv');
        $out=fopen('php://output','w'); fputcsv($out,['ID','Typ','Mieter','Ort','Straße','Hausnr.','WE','Erstellt']);
        foreach($rows as $r){ fputcsv($out,[$r['id'],$r['type'],$r['tenant_name'],$r['city'],$r['street'],$r['house_no'],$r['unit_label'],$r['created_at']]); }
        fclose($out); exit;
    }

    // EDITOR mit Tabs: Kopf, Räume, Zähler, Schlüssel & Meta
    public function form(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = (string)($_GET['id'] ?? '');
        if ($id === '') { Flash::add('error','ID fehlt.'); header('Location: /protocols'); return; }

        $st = $pdo->prepare("SELECT p.*, u.label AS unit_label, o.city,o.street,o.house_no
                             FROM protocols p JOIN units u ON u.id=p.unit_id JOIN objects o ON o.id=u.object_id
                             WHERE p.id=? LIMIT 1");
        $st->execute([$id]); $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) { Flash::add('error','Protokoll nicht gefunden.'); header('Location: /protocols'); return; }

        $payload = json_decode((string)($p['payload'] ?? '{}'), true) ?: [];
        $addr    = $payload['address'] ?? [];
        $rooms   = $payload['rooms']   ?? [];
        $meters  = $payload['meters']  ?? [];
        $keys    = $payload['keys']    ?? [];
        $meta    = $payload['meta']    ?? [];

        // bereits gespeicherte Raumfotos zum Protokoll (für Thumbnails)
        $filesStmt = $pdo->prepare("SELECT original_name, path, room_key FROM protocol_files WHERE protocol_id=? AND section='room_photo' ORDER BY created_at DESC");
        $filesStmt->execute([$p['id']]);
        $files = $filesStmt->fetchAll(PDO::FETCH_ASSOC);
        $uploadsBase = realpath(__DIR__ . '/../../storage/uploads');
        $fileUrl = function(string $absPath) use ($uploadsBase): string {
            $real = realpath($absPath) ?: $absPath;
            if ($uploadsBase && str_starts_with($real, $uploadsBase)) {
                $rel = ltrim(substr($real, strlen($uploadsBase)), '/');
                return '/uploads/'.$rel;
            }
            return '';
        };

        $title = $p['city'].', '.$p['street'].' '.$p['house_no'].' – '.$p['unit_label'];

        ob_start(); ?>
        <h1 class="h5 mb-2">Protokoll bearbeiten</h1>
        <div class="text-muted mb-3"><?= htmlspecialchars($title) ?></div>

        <form method="post" action="/protocols/save" class="needs-validation" novalidate>
          <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">

          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-kopf" type="button">Kopf</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-raeume" type="button">Räume</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-zaehler" type="button">Zähler</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-schluessel" type="button">Schlüssel & Meta</button></li>
          </ul>

          <div class="tab-content border-start border-end border-bottom p-3">
            <!-- Kopf -->
            <div class="tab-pane fade show active" id="tab-kopf">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">Art</label>
                  <select class="form-select" name="type">
                    <?php foreach (['einzug','auszug','zwischen'] as $t): ?>
                    <option value="<?= $t ?>"<?= ($p['type']===$t?' selected':'') ?>><?= $t ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Mietername</label>
                  <input class="form-control" name="tenant_name" value="<?= htmlspecialchars((string)($p['tenant_name'] ?? '')) ?>" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Zeitstempel</label>
                  <input class="form-control" name="timestamp" type="datetime-local" value="<?= htmlspecialchars((string)($payload['timestamp'] ?? '')) ?>" pattern="[0-9T:\-]+" title="YYYY-MM-DDThh:mm">
                </div>
              </div>
              <hr>
              <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Ort</label><input class="form-control" name="address[city]" value="<?= htmlspecialchars((string)($addr['city'] ?? '')) ?>" required></div>
                <div class="col-md-2"><label class="form-label">PLZ</label><input class="form-control" name="address[postal_code]" value="<?= htmlspecialchars((string)($addr['postal_code'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Straße</label><input class="form-control" name="address[street]" value="<?= htmlspecialchars((string)($addr['street'] ?? '')) ?>" required></div>
                <div class="col-md-2"><label class="form-label">Haus‑Nr.</label><input class="form-control" name="address[house_no]" value="<?= htmlspecialchars((string)($addr['house_no'] ?? '')) ?>" required></div>
                <div class="col-md-4"><label class="form-label">WE‑Bezeichnung</label><input class="form-control" name="address[unit_label]" value="<?= htmlspecialchars((string)($addr['unit_label'] ?? '')) ?>"></div>
                <div class="col-md-2"><label class="form-label">Etage</label><input class="form-control" name="address[floor]" value="<?= htmlspecialchars((string)($addr['floor'] ?? '')) ?>"></div>
              </div>
            </div>

            <!-- Räume -->
            <div class="tab-pane fade" id="tab-raeume">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Räume</h6>
                <button class="btn btn-sm btn-outline-primary" id="add-room-btn">+ Raum</button>
              </div>
              <div id="rooms-wrap">
                <?php foreach ($rooms as $idx => $r): ?>
                <div class="card mb-3">
                  <div class="card-body">
                    <div class="row g-3">
                      <div class="col-md-4"><label class="form-label">Raumname</label><input class="form-control" name="rooms[<?= htmlspecialchars((string)$idx) ?>][name]" value="<?= htmlspecialchars((string)($r['name'] ?? '')) ?>"></div>
                      <div class="col-md-4"><label class="form-label">Geruch</label><input class="form-control" name="rooms[<?= htmlspecialchars((string)$idx) ?>][smell]" value="<?= htmlspecialchars((string)($r['smell'] ?? '')) ?>"></div>
                      <div class="col-md-4 d-flex align-items-end"><div class="form-check">
                        <input class="form-check-input" type="checkbox" name="rooms[<?= htmlspecialchars((string)$idx) ?>][accepted]" <?= !empty($r['accepted'])?'checked':''; ?>> <label class="form-check-label">Abnahme erfolgt</label>
                      </div></div>
                      <div class="col-12"><label class="form-label">IST‑Zustand</label><textarea class="form-control" rows="3" name="rooms[<?= htmlspecialchars((string)$idx) ?>][state]"><?= htmlspecialchars((string)($r['state'] ?? '')) ?></textarea></div>
                      <div class="col-md-6"><label class="form-label">WMZ Nummer</label><input class="form-control" name="rooms[<?= htmlspecialchars((string)$idx) ?>][wmz_no]" value="<?= htmlspecialchars((string)($r['wmz_no'] ?? '')) ?>"></div>
                      <div class="col-md-6"><label class="form-label">WMZ Stand</label><input class="form-control" name="rooms[<?= htmlspecialchars((string)$idx) ?>][wmz_val]" value="<?= htmlspecialchars((string)($r['wmz_val'] ?? '')) ?>"></div>
                    </div>
                    <div class="mt-2 text-end"><button class="btn btn-sm btn-outline-danger" data-remove-room>Entfernen</button></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>

              <!-- Thumbnails bereits hochgeladener Fotos -->
              <hr>
              <h6>Bereits hochgeladene Fotos</h6>
              <?php if (!empty($files)): ?>
                <div class="d-flex flex-wrap gap-2">
                <?php foreach ($files as $f):
                  $url = $fileUrl((string)$f['path']);
                  if ($url): ?>
                    <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="text-decoration-none" data-bs-toggle="tooltip" title="<?= htmlspecialchars((string)$f['original_name']) ?>">
                      <img src="<?= htmlspecialchars($url) ?>" alt="" style="width:120px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #ddd;">
                    </a>
                <?php endif; endforeach; ?>
                </div>
              <?php else: ?>
                <div class="text-muted">Noch keine Fotos vorhanden.</div>
              <?php endif; ?>

              <!-- Template für neue Räume -->
              <template id="room-template">
                <div class="card mb-3">
                  <div class="card-body">
                    <div class="row g-3">
                      <div class="col-md-4"><label class="form-label">Raumname</label><input class="form-control" name="rooms[__IDX__][name]"></div>
                      <div class="col-md-4"><label class="form-label">Geruch</label><input class="form-control" name="rooms[__IDX__][smell]"></div>
                      <div class="col-md-4 d-flex align-items-end"><div class="form-check">
                        <input class="form-check-input" type="checkbox" name="rooms[__IDX__][accepted]"> <label class="form-check-label">Abnahme erfolgt</label>
                      </div></div>
                      <div class="col-12"><label class="form-label">IST‑Zustand</label><textarea class="form-control" rows="3" name="rooms[__IDX__][state]"></textarea></div>
                      <div class="col-md-6"><label class="form-label">WMZ Nummer</label><input class="form-control" name="rooms[__IDX__][wmz_no]"></div>
                      <div class="col-md-6"><label class="form-label">WMZ Stand</label><input class="form-control" name="rooms[__IDX__][wmz_val]"></div>
                    </div>
                    <div class="mt-2 text-end"><button class="btn btn-sm btn-outline-danger" data-remove-room>Entfernen</button></div>
                  </div>
                </div>
              </template>
            </div>

            <!-- Zähler -->
            <div class="tab-pane fade" id="tab-zaehler">
              <?php
                $labels = [
                  'strom_we'=>'Strom (Wohneinheit)',
                  'strom_allg'=>'Strom (Haus allgemein)',
                  'gas_we'=>'Gas (Wohneinheit)',
                  'gas_allg'=>'Gas (Haus allgemein)',
                  'wasser_kueche_kalt'=>'Kaltwasser Küche (blau)',
                  'wasser_kueche_warm'=>'Warmwasser Küche (rot)',
                  'wasser_bad_kalt'=>'Kaltwasser Bad (blau)',
                  'wasser_bad_warm'=>'Warmwasser Bad (rot)',
                  'wasser_wm'=>'Wasserzähler Waschmaschine (blau)',
                ];
              ?>
              <?php foreach ($labels as $key=>$label):
                    $row = $meters[$key] ?? ['no'=>'','val'=>'']; ?>
                <div class="row g-3 align-items-end mb-2">
                  <div class="col-md-5"><label class="form-label"><?= $label ?> – Nummer</label><input class="form-control" name="meters[<?= $key ?>][no]" value="<?= htmlspecialchars((string)$row['no']) ?>"></div>
                  <div class="col-md-5"><label class="form-label"><?= $label ?> – Stand</label><input class="form-control" name="meters[<?= $key ?>][val]" value="<?= htmlspecialchars((string)$row['val']) ?>"></div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Schlüssel & Meta -->
            <div class="tab-pane fade" id="tab-schluessel">
              <h6>Schlüssel</h6>
              <div id="keys-wrap">
                <?php $i=0; foreach ($keys as $k): $i++; ?>
                <div class="row g-2 align-items-end mb-2">
                  <div class="col-md-5"><label class="form-label">Bezeichnung</label><input class="form-control" name="keys[<?= $i ?>][label]" value="<?= htmlspecialchars((string)($k['label'] ?? '')) ?>"></div>
                  <div class="col-md-3"><label class="form-label">Anzahl</label><input type="number" min="0" class="form-control" name="keys[<?= $i ?>][qty]" value="<?= htmlspecialchars((string)($k['qty'] ?? '0')) ?>"></div>
                  <div class="col-md-3"><label class="form-label">Schlüssel‑Nr.</label><input class="form-control" name="keys[<?= $i ?>][no]" value="<?= htmlspecialchars((string)($k['no'] ?? '')) ?>"></div>
                  <div class="col-md-1"><button class="btn btn-sm btn-outline-danger w-100" data-remove-key>−</button></div>
                </div>
                <?php endforeach; ?>
              </div>
              <button class="btn btn-sm btn-outline-primary" id="add-key-btn">+ Schlüssel</button>
              <template id="key-template">
                <div class="col-md-5"><label class="form-label">Bezeichnung</label><input class="form-control" name="keys[__IDX__][label]"></div>
                <div class="col-md-3"><label class="form-label">Anzahl</label><input type="number" min="0" class="form-control" name="keys[__IDX__][qty]" value="0"></div>
                <div class="col-md-3"><label class="form-label">Schlüssel‑Nr.</label><input class="form-control" name="keys[__IDX__][no]"></div>
                <div class="col-md-1"><button class="btn btn-sm btn-outline-danger w-100" data-remove-key>−</button></div>
              </template>

              <hr>
              <h6>Meta</h6>
              <div class="row g-3">
                <div class="col-12"><label class="form-label">Bemerkungen / Sonstiges</label>
                  <textarea class="form-control" rows="3" name="meta[notes]"><?= htmlspecialchars((string)($meta['notes'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="meta[owner_send]" <?= !empty($meta['owner_send'])?'checked':''; ?>>
                    <label class="form-check-label">Protokoll an Eigentümer senden</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="meta[manager_send]" <?= !empty($meta['manager_send'])?'checked':''; ?>>
                    <label class="form-check-label">Protokoll an Verwaltung senden</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary">Speichern (neue Version)</button>
            <a class="btn btn-outline-secondary" href="/protocols">Zurück</a>
          </div>
        </form>

        <script src="/assets/protocol_form.js"></script>
        <script src="/assets/ui_enhancements.js"></script>
        <?php
        View::render('Protokoll – Bearbeiten', ob_get_clean());
    }

    public function save(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id  = (string)($_POST['id'] ?? '');
        if ($id === '') { Flash::add('error','ID fehlt.'); header('Location: /protocols'); return; }

        $type  = (string)($_POST['type'] ?? 'einzug');
        if (!in_array($type, ['einzug','auszug','zwischen'], true)) $type = 'einzug';
        $tenant = trim((string)($_POST['tenant_name'] ?? ''));
        $timestamp = (string)($_POST['timestamp'] ?? '');
        $addr = (array)($_POST['address'] ?? []);

        $rooms = [];
        foreach ((array)($_POST['rooms'] ?? []) as $r) {
            $rooms[] = [
                'name'=>trim((string)($r['name'] ?? '')),
                'smell'=>trim((string)($r['smell'] ?? '')),
                'state'=>trim((string)($r['state'] ?? '')),
                'accepted'=>isset($r['accepted']),
                'wmz_no'=>trim((string)($r['wmz_no'] ?? '')),
                'wmz_val'=>trim((string)($r['wmz_val'] ?? '')),
            ];
        }

        $meters = (array)($_POST['meters'] ?? []);
        $keys = [];
        foreach ((array)($_POST['keys'] ?? []) as $k) {
            if (($k['label'] ?? '')==='' && ($k['qty'] ?? '')==='' && ($k['no'] ?? '')==='') continue;
            $keys[] = [
                'label'=>trim((string)($k['label'] ?? '')),
                'qty'=>(int)($k['qty'] ?? 0),
                'no'=>trim((string)($k['no'] ?? '')),
            ];
        }
        $metaPost = (array)($_POST['meta'] ?? []);
        $meta = [
            'notes'=>trim((string)($metaPost['notes'] ?? '')),
            'owner_send'=>!empty($metaPost['owner_send']),
            'manager_send'=>!empty($metaPost['manager_send']),
        ];

        $payload = [
            'timestamp'=>$timestamp,
            'address'=>[
                'city'=>trim((string)($addr['city'] ?? '')),
                'postal_code'=>trim((string)($addr['postal_code'] ?? '')),
                'street'=>trim((string)($addr['street'] ?? '')),
                'house_no'=>trim((string)($addr['house_no'] ?? '')),
                'unit_label'=>trim((string)($addr['unit_label'] ?? '')),
                'floor'=>trim((string)($addr['floor'] ?? '')),
            ],
            'rooms'=>$rooms,
            'meters'=>$meters,
            'keys'=>$keys,
            'meta'=>$meta,
        ];

        // Update + neue Version
        $up = $pdo->prepare('UPDATE protocols SET type=?, tenant_name=?, payload=?, updated_at=NOW() WHERE id=?');
        $up->execute([$type, ($tenant!==''?$tenant:null), json_encode($payload, JSON_UNESCAPED_UNICODE), $id]);

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(version_no),0)+1 AS v FROM protocol_versions WHERE protocol_id=?');
        $stmt->execute([$id]); $verNo = (int)$stmt->fetchColumn();
        $user = Auth::user();
        $pv = $pdo->prepare('INSERT INTO protocol_versions (id,protocol_id,version_no,data,created_by,created_at) VALUES (UUID(),?,?,?,?,NOW())');
        $pv->execute([$id, $verNo, json_encode($payload, JSON_UNESCAPED_UNICODE), $user['email'] ?? 'system']);

        AuditLogger::log('protocols',$id,'update',['type'=>$type,'tenant'=>$tenant,'version'=>$verNo]);
        Flash::add('success','Gespeichert. Neue Version v'.$verNo.' angelegt.');
        header('Location: /protocols/edit?id='.$id); exit;
    }

    public function delete(): void
    {
        Auth::requireAuth();
        $id = $_GET['id'] ?? '';
        if ($id) {
            $pdo = Database::pdo();
            $pdo->prepare('UPDATE protocols SET deleted_at=NOW() WHERE id=?')->execute([$id]);
            AuditLogger::log('protocols',$id,'soft_delete');
            Flash::add('success','Protokoll gelöscht (Soft-Delete).');
        }
        header('Location: /protocols'); exit;
    }
}
