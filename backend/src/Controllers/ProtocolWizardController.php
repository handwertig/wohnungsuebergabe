<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Flash;
use App\Uploads;
use App\Validation;
use App\View;
use App\AuditLogger;
use PDO;

final class ProtocolWizardController
{
    // Schritt 0: Start/Resume
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

    // GET: Formular je Schritt
    public function step(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $step  = max(1, min(4, (int)($_GET['step'] ?? 1)));
        $draft = (string)($_GET['draft'] ?? '');

        $d = $this->loadDraft($pdo, $draft);
        if (!$d) { Flash::add('error','Entwurf nicht gefunden.'); header('Location: /protocols'); return; }

        $data = json_decode($d['data'] ?? '{}', true) ?: [];
        $html = '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h1 class="h4 mb-0">Übergabeprotokoll – Schritt '.$step.'/4</h1>';
        $html .= '<div class="small text-muted">Entwurf: '.htmlspecialchars($draft).'</div></div>';
        $html .= '<div class="progress mb-3"><div class="progress-bar" role="progressbar" style="width:'.($step*25).'%"></div></div>';

        $html .= '<form method="post" action="/protocols/wizard/save?step='.$step.'&draft='.$draft.'" enctype="multipart/form-data">';

        if ($step === 1) {
            // === Schritt 1: Adresse + WE (Neuanlage) ODER vorhandene WE auswählen ===
            // Vorbelegung
            $addr = $data['address'] ?? ['city'=>'','postal_code'=>'','street'=>'','house_no'=>'','unit_label'=>'','floor'=>''];
            $html .= '<div class="card mb-3"><div class="card-body">';
            $html .= '<h2 class="h5 mb-3">Adresse & Wohneinheit</h2>';
            $html .= '<div class="row g-3">';
            $html .= '<div class="col-md-5"><label class="form-label">Ort *</label><input class="form-control" name="address[city]" value="'.htmlspecialchars($addr['city']).'"></div>';
            $html .= '<div class="col-md-2"><label class="form-label">PLZ</label><input class="form-control" name="address[postal_code]" value="'.htmlspecialchars((string)$addr['postal_code']).'"></div>';
            $html .= '<div class="col-md-5"><label class="form-label">Straße *</label><input class="form-control" name="address[street]" value="'.htmlspecialchars($addr['street']).'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Haus‑Nr. *</label><input class="form-control" name="address[house_no]" value="'.htmlspecialchars($addr['house_no']).'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">WE‑Bezeichnung *</label><input class="form-control" name="address[unit_label]" value="'.htmlspecialchars($addr['unit_label']).'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Etage (optional)</label><input class="form-control" name="address[floor]" value="'.htmlspecialchars((string)$addr['floor']).'"></div>';
            $html .= '</div>';
            $html .= '<div class="form-text mt-2">Die Angaben werden bei „Weiter“ automatisch als Objekt/Wohneinheit gespeichert (oder zugeordnet, falls vorhanden).</div>';
            $html .= '</div></div>';

            // Protokollkopf
            $html .= '<div class="card"><div class="card-body">';
            $html .= '<h2 class="h5 mb-3">Protokoll-Kopf</h2>';
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
            // Räume (+ Fotos)
            $rooms = $data['rooms'] ?? [];
            $html .= '<div class="d-flex justify-content-between"><h2 class="h5">Räume</h2><button class="btn btn-sm btn-outline-primary" name="add_room" value="1">Raum hinzufügen</button></div>';
            $idx = 0;
            foreach ($rooms as $key => $room) {
                $idx++;
                $rk = htmlspecialchars((string)$key);
                $html .= '<div class="card my-3"><div class="card-header d-flex justify-content-between"><strong>Raum '.$idx.': '.htmlspecialchars($room['name'] ?? '').'</strong>';
                $html .= '<button class="btn btn-sm btn-outline-danger" name="del_room" value="'.$rk.'" onclick="return confirm(\'Raum entfernen?\')">Entfernen</button>';
                $html .= '</div><div class="card-body row g-3">';
                $html .= '<div class="col-md-6"><label class="form-label">Raumname *</label><input class="form-control" name="rooms['.$rk.'][name]" value="'.htmlspecialchars($room['name'] ?? '').'"></div>';
                $html .= '<div class="col-md-6"><label class="form-label">Geruch</label><input class="form-control" name="rooms['.$rk.'][smell]" value="'.htmlspecialchars($room['smell'] ?? '').'"></div>';
                $html .= '<div class="col-12"><label class="form-label">IST-Zustand</label><textarea class="form-control" name="rooms['.$rk.'][state]" rows="3">'.htmlspecialchars($room['state'] ?? '').'</textarea></div>';
                $html .= '<div class="col-md-3 form-check ms-2"><input class="form-check-input" type="checkbox" name="rooms['.$rk.'][accepted]" '.(!empty($room['accepted'])?'checked':'').'> <label class="form-check-label">Abnahme erfolgt</label></div>';
                $html .= '<div class="col-md-6"><label class="form-label">Wärmemengenzähler – Nummer</label><input class="form-control" name="rooms['.$rk.'][wmz_no]" value="'.htmlspecialchars($room['wmz_no'] ?? '').'"></div>';
                $html .= '<div class="col-md-6"><label class="form-label">Wärmemengenzähler – Stand</label><input class="form-control" name="rooms['.$rk.'][wmz_val]" value="'.htmlspecialchars($room['wmz_val'] ?? '').'"></div>';

                // Fotos zum Raum
                $html .= '<div class="col-12"><label class="form-label">Fotos (JPG/PNG, max. 10MB)</label><input class="form-control" type="file" name="room_photos['.$rk.'][]" multiple accept="image/*"></div>';

                // bereits hochgeladene Fotos anzeigen
                $pics = $this->filesForRoom($pdo, (string)$d['id'], (string)$key);
                if ($pics) {
                    $html .= '<div class="col-12"><div class="d-flex flex-wrap gap-2">';
                    foreach ($pics as $p) {
                        $html .= '<div class="border rounded p-2 small">'.htmlspecialchars($p['original_name']).'</div>';
                    }
                    $html .= '</div></div>';
                }

                $html .= '</div></div>'; // card
            }
            if (!$rooms) {
                $html .= '<p class="text-muted">Noch keine Räume – klicke „Raum hinzufügen“.</p>';
            }
        }

        if ($step === 3) {
            // Zählerstände (MVP)
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
            $html .= '<h2 class="h5 mb-3">Zählerstände</h2>';
            foreach ($types as $key => $label) {
                $row = $meters[$key] ?? ['no'=>'','val'=>''];
                $html .= '<div class="row g-3 align-items-end mb-2">';
                $html .= '<div class="col-md-4"><label class="form-label">'.$label.' – Nummer</label><input class="form-control" name="meters['.$key.'][no]" value="'.htmlspecialchars((string)$row['no']).'"></div>';
                $html .= '<div class="col-md-4"><label class="form-label">'.$label.' – Stand</label><input class="form-control" name="meters['.$key.'][val]" value="'.htmlspecialchars((string)$row['val']).'"></div>';
                $html .= '</div>';
            }
        }

        if ($step === 4) {
            // Schlüssel & Meta
            $keys = $data['keys'] ?? [];
            $html .= '<h2 class="h5 mb-3">Schlüssel</h2>';
            $html .= '<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Bezeichnung</th><th>Anzahl</th><th>Schlüssel-Nr.</th><th></th></tr></thead><tbody id="keys">';
            foreach ($keys as $i => $k) {
                $html .= '<tr>';
                $html .= '<td><input class="form-control" name="keys['.$i.'][label]" value="'.htmlspecialchars((string)($k['label'] ?? '')).'"></td>';
                $html .= '<td><input class="form-control" name="keys['.$i.'][qty]" type="number" min="0" value="'.htmlspecialchars((string)($k['qty'] ?? '0')).'"></td>';
                $html .= '<td><input class="form-control" name="keys['.$i.'][no]" value="'.htmlspecialchars((string)($k['no'] ?? '')).'"></td>';
                $html .= '<td><button class="btn btn-sm btn-outline-danger" name="del_key" value="'.$i.'">Entfernen</button></td>';
                $html .= '</tr>';
            }
            if (!$keys) {
                $html .= '<tr><td colspan="4" class="text-muted">Noch keine Schlüssel – „+ Schlüssel“ klicken.</td></tr>';
            }
            $html .= '</tbody></table></div>';
            $html .= '<button class="btn btn-sm btn-outline-primary" name="add_key" value="1">+ Schlüssel</button>';

            $meta = $data['meta'] ?? ['notes'=>'','owner_send'=>false,'manager_send'=>false];
            $html .= '<hr><h2 class="h5 mb-3">Protokoll-Details</h2>';
            $html .= '<div class="row g-3">';
            $html .= '<div class="col-12"><label class="form-label">Bemerkungen / Sonstiges</label><textarea class="form-control" name="meta[notes]" rows="3">'.htmlspecialchars((string)($meta['notes'] ?? '')).'</textarea></div>';
            $html .= '<div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[owner_send]" '.(!empty($meta['owner_send'])?'checked':'').'> <label class="form-check-label">Protokoll an Eigentümer senden</label></div></div>';
            $html .= '<div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[manager_send]" '.(!empty($meta['manager_send'])?'checked':'').'> <label class="form-check-label">Protokoll an Verwaltung senden</label></div></div>';
            $html .= '</div>';
        }

        // Navigation
        $html .= '<div class="mt-4 d-flex justify-content-between">';
        if ($step > 1) $html .= '<a class="btn btn-outline-secondary" href="/protocols/wizard?step='.($step-1).'&draft='.$draft.'">Zurück</a>';
        else $html .= '<span></span>';
        $html .= '<div class="d-flex gap-2">';
        $html .= '<a class="btn btn-outline-danger" href="/protocols">Abbrechen</a>';
        $html .= '<button class="btn btn-primary">Weiter</button>';
        $html .= '</div></div>';

        $html .= '</form>';

        View::render('Protokoll Wizard', $html);
    }

