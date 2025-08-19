<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Flash;
use App\Validation;
use App\View;
use App\AuditLogger;
use PDO;

final class ProtocolWizardController
{
    public function start(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $user = \App\Auth::user();

        $id = $this->uuid($pdo);
        $stmt = $pdo->prepare("INSERT INTO protocol_drafts (id,data,created_by,created_at) VALUES (?,?,?,NOW())");
        $stmt->execute([$id, json_encode(['rooms'=>[],'meters'=>[],'keys'=>[]], JSON_UNESCAPED_UNICODE), $user['email'] ?? 'system']);

        header('Location: /protocols/wizard?step=1&draft='.$id); exit;
    }

    public function step(): void
    {
        Auth::requireAuth();
        $pdo   = Database::pdo();
        $step  = max(1, min(4, (int)($_GET['step'] ?? 1)));
        $draft = (string)($_GET['draft'] ?? '');

        $d = $this->loadDraft($pdo, $draft);
        if (!$d) { Flash::add('error','Entwurf nicht gefunden.'); header('Location: /protocols'); return; }
        $data = json_decode($d['data'] ?? '{}', true) ?: [];

        // Stammdaten (für Schritt 1)
        $owners = $pdo->query('SELECT id,name,company FROM owners WHERE deleted_at IS NULL ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $managers = $pdo->query('SELECT id,name,company FROM managers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h1 class="h4 mb-0">Übergabeprotokoll – Schritt '.$step.'/4</h1>';
        $html .= '<div class="small text-muted">Entwurf: '.htmlspecialchars($draft).'</div></div>';
        $html .= '<div class="progress mb-3"><div class="progress-bar" role="progressbar" style="width:'.($step*25).'%"></div></div>';

        $html .= '<form method="post" action="/protocols/wizard/save?step='.$step.'&draft='.$draft.'" enctype="multipart/form-data">';

        if ($step === 1) {
            // Adresse & WE
            $addr = $data['address'] ?? ['city'=>'','postal_code'=>'','street'=>'','house_no'=>'','unit_label'=>'','floor'=>''];
            $html .= '<div class="card mb-3"><div class="card-body">';
            $html .= '<h2 class="h6 mb-3">Adresse & Wohneinheit</h2>';
            $html .= '<div class="row g-3">';
            $html .= '<div class="col-md-5"><label class="form-label">Ort *</label><input class="form-control" name="address[city]" value="'.htmlspecialchars($addr['city']).'"></div>';
            $html .= '<div class="col-md-2"><label class="form-label">PLZ</label><input class="form-control" name="address[postal_code]" value="'.htmlspecialchars((string)$addr['postal_code']).'"></div>';
            $html .= '<div class="col-md-5"><label class="form-label">Straße *</label><input class="form-control" name="address[street]" value="'.htmlspecialchars($addr['street']).'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Haus‑Nr. *</label><input class="form-control" name="address[house_no]" value="'.htmlspecialchars($addr['house_no']).'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">WE‑Bezeichnung *</label><input class="form-control" name="address[unit_label]" value="'.htmlspecialchars($addr['unit_label']).'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Etage (optional)</label><input class="form-control" name="address[floor]" value="'.htmlspecialchars((string)$addr['floor']).'"></div>';
            $html .= '</div><div class="form-text mt-2">Bei „Weiter“ werden Objekt & WE automatisch erzeugt/zugeordnet.</div>';
            $html .= '</div></div>';

            // Eigentümer
            $ownerSnap = $data['owner'] ?? ['name'=>'','company'=>'','address'=>'','email'=>'','phone'=>''];
            $html .= '<div class="card mb-3"><div class="card-body">';
            $html .= '<h2 class="h6 mb-3">Eigentümer</h2>';
            $html .= '<div class="row g-3 align-items-end">';
            $html .= '<div class="col-md-6"><label class="form-label">Eigentümer (bestehend)</label><select class="form-select" name="owner_id"><option value="">— bitte wählen —</option>';
            foreach ($owners as $o) {
                $label = $o['name'].($o['company'] ? ' ('.$o['company'].')' : '');
                $sel = (($d['owner_id'] ?? '') === $o['id']) ? ' selected' : '';
                $html .= '<option value="'.$o['id'].'"'.$sel.'>'.htmlspecialchars($label).'</option>';
            }
            $html .= '</select></div>';
            $html .= '<div class="col-md-6"><div class="form-text">Oder neuen Eigentümer direkt erfassen:</div></div>';
            $html .= '<div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="owner_new[name]" value="'.htmlspecialchars((string)$ownerSnap['name']).'"></div>';
            $html .= '<div class="col-md-6"><label class="form-label">Firma</label><input class="form-control" name="owner_new[company]" value="'.htmlspecialchars((string)$ownerSnap['company']).'"></div>';
            $html .= '<div class="col-md-12"><label class="form-label">Adresse</label><input class="form-control" name="owner_new[address]" value="'.htmlspecialchars((string)$ownerSnap['address']).'"></div>';
            $html .= '<div class="col-md-6"><label class="form-label">E‑Mail</label><input type="email" class="form-control" name="owner_new[email]" value="'.htmlspecialchars((string)$ownerSnap['email']).'"></div>';
            $html .= '<div class="col-md-6"><label class="form-label">Telefon</label><input class="form-control" name="owner_new[phone]" value="'.htmlspecialchars((string)$ownerSnap['phone']).'"></div>';
            $html .= '</div></div>';

            // Hausverwaltung (NEU)
            $html .= '<div class="card mb-3"><div class="card-body">';
            $html .= '<h2 class="h6 mb-3">Hausverwaltung</h2>';
            $html .= '<div class="row g-3">';
            $html .= '<div class="col-md-6"><label class="form-label">Hausverwaltung (Dropdown)</label><select class="form-select" name="manager_id"><option value="">— bitte wählen —</option>';
            foreach ($managers as $m) {
                $label = $m['name'].($m['company'] ? ' ('.$m['company'].')' : '');
                $sel = (($d['manager_id'] ?? '') === $m['id']) ? ' selected' : '';
                $html .= '<option value="'.$m['id'].'"'.$sel.'>'.htmlspecialchars($label).'</option>';
            }
            $html .= '</select></div>';
            $html .= '<div class="col-md-6"><div class="form-text">Stammdaten der Hausverwaltungen pflegst du unter <a href="/settings">Einstellungen</a>.</div></div>';
            $html .= '</div></div>';

            // Protokollkopf
            $html .= '<div class="card"><div class="card-body">';
            $html .= '<h2 class="h6 mb-3">Protokoll‑Kopf</h2>';
            $html .= '<div class="row g-3">';
            $html .= '<div class="col-md-3"><label class="form-label">Art *</label><select class="form-select" name="type" required>';
            foreach (['einzug','auszug','zwischen'] as $t) {
                $html .= '<option value="'.$t.'"'.(($d['type'] ?? 'einzug')===$t?' selected':'').'>'.$t.'</option>';
            }
            $html .= '</select></div>';
            $html .= '<div class="col-md-6"><label class="form-label">Mietername *</label><input class="form-control" name="tenant_name" required value="'.htmlspecialchars($d['tenant_name'] ?? '').'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Zeitstempel</label><input class="form-control" name="timestamp" type="datetime-local" value="'.htmlspecialchars($data['timestamp'] ?? '').'"></div>';
            $html .= '</div></div></div>';
        }

        if ($step === 2) {
            // Räume …
            $rooms = $data['rooms'] ?? [];
            $html .= '<div class="d-flex justify-content-between"><h2 class="h6">Räume</h2><button class="btn btn-sm btn-outline-primary" name="add_room" value="1">Raum hinzufügen</button></div>';
            $idx = 0;
            foreach ($rooms as $key => $room) {
                $idx++; $rk = htmlspecialchars((string)$key);
                $html .= '<div class="card my-3"><div class="card-header d-flex justify-content-between"><strong>Raum '.$idx.': '.htmlspecialchars($room['name'] ?? '').'</strong>';
                $html .= '<button class="btn btn-sm btn-outline-danger" name="del_room" value="'.$rk.'" onclick="return confirm(\'Raum entfernen?\')">Entfernen</button>';
                $html .= '</div><div class="card-body row g-3">';
                $html .= '<div class="col-md-6"><label class="form-label">Raumname *</label><input class="form-control" name="rooms['.$rk.'][name]" value="'.htmlspecialchars($room['name'] ?? '').'"></div>';
                $html .= '<div class="col-md-6"><label class="form-label">Geruch</label><input class="form-control" name="rooms['.$rk.'][smell]" value="'.htmlspecialchars($room['smell'] ?? '').'"></div>';
                $html .= '<div class="col-12"><label class="form-label">IST‑Zustand</label><textarea class="form-control" name="rooms['.$rk.'][state]" rows="3">'.htmlspecialchars($room['state'] ?? '').'</textarea></div>';
                $html .= '<div class="col-md-3 form-check ms-2"><input class="form-check-input" type="checkbox" name="rooms['.$rk.'][accepted]" '.(!empty($room['accepted'])?'checked':'').'> <label class="form-check-label">Abnahme erfolgt</label></div>';
                $html .= '<div class="col-md-6"><label class="form-label">WMZ Nummer</label><input class="form-control" name="rooms['.$rk.'][wmz_no]" value="'.htmlspecialchars($room['wmz_no'] ?? '').'"></div>';
                $html .= '<div class="col-md-6"><label class="form-label">WMZ Stand</label><input class="form-control" name="rooms['.$rk.'][wmz_val]" value="'.htmlspecialchars($room['wmz_val'] ?? '').'"></div>';
                $html .= '<div class="col-12"><label class="form-label">Fotos (JPG/PNG, max. 10MB)</label><input class="form-control" type="file" name="room_photos['.$rk.'][]" multiple accept="image/*"></div>';
                $html .= '</div></div>';
            }
            if (!$rooms) $html .= '<p class="text-muted">Noch keine Räume – „Raum hinzufügen“.</p>';
        }

        if ($step === 3) {
            $meters = $data['meters'] ?? [];
            $types = [
                'strom_we' => 'Strom (Wohneinheit)',
                'strom_allg' => 'Strom (Haus allgemein)',
                'gas_we' => 'Gas (Wohneinheit)',
                'gas_allg' => 'Gas (Haus allgemein)',
                'wasser_kueche_kalt' => 'Kaltwasser Küche (blau)',
                'wasser_kueche_warm' => 'Warmwasser Küche (rot)',
                'wasser_bad_kalt' => 'Kaltwasser Bad (blau)',
                'wasser_bad_warm' => 'Warmwasser Bad (rot)',
                'wasser_wm' => 'Wasserzähler Waschmaschine (blau)',
            ];
            $html .= '<h2 class="h6 mb-3">Zählerstände</h2>';
            foreach ($types as $key => $label) {
                $row = $meters[$key] ?? ['no'=>'','val'=>''];
                $html .= '<div class="row g-3 align-items-end mb-2">';
                $html .= '<div class="col-md-4"><label class="form-label">'.$label.' – Nummer</label><input class="form-control" name="meters['.$key.'][no]" value="'.htmlspecialchars((string)$row['no']).'"></div>';
                $html .= '<div class="col-md-4"><label class="form-label">'.$label.' – Stand</label><input class="form-control" name="meters['.$key.'][val]" value="'.htmlspecialchars((string)$row['val']).'"></div>';
                $html .= '</div>';
            }
        }

        if ($step === 4) {
            $keys = $data['keys'] ?? [];
            $meta = $data['meta'] ?? ['notes'=>'','owner_send'=>false,'manager_send'=>false];
            $html .= '<h2 class="h6 mb-3">Schlüssel</h2>';
            $html .= '<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Bezeichnung</th><th>Anzahl</th><th>Nr.</th><th></th></tr></thead><tbody>';
            $ii=0; foreach ($keys as $i => $k) { $ii++;
                $html .= '<tr>';
                $html .= '<td><input class="form-control" name="keys['.$i.'][label]" value="'.htmlspecialchars((string)($k['label'] ?? '')).'"></td>';
                $html .= '<td><input class="form-control" type="number" min="0" name="keys['.$i.'][qty]" value="'.htmlspecialchars((string)($k['qty'] ?? '0')).'"></td>';
                $html .= '<td><input class="form-control" name="keys['.$i.'][no]" value="'.htmlspecialchars((string)($k['no'] ?? '')).'"></td>';
                $html .= '<td><button class="btn btn-sm btn-outline-danger" name="del_key" value="'.$i.'">Entfernen</button></td>';
                $html .= '</tr>';
            }
            if ($ii===0) $html .= '<tr><td colspan="4" class="text-muted">Noch keine Schlüssel – „+ Schlüssel“ klicken.</td></tr>';
            $html .= '</tbody></table></div>';
            $html .= '<button class="btn btn-sm btn-outline-primary" name="add_key" value="1">+ Schlüssel</button>';
            $html .= '<hr><h2 class="h6 mb-3">Protokoll‑Details</h2>';
            $html .= '<div class="row g-3">';
            $html .= '<div class="col-12"><label class="form-label">Bemerkungen / Sonstiges</label><textarea class="form-control" name="meta[notes]" rows="3">'.htmlspecialchars((string)($meta['notes'] ?? '')).'</textarea></div>';
            $html .= '<div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[owner_send]" '.(!empty($meta['owner_send'])?'checked':'').'> <label class="form-check-label">an Eigentümer senden</label></div></div>';
            $html .= '<div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[manager_send]" '.(!empty($meta['manager_send'])?'checked':'').'> <label class="form-check-label">an Verwaltung senden</label></div></div>';
            $html .= '</div>';
        }

        // Navigation
        $html .= '<div class="mt-4 d-flex justify-content-between">';
        if ($step > 1) $html .= '<a class="btn btn-outline-secondary" href="/protocols/wizard?step='.($step-1).'&draft='.$draft.'">Zurück</a>'; else $html .= '<span></span>';
        $html .= '<div class="d-flex gap-2"><a class="btn btn-outline-danger" href="/protocols">Abbrechen</a><button class="btn btn-primary">Weiter</button></div>';
        $html .= '</div>';

        $html .= '</form>';

        View::render('Protokoll Wizard', $html);
    }

    public function save(): void
    {
        Auth::requireAuth();
        $pdo  = Database::pdo();
        $step = max(1, min(4, (int)($_GET['step'] ?? 1)));
        $draftId = (string)($_GET['draft'] ?? '');
        $d = $this->loadDraft($pdo, $draftId);
        if (!$d) { Flash::add('error','Entwurf nicht gefunden.'); header('Location: /protocols'); return; }
        $data = json_decode($d['data'] ?? '{}', true) ?: [];

        if ($step === 1) {
            $addr = (array)($_POST['address'] ?? []);
            $vals = [
                'city'       => trim((string)($addr['city'] ?? '')),
                'street'     => trim((string)($addr['street'] ?? '')),
                'house_no'   => trim((string)($addr['house_no'] ?? '')),
                'postal'     => trim((string)($addr['postal_code'] ?? '')),
                'unit_label' => trim((string)($addr['unit_label'] ?? '')),
                'floor'      => trim((string)($addr['floor'] ?? '')),
                'type'       => (string)($_POST['type'] ?? 'einzug'),
                'tenant'     => trim((string)($_POST['tenant_name'] ?? '')),
            ];
            $errs = Validation::required($vals, ['city','street','house_no','unit_label','type','tenant']);
            if ($errs) { Flash::add('error','Bitte Pflichtfelder in Adresse/WE und Protokollkopf ausfüllen.'); header('Location: /protocols/wizard?step=1&draft='.$draftId); return; }

            // Objekt & WE
            [$objectId, $unitId] = $this->upsertObjectAndUnit($pdo, $vals['city'], $vals['postal'], $vals['street'], $vals['house_no'], $vals['unit_label'], $vals['floor']);

            // Eigentümer
            $ownerIdPost = (string)($_POST['owner_id'] ?? '');
            $ownerNew = (array)($_POST['owner_new'] ?? []);
            [$ownerId, $ownerSnap] = $this->resolveOwner($pdo, $ownerIdPost, $ownerNew);

            // Hausverwaltung
            $managerId = (string)($_POST['manager_id'] ?? '');

            // Timestamp
            $data['timestamp'] = (string)($_POST['timestamp'] ?? ($data['timestamp'] ?? ''));

            $this->updateDraft($pdo, $draftId, [
                'unit_id'     => $unitId,
                'type'        => $vals['type'],
                'tenant_name' => $vals['tenant'],
                'owner_id'    => $ownerId ?: null,
                'manager_id'  => $managerId ?: null,
                'data'        => array_merge($data, [
                    'address'=>[
                        'city'=>$vals['city'],'postal_code'=>$vals['postal'],'street'=>$vals['street'],'house_no'=>$vals['house_no'],
                        'unit_label'=>$vals['unit_label'],'floor'=>$vals['floor']
                    ],
                    'owner'=>$ownerSnap
                ]),
                'step'        => max(2, (int)$d['step']),
            ]);
        }

        if ($step === 2) {
            // Räume + Uploads (unverändert – hier kein Upload mehr notwendig, Draft-Files bleiben)
            if (isset($_POST['add_room'])) { $key = 'r'.substr(sha1((string)microtime(true)),0,6); $data['rooms'][$key] = ['name'=>'','state'=>'','smell'=>'','accepted'=>false,'wmz_no'=>'','wmz_val'=>'']; }
            if (isset($_POST['del_room'])) { $k = (string)$_POST['del_room']; unset($data['rooms'][$k]); }
            if (isset($_POST['rooms']) && is_array($_POST['rooms'])) {
                foreach ($_POST['rooms'] as $k => $r) {
                    $data['rooms'][$k]['name']    = trim((string)($r['name'] ?? ''));
                    $data['rooms'][$k]['state']   = trim((string)($r['state'] ?? ''));
                    $data['rooms'][$k]['smell']   = trim((string)($r['smell'] ?? ''));
                    $data['rooms'][$k]['accepted']= isset($r['accepted']);
                    $data['rooms'][$k]['wmz_no']  = trim((string)($r['wmz_no'] ?? ''));
                    $data['rooms'][$k]['wmz_val'] = trim((string)($r['wmz_val'] ?? ''));
                }
            }
            $this->updateDraft($pdo, $draftId, ['data'=>$data, 'step'=>max(3,(int)$d['step'])]);
        }

        if ($step === 3) {
            $data['meters'] = (array)($_POST['meters'] ?? []);
            $this->updateDraft($pdo, $draftId, ['data'=>$data, 'step'=>max(4,(int)$d['step'])]);
        }

        if ($step === 4) {
            if (isset($_POST['add_key'])) { $data['keys'][] = ['label'=>'','qty'=>0,'no'=>'']; }
            if (isset($_POST['del_key'])) { $i = (string)$_POST['del_key']; if (isset($data['keys'][(int)$i])) unset($data['keys'][(int)$i]); }
            if (isset($_POST['keys']) && is_array($_POST['keys'])) {
                $tmp=[]; foreach ($_POST['keys'] as $k) { $tmp[] = ['label'=>trim((string)($k['label']??'')),'qty'=>(int)($k['qty']??0),'no'=>trim((string)($k['no']??''))]; }
                $data['keys']=$tmp;
            }
            $meta = (array)($_POST['meta'] ?? []);
            $meta['owner_send']  = !empty($meta['owner_send']);
            $meta['manager_send']= !empty($meta['manager_send']);
            $data['meta']=$meta;

            $this->updateDraft($pdo, $draftId, ['data'=>$data]);
        }

        if (isset($_POST['add_room']) || isset($_POST['del_room']) || isset($_POST['add_key']) || isset($_POST['del_key'])) {
            header('Location: /protocols/wizard?step='.$step.'&draft='.$draftId); return;
        }
        if ($step >= 4) { header('Location: /protocols/wizard/review?draft='.$draftId); return; }
        header('Location: /protocols/wizard?step='.($step+1).'&draft='.$draftId);
    }

    public function review(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $draftId = (string)($_GET['draft'] ?? '');
        $d = $this->loadDraft($pdo, $draftId);
        if (!$d) { Flash::add('error','Entwurf nicht gefunden.'); header('Location: /protocols'); return; }
        $data = json_decode($d['data'] ?? '{}', true) ?: [];

        $title = '—';
        if (!empty($d['unit_id'])) {
            $st = $pdo->prepare('SELECT u.label as unit_label, o.city,o.street,o.house_no FROM units u JOIN objects o ON o.id=u.object_id WHERE u.id=?');
            $st->execute([$d['unit_id']]); if ($row=$st->fetch(PDO::FETCH_ASSOC)) $title = $row['city'].', '.$row['street'].' '.$row['house_no'].' – '.$row['unit_label'];
        }
        $ownerStr = '—';
        if (!empty($d['owner_id'])) {
            $st = $pdo->prepare('SELECT name,company FROM owners WHERE id=?');
            $st->execute([$d['owner_id']]); if ($o=$st->fetch(PDO::FETCH_ASSOC)) $ownerStr = $o['name'].($o['company']?' ('.$o['company'].')':'');
        } elseif (!empty($data['owner']['name'])) { $ownerStr = $data['owner']['name']; }

        $managerStr = '—';
        if (!empty($d['manager_id'])) {
            $st = $pdo->prepare('SELECT name,company FROM managers WHERE id=?');
            $st->execute([$d['manager_id']]); if ($m=$st->fetch(PDO::FETCH_ASSOC)) $managerStr = $m['name'].($m['company']?' ('.$m['company'].')':'');
        }

        $html = '<h1 class="h5 mb-3">Review & Abschluss</h1>';
        $html .= '<dl class="row">';
        $html .= '<dt class="col-sm-3">Wohneinheit</dt><dd class="col-sm-9">'.htmlspecialchars($title).'</dd>';
        $html .= '<dt class="col-sm-3">Eigentümer</dt><dd class="col-sm-9">'.htmlspecialchars($ownerStr).'</dd>';
        $html .= '<dt class="col-sm-3">Hausverwaltung</dt><dd class="col-sm-9">'.htmlspecialchars($managerStr).'</dd>';
        $html .= '<dt class="col-sm-3">Art</dt><dd class="col-sm-9">'.htmlspecialchars($d['type'] ?? '').'</dd>';
        $html .= '<dt class="col-sm-3">Mieter</dt><dd class="col-sm-9">'.htmlspecialchars($d['tenant_name'] ?? '').'</dd>';
        $html .= '</dl>';

        if (!empty($data['rooms'])) {
            $html .= '<h2 class="h6 mt-4">Räume</h2><ul class="list-group mb-3">';
            foreach ($data['rooms'] as $k=>$r) { $html .= '<li class="list-group-item"><strong>'.htmlspecialchars($r['name'] ?? '').'</strong> — '.htmlspecialchars($r['state'] ?? '').'</li>'; }
            $html .= '</ul>';
        }

        $html .= '<form method="post" action="/protocols/wizard/finish?draft='.$draftId.'"><div class="d-flex gap-2">';
        $html .= '<a class="btn btn-outline-secondary" href="/protocols/wizard?step=4&draft='.$draftId.'">Zurück</a>';
        $html .= '<button class="btn btn-success">Abschließen & Speichern</button>';
        $html .= '</div></form>';

        View::render('Protokoll Review', $html);
    }

    public function finish(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $draftId = (string)($_GET['draft'] ?? '');
        $d = $this->loadDraft($pdo, $draftId);
        if (!$d) { Flash::add('error','Entwurf nicht gefunden.'); header('Location: /protocols'); return; }
        $data = json_decode($d['data'] ?? '{}', true) ?: [];

        $req = Validation::required(['unit_id'=>$d['unit_id'] ?? '','type'=>$d['type'] ?? '','tenant_name'=>$d['tenant_name'] ?? ''], ['unit_id','type','tenant_name']);
        if ($req) { Flash::add('error','Bitte Schritt 1 vervollständigen.'); header('Location: /protocols/wizard?step=1&draft='.$draftId); return; }

        $pid = $this->uuid($pdo);
        $ins = $pdo->prepare('INSERT INTO protocols (id,unit_id,type,tenant_name,payload,owner_id,manager_id,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $ins->execute([$pid, $d['unit_id'],$d['type'],$d['tenant_name'], json_encode($data, JSON_UNESCAPED_UNICODE), ($d['owner_id'] ?? null), ($d['manager_id'] ?? null)]);
        AuditLogger::log('protocols',$pid,'create',['from_draft'=>$draftId]);

        $user = \App\Auth::user();
        $v = $pdo->prepare('INSERT INTO protocol_versions (id,protocol_id,version_no,data,created_by,created_at) VALUES (UUID(),?,?,?,?,NOW())');
        $v->execute([$pid,1,json_encode($data, JSON_UNESCAPED_UNICODE), $user['email'] ?? 'system']);

        $pdo->prepare('UPDATE protocol_files SET protocol_id=?, draft_id=NULL WHERE draft_id=?')->execute([$pid,$draftId]);
        $pdo->prepare('UPDATE protocol_drafts SET status="finished", updated_at=NOW() WHERE id=?')->execute([$draftId]);

        Flash::add('success','Protokoll gespeichert und Version 1 angelegt.');
        header('Location: /protocols'); exit;
    }

    // helpers
    private function loadDraft(PDO $pdo, string $id): ?array
    { if ($id === '') return null; $st = $pdo->prepare('SELECT * FROM protocol_drafts WHERE id=? AND status="draft"'); $st->execute([$id]); return $st->fetch(PDO::FETCH_ASSOC) ?: null; }

    private function updateDraft(PDO $pdo, string $id, array $patch): void
    {
        $st = $pdo->prepare('SELECT * FROM protocol_drafts WHERE id=? LIMIT 1'); $st->execute([$id]); $cur = $st->fetch(PDO::FETCH_ASSOC); if (!$cur) return;
        $fields = []; $params = [];
        foreach (['unit_id','type','tenant_name','owner_id','manager_id'] as $f) { if (array_key_exists($f, $patch)) { $fields[]="$f=?"; $params[]=$patch[$f]; } }
        if (array_key_exists('data',$patch)) { $fields[]="data=?"; $params[] = is_string($patch['data']) ? $patch['data'] : json_encode($patch['data'], JSON_UNESCAPED_UNICODE); }
        if (array_key_exists('step',$patch)) { $fields[]="step=?"; $params[]=(int)$patch['step']; }
        if (!$fields) return;
        $fields[]="updated_at=NOW()"; $params[]=$id;
        $sql="UPDATE protocol_drafts SET ".implode(',', $fields)." WHERE id=?"; $pdo->prepare($sql)->execute($params);
    }

    private function upsertObjectAndUnit(PDO $pdo, string $city, string $postal, string $street, string $houseNo, string $unitLabel, string $floor): array
    {
        $so = $pdo->prepare('SELECT id FROM objects WHERE city=? AND street=? AND house_no=? LIMIT 1');
        $so->execute([$city,$street,$houseNo]); $objectId = $so->fetchColumn();
        if (!$objectId) { $objectId = $this->uuid($pdo); $pdo->prepare('INSERT INTO objects (id,city,postal_code,street,house_no,created_at) VALUES (?,?,?,?,?,NOW())')->execute([$objectId,$city,($postal!==''?$postal:null),$street,$houseNo]); }
        elseif ($postal !== '') { $pdo->prepare('UPDATE objects SET postal_code=? WHERE id=? AND (postal_code IS NULL OR postal_code="")')->execute([$postal,$objectId]); }

        $su = $pdo->prepare('SELECT id FROM units WHERE object_id=? AND label=? LIMIT 1');
        $su->execute([$objectId,$unitLabel]); $unitId = $su->fetchColumn();
        if (!$unitId) { $unitId = $this->uuid($pdo); $pdo->prepare('INSERT INTO units (id,object_id,label,floor,created_at) VALUES (?,?,?, ?, NOW())')->execute([$unitId,$objectId,$unitLabel,($floor!==''?$floor:null)]); }
        elseif ($floor !== '') { $pdo->prepare('UPDATE units SET floor=? WHERE id=? AND (floor IS NULL OR floor="")')->execute([$floor,$unitId]); }

        return [(string)$objectId,(string)$unitId];
    }

    private function resolveOwner(PDO $pdo, string $ownerIdPost, array $ownerNew): array
    {
        if ($ownerIdPost !== '') {
            $st = $pdo->prepare('SELECT name,company,address,email,phone FROM owners WHERE id=?'); $st->execute([$ownerIdPost]); $o = $st->fetch(PDO::FETCH_ASSOC);
            $snap = $o ?: ['name'=>'','company'=>'','address'=>'','email'=>'','phone'=>'']; return [$ownerIdPost, $snap];
        }
        $name = trim((string)($ownerNew['name'] ?? ''));
        if ($name !== '') {
            $id = $this->uuid($pdo);
            $pdo->prepare('INSERT INTO owners (id,name,company,address,email,phone,created_at) VALUES (?,?,?,?,?,?,NOW())')
                ->execute([$id, $name, ($ownerNew['company'] ?? null), ($ownerNew['address'] ?? null), ($ownerNew['email'] ?? null), ($ownerNew['phone'] ?? null)]);
            $snap = [
                'name'=>$name,'company'=>trim((string)($ownerNew['company'] ?? '')),
                'address'=>trim((string)($ownerNew['address'] ?? '')),
                'email'=>trim((string)($ownerNew['email'] ?? '')),
                'phone'=>trim((string)($ownerNew['phone'] ?? '')),
            ];
            return [$id, $snap];
        }
        return [null, ['name'=>'','company'=>'','address'=>'','email'=>'','phone'=>'']];
    }

    private function uuid(PDO $pdo): string
    { return (string)$pdo->query('SELECT UUID()')->fetchColumn(); }
}
