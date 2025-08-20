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

        // Stammdaten (Schritt 1)
        $owners   = $pdo->query('SELECT id,name,company FROM owners WHERE deleted_at IS NULL ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $managers = $pdo->query('SELECT id,name,company FROM managers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h1 class="h4 mb-0">Übergabeprotokoll – Schritt '.$step.'/4</h1>';
        $html .= '<div class="small text-muted">Entwurf: '.htmlspecialchars($draft).'</div></div>';
        $html .= '<div class="progress mb-3"><div class="progress-bar" role="progressbar" style="width:'.($step*25).'%"></div></div>';

        $html .= '<form method="post" action="/protocols/wizard/save?step='.$step.'&draft='.$draft.'" enctype="multipart/form-data">';

        if ($step === 1) {
            // 1) Adresse & WE
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

            // 2) Protokoll‑Kopf (Label‑Änderung)
            $html .= '<div class="card mb-3"><div class="card-body">';
            $html .= '<h2 class="h6 mb-3">Protokoll‑Kopf</h2>';
            $html .= '<div class="row g-3">';
            $html .= '<div class="col-md-4"><label class="form-label">Art *</label><select class="form-select" name="type" required>';
            foreach ([['einzug','Einzugsprotokoll'],['auszug','Auszugsprotokoll'],['zwischen','Zwischenprotokoll']] as $opt) {
                $val=$opt[0]; $lab=$opt[1]; $sel=(($d['type'] ?? 'einzug')===$val)?' selected':'';
                $html .= '<option value="'.$val.'"'.$sel.'>'.$lab.'</option>';
            }
            $html .= '</select></div>';
            $html .= '<div class="col-md-5"><label class="form-label">Mietername *</label><input class="form-control" name="tenant_name" required value="'.htmlspecialchars($d['tenant_name'] ?? '').'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Zeitstempel</label><input class="form-control" name="timestamp" type="datetime-local" value="'.htmlspecialchars($data['timestamp'] ?? '').'"></div>';
            $html .= '</div></div></div>';

            // 3) Eigentümer (Dropdown + Inline)
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
            $html .= '</div></div></div>';

            // 4) Hausverwaltung
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
            $html .= '<div class="col-md-6"><div class="form-text">Stammdaten unter <a href="/settings">Einstellungen</a> pflegbar.</div></div>';
            $html .= '</div></div>';
        }

        if ($step === 2) {
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
            $meta = $data['meta'] ?? [
                'notes'=>'','owner_send'=>false,'manager_send'=>false,
                'bank'=>['bank'=>'','iban'=>'','holder'=>''],
                'tenant_contact'=>['email'=>'','phone'=>''],
                'tenant_new_addr'=>['street'=>'','house_no'=>'','postal_code'=>'','city'=>''],
                'consents'=>['marketing'=>false,'disposal'=>false],
                'third_attendee'=>''
            ];

            // Schlüssel
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

            // Bankverbindung / Kontakt / neue Adresse
            $html .= '<hr><h2 class="h6 mb-3">Weitere Angaben</h2>';
            $html .= '<div class="row g-3">';
            $html .= '<div class="col-md-4"><label class="form-label">Bank</label><input class="form-control" name="meta[bank][bank]" value="'.htmlspecialchars((string)$meta['bank']['bank']).'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">IBAN</label><input class="form-control" name="meta[bank][iban]" value="'.htmlspecialchars((string)$meta['bank']['iban']).'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">Kontoinhaber</label><input class="form-control" name="meta[bank][holder]" value="'.htmlspecialchars((string)$meta['bank']['holder']).'"></div>';

            $html .= '<div class="col-md-4"><label class="form-label">Mieter E‑Mail</label><input type="email" class="form-control" name="meta[tenant_contact][email]" value="'.htmlspecialchars((string)$meta['tenant_contact']['email']).'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">Mieter Telefon</label><input class="form-control" name="meta[tenant_contact][phone]" value="'.htmlspecialchars((string)$meta['tenant_contact']['phone']).'"></div>';

            $html .= '<div class="col-12"><label class="form-label">Neue Meldeadresse</label></div>';
            $html .= '<div class="col-md-6"><label class="form-label">Straße</label><input class="form-control" name="meta[tenant_new_addr][street]" value="'.htmlspecialchars((string)$meta['tenant_new_addr']['street']).'"></div>';
            $html .= '<div class="col-md-2"><label class="form-label">Haus‑Nr.</label><input class="form-control" name="meta[tenant_new_addr][house_no]" value="'.htmlspecialchars((string)$meta['tenant_new_addr']['house_no']).'"></div>';
            $html .= '<div class="col-md-2"><label class="form-label">PLZ</label><input class="form-control" name="meta[tenant_new_addr][postal_code]" value="'.htmlspecialchars((string)$meta['tenant_new_addr']['postal_code']).'"></div>';
            $html .= '<div class="col-md-2"><label class="form-label">Ort</label><input class="form-control" name="meta[tenant_new_addr][city]" value="'.htmlspecialchars((string)$meta['tenant_new_addr']['city']).'"></div>';

            // Einwilligungen
            $html .= '<div class="col-12"><label class="form-label">Einwilligungen</label></div>';
            $html .= '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[consents][marketing]" '.(!empty($meta['consents']['marketing'])?'checked':'').'> <label class="form-check-label">E‑Mail‑Marketing (außerhalb Mietverhältnis)</label></div></div>';
            $html .= '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[consents][disposal]" '.(!empty($meta['consents']['disposal'])?'checked':'').'> <label class="form-check-label">Einverständnis Entsorgung zurückgelassener Gegenstände</label></div></div>';

            // Versand
            $html .= '<div class="col-12"><label class="form-label">Versand</label></div>';
            $html .= '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[owner_send]" '.(!empty($meta['owner_send'])?'checked':'').'> <label class="form-check-label">an Eigentümer senden</label></div></div>';
            $html .= '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[manager_send]" '.(!empty($meta['manager_send'])?'checked':'').'> <label class="form-check-label">an Verwaltung senden</label></div></div>';

            // Dritte Person & Platzhalter Signaturen
            $html .= '<div class="col-12"><hr></div>';
            $html .= '<div class="col-md-6"><label class="form-label">Dritte anwesende Person (optional, Name)</label><input class="form-control" name="meta[third_attendee]" value="'.htmlspecialchars((string)($meta['third_attendee'] ?? '')).'"></div>';
            $html .= '<div class="col-12"><div class="alert alert-info mt-2">Platzhalter für Unterschriften (Mieter, Eigentümer, optional dritte Person). DocuSign‑Versand folgt hier im nächsten Schritt.</div></div>';

            // Bemerkungen
            $html .= '<div class="col-12"><label class="form-label">Bemerkungen / Sonstiges</label><textarea class="form-control" name="meta[notes]" rows="3">'.htmlspecialchars((string)($meta['notes'] ?? '')).'</textarea></div>';
            $html .= '</div>';
        }

        // Navigation
        $html .= '<div class="kt-sticky-actions"><div><?php if ( > 1) echo "<a class="btn btn-ghost" href="/protocols/wizard?step=".(-1)."&draft="..""><i class="bi bi-arrow-left"></i> Zurück</a>"; ?> <a class="btn btn-ghost" href="/protocols"><i class="bi bi-x-lg"></i> Abbrechen</a></div><div class="d-flex gap-2"><button class="btn btn-primary btn-lg">Weiter <i class="bi bi-arrow-right"></i></button></div></div></form>';

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