    // POST: pro Schritt speichern
    public function save(): void
    {
        Auth::requireAuth();
        $pdo  = Database::pdo();
        $step = max(1, min(4, (int)($_GET['step'] ?? 1)));
        $draftId = (string)($_GET['draft'] ?? '');
        $d = $this->loadDraft($pdo, $draftId);
        if (!$d) { Flash::add('error','Entwurf nicht gefunden.'); header('Location: /protocols'); return; }
        $data = json_decode($d['data'] ?? '{}', true) ?: [];
        $next = $step + 1;

        if ($step === 1) {
            // Eingaben lesen
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

            // Objekt & WE anlegen (oder wiederverwenden)
            [$objectId, $unitId] = $this->upsertObjectAndUnit($pdo, $vals['city'], $vals['postal'], $vals['street'], $vals['house_no'], $vals['unit_label'], $vals['floor']);

            // Timestamp mitschreiben
            $data['timestamp'] = (string)($_POST['timestamp'] ?? ($data['timestamp'] ?? ''));

            $this->updateDraft($pdo, $draftId, [
                'unit_id'     => $unitId,
                'type'        => $vals['type'],
                'tenant_name' => $vals['tenant'],
                'data'        => array_merge($data, ['address'=>[
                    'city'=>$vals['city'],'postal_code'=>$vals['postal'],'street'=>$vals['street'],'house_no'=>$vals['house_no'],
                    'unit_label'=>$vals['unit_label'],'floor'=>$vals['floor']
                ]]),
                'step'        => max(2, (int)$d['step']),
            ]);
        }

        if ($step === 2) {
            // Räume + Uploads
            if (isset($_POST['add_room'])) {
                $key = 'r'.substr(sha1((string)microtime(true)),0,6);
                $data['rooms'][$key] = ['name'=>'','state'=>'','smell'=>'','accepted'=>false,'wmz_no'=>'','wmz_val'=>''];
            }
            if (isset($_POST['del_room'])) {
                $k = (string)$_POST['del_room'];
                unset($data['rooms'][$k]);
            }
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
            // Uploads
            if (!empty($_FILES['room_photos']['name']) && is_array($_FILES['room_photos']['name'])) {
                foreach ($_FILES['room_photos']['name'] as $rk => $arr) {
                    $count = count((array)$arr);
                    for ($i=0;$i<$count;$i++) {
                        $file = [
                            'name' => $_FILES['room_photos']['name'][$rk][$i] ?? null,
                            'type' => $_FILES['room_photos']['type'][$rk][$i] ?? null,
                            'tmp_name' => $_FILES['room_photos']['tmp_name'][$rk][$i] ?? null,
                            'error' => $_FILES['room_photos']['error'][$rk][$i] ?? null,
                            'size' => $_FILES['room_photos']['size'][$rk][$i] ?? null,
                        ];
                        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                        try {
                            $saved = Uploads::saveDraftFile($draftId, $file, 'room_photo', (string)$rk);
                            $ins = $pdo->prepare('INSERT INTO protocol_files (id,draft_id,section,room_key,original_name,path,mime,size,created_at) VALUES (UUID(),?,?,?,?,?,?,?,NOW())');
                            $ins->execute([$draftId,$saved['section'],$saved['room_key'],$saved['name'],$saved['path'],$saved['mime'],$saved['size']]);
                        } catch (\Throwable $e) {
                            Flash::add('error','Upload-Fehler: '.$e->getMessage());
                        }
                    }
                }
            }
            $this->updateDraft($pdo, $draftId, ['data'=>$data, 'step'=>max(3,(int)$d['step'])]);
        }

        if ($step === 3) {
            $meters = (array)($_POST['meters'] ?? []);
            $data['meters'] = $meters;
            $this->updateDraft($pdo, $draftId, ['data'=>$data, 'step'=>max(4,(int)$d['step'])]);
        }

        if ($step === 4) {
            if (isset($_POST['add_key'])) {
                $data['keys'][] = ['label'=>'','qty'=>0,'no'=>''];
            }
            if (isset($_POST['del_key'])) {
                $i = (string)$_POST['del_key'];
                if (isset($data['keys'][(int)$i])) unset($data['keys'][(int)$i]);
            }
            if (isset($_POST['keys']) && is_array($_POST['keys'])) {
                $tmp=[]; foreach ($_POST['keys'] as $k) {
                    $tmp[] = ['label'=>trim((string)($k['label']??'')),'qty'=>(int)($k['qty']??0),'no'=>trim((string)($k['no']??''))];
                }
                $data['keys']=$tmp;
            }
            $meta = (array)($_POST['meta'] ?? []);
            $meta['owner_send']  = !empty($meta['owner_send']);
            $meta['manager_send']= !empty($meta['manager_send']);
            $data['meta']=$meta;

            $this->updateDraft($pdo, $draftId, ['data'=>$data]);
        }

        // Navigation
        if (isset($_POST['add_room']) || isset($_POST['del_room']) || isset($_POST['add_key']) || isset($_POST['del_key'])) {
            header('Location: /protocols/wizard?step='.$step.'&draft='.$draftId); return;
        }
        if ($step >= 4) {
            header('Location: /protocols/wizard/review?draft='.$draftId); return;
        }
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
        $files = $this->filesForDraft($pdo, $draftId);

        // Objekt/WE Titel:
        $title = '—';
        if (!empty($d['unit_id'])) {
            $st = $pdo->prepare('SELECT u.label as unit_label, o.city,o.street,o.house_no FROM units u JOIN objects o ON o.id=u.object_id WHERE u.id=?');
            $st->execute([$d['unit_id']]); $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) $title = $row['city'].', '.$row['street'].' '.$row['house_no'].' – '.$row['unit_label'];
        }

        $html = '<h1 class="h4 mb-3">Review & Abschluss</h1>';
        $html .= '<dl class="row">';
        $html .= '<dt class="col-sm-3">Wohneinheit</dt><dd class="col-sm-9">'.htmlspecialchars($title).'</dd>';
        $html .= '<dt class="col-sm-3">Art</dt><dd class="col-sm-9">'.htmlspecialchars($d['type'] ?? '').'</dd>';
        $html .= '<dt class="col-sm-3">Mieter</dt><dd class="col-sm-9">'.htmlspecialchars($d['tenant_name'] ?? '').'</dd>';
        $html .= '</dl>';

        $rooms = $data['rooms'] ?? [];
        if ($rooms) {
            $html .= '<h2 class="h5 mt-4">Räume</h2><ul class="list-group mb-3">';
            foreach ($rooms as $k=>$r) {
                $html .= '<li class="list-group-item"><strong>'.htmlspecialchars($r['name'] ?? '').'</strong> — '.htmlspecialchars($r['state'] ?? '');
                $pics = array_values(array_filter($files, fn($f)=>$f['section']==='room_photo' && $f['room_key']===(string)$k));
                if ($pics) { $html .= '<div class="small text-muted mt-2">'.count($pics).' Foto(s) hochgeladen</div>'; }
                $html .= '</li>';
            }
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

        $req = Validation::required([
            'unit_id'=>$d['unit_id'] ?? '',
            'type'=>$d['type'] ?? '',
            'tenant_name'=>$d['tenant_name'] ?? '',
        ], ['unit_id','type','tenant_name']);
        if ($req) { Flash::add('error','Bitte Schritt 1 vervollständigen.'); header('Location: /protocols/wizard?step=1&draft='.$draftId); return; }

        // Protokoll erzeugen
        $pid = $this->uuid($pdo);
        $ins = $pdo->prepare('INSERT INTO protocols (id,unit_id,type,tenant_name,payload,created_at) VALUES (?,?,?,?,?,NOW())');
        $ins->execute([$pid, $d['unit_id'],$d['type'],$d['tenant_name'], json_encode($data, JSON_UNESCAPED_UNICODE)]);
        AuditLogger::log('protocols',$pid,'create',['from_draft'=>$draftId]);

        // Version 1
        $user = \App\Auth::user();
        $v = $pdo->prepare('INSERT INTO protocol_versions (id,protocol_id,version_no,data,created_by,created_at) VALUES (UUID(),?,?,?,?,NOW())');
        $v->execute([$pid,1,json_encode($data, JSON_UNESCAPED_UNICODE), $user['email'] ?? 'system']);

        // Dateien umhängen
        $pdo->prepare('UPDATE protocol_files SET protocol_id=?, draft_id=NULL WHERE draft_id=?')->execute([$pid,$draftId]);

        // Draft schließen
        $pdo->prepare('UPDATE protocol_drafts SET status="finished", updated_at=NOW() WHERE id=?')->execute([$draftId]);

        Flash::add('success','Protokoll gespeichert und Version 1 angelegt.');
        header('Location: /protocols'); exit;
    }

    // ---------- helpers ----------

    private function loadDraft(PDO $pdo, string $id): ?array
    {
        if ($id === '') return null;
        $st = $pdo->prepare('SELECT * FROM protocol_drafts WHERE id=? AND status="draft"');
        $st->execute([$id]); $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function updateDraft(PDO $pdo, string $id, array $patch): void
    {
        // vorhandenes Draft lesen
        $st = $pdo->prepare('SELECT * FROM protocol_drafts WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $cur = $st->fetch(PDO::FETCH_ASSOC);
        if (!$cur) return;

        $fields = [];
        $params = [];
        foreach (['unit_id','type','tenant_name'] as $f) {
            if (array_key_exists($f, $patch)) {
                $fields[] = "$f=?";
                $params[] = $patch[$f];
            }
        }
        if (array_key_exists('data', $patch)) {
            $fields[] = "data=?";
            $params[] = is_string($patch['data']) ? $patch['data'] : json_encode($patch['data'], JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('step', $patch)) {
            $fields[] = "step=?";
            $params[] = (int)$patch['step'];
        }
        if (!$fields) return;

        $fields[] = "updated_at=NOW()";
        $params[] = $id;

        $sql = "UPDATE protocol_drafts SET ".implode(',', $fields)." WHERE id=?";
        $upd = $pdo->prepare($sql);
        $upd->execute($params);
    }

    private function upsertObjectAndUnit(PDO $pdo, string $city, string $postal, string $street, string $houseNo, string $unitLabel, string $floor): array
    {
        // Objekt suchen
        $so = $pdo->prepare('SELECT id FROM objects WHERE city=? AND street=? AND house_no=? LIMIT 1');
        $so->execute([$city,$street,$houseNo]);
        $objectId = $so->fetchColumn();
        if (!$objectId) {
            $objectId = $this->uuid($pdo);
            $po = $pdo->prepare('INSERT INTO objects (id,city,postal_code,street,house_no,created_at) VALUES (?,?,?,?,?,NOW())');
            $po->execute([$objectId,$city,($postal!==''?$postal:null),$street,$houseNo]);
        } else {
            // ggf. PLZ ergänzen
            if ($postal !== '') {
                $pdo->prepare('UPDATE objects SET postal_code=? WHERE id=? AND (postal_code IS NULL OR postal_code="")')->execute([$postal,$objectId]);
            }
        }

        // WE suchen
        $su = $pdo->prepare('SELECT id FROM units WHERE object_id=? AND label=? LIMIT 1');
        $su->execute([$objectId,$unitLabel]);
        $unitId = $su->fetchColumn();
        if (!$unitId) {
            $unitId = $this->uuid($pdo);
            $pu = $pdo->prepare('INSERT INTO units (id,object_id,label,floor,created_at) VALUES (?,?,?, ?, NOW())');
            $pu->execute([$unitId,$objectId,$unitLabel,($floor!==''?$floor:null)]);
        } else {
            // ggf. Etage ergänzen
            if ($floor !== '') {
                $pdo->prepare('UPDATE units SET floor=? WHERE id=? AND (floor IS NULL OR floor="")')->execute([$floor,$unitId]);
            }
        }

        return [(string)$objectId,(string)$unitId];
    }

    private function filesForDraft(PDO $pdo, string $draftId): array
    {
        $st = $pdo->prepare('SELECT * FROM protocol_files WHERE draft_id=? ORDER BY created_at');
        $st->execute([$draftId]); return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function filesForRoom(PDO $pdo, string $draftId, string $roomKey): array
    {
        $st = $pdo->prepare('SELECT * FROM protocol_files WHERE draft_id=? AND room_key=? ORDER BY created_at');
        $st->execute([$draftId,$roomKey]); return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function uuid(PDO $pdo): string
    {
        return (string)$pdo->query('SELECT UUID()')->fetchColumn();
    }
}
