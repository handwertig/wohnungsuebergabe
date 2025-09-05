<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Flash;
use App\View;
use PDO;

final class ProtocolWizardController
{
    private function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

    public function start(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id  = (string)$pdo->query('SELECT UUID()')->fetchColumn();
        $empty = ['rooms'=>[],'meters'=>[],'keys'=>[],'timestamp'=>''];
        $pdo->prepare("INSERT INTO protocol_drafts (id,data,status,created_at) VALUES (?,?, 'draft', NOW())")
            ->execute([$id, json_encode($empty, JSON_UNESCAPED_UNICODE)]);
        header('Location: /protocols/wizard?step=1&draft='.$id);
    }

    public function step(): void
    {
        Auth::requireAuth();
        $pdo   = Database::pdo();
        $step  = max(1, min(4, (int)($_GET['step'] ?? 1)));
        $draft = (string)($_GET['draft'] ?? '');

        $st=$pdo->prepare("SELECT * FROM protocol_drafts WHERE id=? AND status='draft'");
        $st->execute([$draft]); $d=$st->fetch(PDO::FETCH_ASSOC);
        if(!$d){ Flash::add('error','Entwurf nicht gefunden.'); header('Location:/protocols'); return; }
        $data = json_decode((string)($d['data']??'{}'), true) ?: [];

        $owners   = $pdo->query("SELECT id,name,company FROM owners WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $managers = $pdo->query("SELECT id,name,company FROM managers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        // Rechtstexte-Helper (für Schritt 4)
        $getLatest = function(string $name) use ($pdo){
            $s=$pdo->prepare("SELECT title, content, version FROM legal_texts WHERE name=? ORDER BY version DESC LIMIT 1");
            $s->execute([$name]); $r=$s->fetch(PDO::FETCH_ASSOC);
            return $r ?: ['title'=>'','content'=>'','version'=>0];
        };

        $html  = '<h1 class="h4 mb-3">Übergabeprotokoll – Schritt '.$step.'/4</h1>';
        $html .= '<form method="post" action="/protocols/wizard/save?step='.$step.'&draft='.$this->h($draft).'" enctype="multipart/form-data">';

        /* ------------------ Schritt 1: Adresse/Kopf/Eigentümer/HV ------------------ */
        if ($step===1) {
            $addr = (array)($data['address'] ?? ['city'=>'','postal_code'=>'','street'=>'','house_no'=>'','unit_label'=>'','floor'=>'']);
            $typ  = (string)($d['type'] ?? 'einzug');

            // Adresse & WE
            $html .= '<div class="card mb-3"><div class="card-body"><h2 class="h6 mb-3">Adresse & Wohneinheit</h2><div class="row g-3">';
            $html .= '<div class="col-md-5"><label class="form-label">Ort *</label><input class="form-control" name="address[city]" value="'.$this->h((string)$addr['city']).'"></div>';
            $html .= '<div class="col-md-2"><label class="form-label">PLZ</label><input class="form-control" name="address[postal_code]" value="'.$this->h((string)$addr['postal_code']).'"></div>';
            $html .= '<div class="col-md-5"><label class="form-label">Straße *</label><input class="form-control" name="address[street]" value="'.$this->h((string)$addr['street']).'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Haus‑Nr. *</label><input class="form-control" name="address[house_no]" value="'.$this->h((string)$addr['house_no']).'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">WE‑Bezeichnung *</label><input class="form-control" name="address[unit_label]" value="'.$this->h((string)$addr['unit_label']).'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Etage</label><input class="form-control" name="address[floor]" value="'.$this->h((string)$addr['floor']).'"></div>';
            $html .= '</div></div></div>';

            // Kopf
            $html .= '<div class="card mb-3"><div class="card-body"><h2 class="h6 mb-3">Protokoll‑Kopf</h2><div class="row g-3">';
            $html .= '<div class="col-md-4"><label class="form-label">Art *</label><select class="form-select" name="type">';
            foreach ([['einzug','Einzugsprotokoll'],['auszug','Auszugsprotokoll'],['zwischen','Zwischenabnahme']] as $opt){
                $sel = ($typ===$opt[0])?' selected':'';
                $html .= '<option value="'.$opt[0].'"'.$sel.'>'.$opt[1].'</option>';
            }
            $html .= '</select></div>';
            $html .= '<div class="col-md-5"><label class="form-label">Mietername *</label><input class="form-control" name="tenant_name" value="'.$this->h((string)($d['tenant_name'] ?? '')).'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Zeitstempel</label><input class="form-control" type="datetime-local" name="timestamp" value="'.$this->h((string)($data['timestamp'] ?? '')).'"></div>';
            $html .= '</div></div></div>';

            // Eigentümer
            $ownerSnap = (array)($data['owner'] ?? ['name'=>'','company'=>'','address'=>'','email'=>'','phone'=>'']);
            $html .= '<div class="card mb-3"><div class="card-body"><h2 class="h6 mb-3">Eigentümer</h2><div class="row g-3">';
            $html .= '<div class="col-md-6"><label class="form-label">Eigentümer (bestehend)</label><select class="form-select" name="owner_id"><option value="">— bitte wählen —</option>';
            foreach ($owners as $o){ $label=$o['name'].(!empty($o['company'])?' ('.$o['company'].')':''); $sel=(($d['owner_id'] ?? '')===$o['id'])?' selected':''; $html.='<option value="'.$this->h($o['id']).'"'.$sel.'>'.$this->h($label).'</option>'; }
            $html .= '</select></div>';
            $html .= '<div class="col-md-6"><div class="form-text">oder neuen Eigentümer erfassen:</div></div>';
            $html .= '<div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="owner_new[name]" value="'.$this->h((string)$ownerSnap['name']).'"></div>';
            $html .= '<div class="col-md-6"><label class="form-label">Firma</label><input class="form-control" name="owner_new[company]" value="'.$this->h((string)$ownerSnap['company']).'"></div>';
            $html .= '<div class="col-md-12"><label class="form-label">Adresse</label><input class="form-control" name="owner_new[address]" value="'.$this->h((string)$ownerSnap['address']).'"></div>';
            $html .= '<div class="col-md-6"><label class="form-label">E‑Mail</label><input class="form-control" type="email" name="owner_new[email]" value="'.$this->h((string)$ownerSnap['email']).'"></div>';
            $html .= '<div class="col-md-6"><label class="form-label">Telefon</label><input class="form-control" name="owner_new[phone]" value="'.$this->h((string)$ownerSnap['phone']).'"></div>';
            $html .= '</div></div></div>';

            // Hausverwaltung
            $html .= '<div class="card mb-3"><div class="card-body"><h2 class="h6 mb-3">Hausverwaltung</h2><div class="row g-3">';
            $html .= '<div class="col-md-6"><label class="form-label">Hausverwaltung</label><select class="form-select" name="manager_id"><option value="">— bitte wählen —</option>';
            foreach ($managers as $m){ $label=$m['name'].(!empty($m['company'])?' ('.$m['company'].')':''); $sel=(($d['manager_id'] ?? '')===$m['id'])?' selected':''; $html.='<option value="'.$this->h($m['id']).'"'.$sel.'>'.$this->h($label).'</option>'; }
            $html .= '</select></div>';
            $html .= '<div class="col-md-6"><div class="form-text">Stammdaten unter <a href="/settings">Einstellungen</a> pflegbar.</div></div>';
            $html .= '</div></div></div>';

            $html .= '<div class="d-flex justify-content-between"><a class="btn btn-ghost" href="/protocols"><i class="bi bi-x-lg"></i> Abbrechen</a><button class="btn btn-primary btn-lg">Weiter</button></div>';
        }

        /* ------------------ Schritt 2: Räume (+ Fotos, add/remove) ------------------ */
        if ($step===2) {
            $rooms = (array)($data['rooms'] ?? []);
            if (!$rooms) $rooms[] = ['name'=>'','state'=>'','smell'=>'','accepted'=>false,'wmz_no'=>'','wmz_val'=>''];
            $html .= '<div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Räume</h2><button type="button" id="add-room" class="btn btn-sm btn-outline-primary">+ Raum</button></div>';

            $i = 0;
            foreach ($rooms as $r) { $i++;
                $html .= '<div class="card mb-3 room-item"><div class="card-body"><div class="row g-3">';
                $html .= '<div class="col-md-4"><label class="form-label">Raumname</label><input class="form-control" name="rooms['.$i.'][name]" value="'.$this->h((string)($r['name'] ?? '')).'" list="room-presets"></div>';
                $html .= '<div class="col-md-4"><label class="form-label">Geruch</label><input class="form-control" name="rooms['.$i.'][smell]" value="'.$this->h((string)($r['smell'] ?? '')).'"></div>';
                $html .= '<div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="rooms['.$i.'][accepted]" '.(!empty($r['accepted'])?'checked':'').'> <label class="form-check-label">Abnahme erfolgt</label></div></div>';
                $html .= '<div class="col-12"><label class="form-label">IST‑Zustand</label><textarea class="form-control" rows="3" name="rooms['.$i.'][state]">'.$this->h((string)($r['state'] ?? '')).'</textarea></div>';
                $html .= '<div class="col-md-6"><label class="form-label">WMZ Nummer</label><input class="form-control" name="rooms['.$i.'][wmz_no]" value="'.$this->h((string)($r['wmz_no'] ?? '')).'"></div>';
                $html .= '<div class="col-md-6"><label class="form-label">WMZ Stand</label><input class="form-control" name="rooms['.$i.'][wmz_val]" value="'.$this->h((string)($r['wmz_val'] ?? '')).'" inputmode="decimal" pattern="^[0-9]+([.,][0-9]{1,3})?$"></div>';
                $html .= '<div class="col-12"><label class="form-label">Fotos (JPG/PNG, max.10MB)</label><input class="form-control" type="file" name="room_photos['.$i.'][]" multiple accept="image/*"></div>';
                $html .= '<div class="col-12 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-room">Entfernen</button></div>';
                $html .= '</div></div></div>';
            }

            // Template + JS
            $html .= '<template id="room-template"><div class="card mb-3 room-item"><div class="card-body"><div class="row g-3">';
            $html .= '<div class="col-md-4"><label class="form-label">Raumname</label><input class="form-control" name="rooms[__IDX__][name]" list="room-presets"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">Geruch</label><input class="form-control" name="rooms[__IDX__][smell]"></div>';
            $html .= '<div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="rooms[__IDX__][accepted]"> <label class="form-check-label">Abnahme erfolgt</label></div></div>';
            $html .= '<div class="col-12"><label class="form-label">IST‑Zustand</label><textarea class="form-control" rows="3" name="rooms[__IDX__][state]"></textarea></div>';
            $html .= '<div class="col-md-6"><label class="form-label">WMZ Nummer</label><input class="form-control" name="rooms[__IDX__][wmz_no]"></div>';
            $html .= '<div class="col-md-6"><label class="form-label">WMZ Stand</label><input class="form-control" name="rooms[__IDX__][wmz_val]" inputmode="decimal" pattern="^[0-9]+([.,][0-9]{1,3})?$"></div>';
            $html .= '<div class="col-12"><label class="form-label">Fotos (JPG/PNG, max.10MB)</label><input class="form-control" type="file" name="room_photos[__IDX__][]" multiple accept="image/*"></div>';
            $html .= '<div class="col-12 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-room">Entfernen</button></div>';
            $html .= '</div></div></div></template>';

            $html .= '<script>
(function(){
  var addBtn=document.getElementById("add-room"), tpl=document.getElementById("room-template");
  function nextIndex(){ var max=0; document.querySelectorAll(\'[name^="rooms["][name$="[name]"]\').forEach(function(i){ var m=i.name.match(/^rooms\\[(\\d+)\\]/); if(m) max=Math.max(max, parseInt(m[1],10)); }); return max+1; }
  function wireRemove(root){ root.querySelectorAll(".remove-room").forEach(function(b){ if(b._wired)return; b._wired=true; b.addEventListener("click", function(){ var card=b.closest(".room-item"); if(card) card.remove(); }); }); }
  wireRemove(document);
  if(addBtn && tpl){ addBtn.addEventListener("click", function(){ var idx=nextIndex(); var div=document.createElement("div"); div.innerHTML=tpl.innerHTML.replace(/__IDX__/g,String(idx)); tpl.parentNode.insertBefore(div.firstChild, tpl); wireRemove(document); }); }
})();
</script>';

            $html .= '<div class="d-flex justify-content-between mt-2"><a class="btn btn-ghost" href="/protocols/wizard?step=1&draft='.$this->h($draft).'"><i class="bi bi-arrow-left"></i> Zurück</a><button class="btn btn-primary btn-lg">Weiter</button></div>';
        }

        /* ------------------ Schritt 3: Zähler ------------------ */
        if ($step===3) {
            $labels=[
                'strom_we'=>'Strom (Wohneinheit)','strom_allg'=>'Strom (Haus allgemein)',
                'gas_we'=>'Gas (Wohneinheit)','gas_allg'=>'Gas (Haus allgemein)',
                'wasser_kueche_kalt'=>'Wasser Küche (kalt)','wasser_kueche_warm'=>'Wasser Küche (warm)',
                'wasser_bad_kalt'=>'Wasser Bad (kalt)','wasser_bad_warm'=>'Wasser Bad (warm)',
                'wasser_wm'=>'Wasser Waschmaschine (blau)'
            ];
            $meters=(array)($data['meters'] ?? []);
            $html .= '<h2 class="h6 mb-3">Zählerstände</h2>';
            foreach ($labels as $k=>$lbl){
                $m = $meters[$k] ?? ['no'=>'','val'=>''];
                $html .= '<div class="row g-3 align-items-end mb-2">';
                $html .= '<div class="col-md-5"><label class="form-label">'.$this->h($lbl).' – Nummer</label><input class="form-control" name="meters['.$k.'][no]" value="'.$this->h((string)$m['no']).'"></div>';
                $html .= '<div class="col-md-5"><label class="form-label">'.$this->h($lbl).' – Stand</label><input class="form-control" name="meters['.$k.'][val]" value="'.$this->h((string)$m['val']).'"></div>';
                $html .= '</div>';
            }
            $html .= '<div class="d-flex justify-content-between mt-2"><a class="btn btn-ghost" href="/protocols/wizard?step=2&draft='.$this->h($draft).'"><i class="bi bi-arrow-left"></i> Zurück</a><button class="btn btn-primary btn-lg">Weiter</button></div>';
        }

        /* ------------------ Schritt 4: Bank + Dritte Person + Einwilligungen + DocuSign ------------------ */
        if ($step===4) {
            $meta = (array)($data['meta'] ?? ['consents'=>[]]);
            $cons = (array)($meta['consents'] ?? []);
            $kh = $getLatest('kaution_hinweis');
            $rtD= $getLatest('datenschutz');
            $rtE= $getLatest('entsorgung');
            $rtM= $getLatest('marketing');

            $bank=(array)($meta['bank'] ?? ['bank'=>'','iban'=>'','holder'=>'']);
            $tc  =(array)($meta['tenant_contact'] ?? ['email'=>'','phone'=>'']);
            $na  =(array)($meta['tenant_new_addr'] ?? ['street'=>'','house_no'=>'','postal_code'=>'','city'=>'']);
            $third=(string)($meta['third_attendee'] ?? '');

            $html .= '<h2 class="h6 mb-2">Weitere Angaben</h2>';
            $html .= '<div class="alert alert-info small">Die Angabe der Bankverbindung dient zur Rückzahlung einer Mietkaution. Siehe hierzu die Angaben im Mietvertrag und Hinweise im Protokoll.</div>';
            if (!empty($kh['title']) || !empty($kh['content'])) {
                $html .= '<div class="card mb-3"><div class="card-body small"><div class="fw-semibold mb-1">'.$this->h((string)$kh['title']).' (v'.(int)($kh['version']??0).')</div>'.$kh['content'].'</div></div>';
            }

            $html .= '<div class="row g-3">';
            $html .= '<div class="col-md-4"><label class="form-label">Bank</label><input class="form-control" name="meta[bank][bank]" value="'.$this->h((string)$bank['bank']).'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">IBAN</label><input class="form-control" name="meta[bank][iban]" value="'.$this->h((string)$bank['iban']).'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">Kontoinhaber</label><input class="form-control" name="meta[bank][holder]" value="'.$this->h((string)$bank['holder']).'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">Mieter E‑Mail</label><input class="form-control" type="email" name="meta[tenant_contact][email]" value="'.$this->h((string)$tc['email']).'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">Mieter Telefon</label><input class="form-control" name="meta[tenant_contact][phone]" value="'.$this->h((string)$tc['phone']).'"></div>';
            $html .= '<div class="col-12"><label class="form-label">Neue Meldeadresse</label></div>';
            $html .= '<div class="col-md-6"><input class="form-control" placeholder="Straße" name="meta[tenant_new_addr][street]" value="'.$this->h((string)$na['street']).'"></div>';
            $html .= '<div class="col-md-2"><input class="form-control" placeholder="Haus‑Nr." name="meta[tenant_new_addr][house_no]" value="'.$this->h((string)$na['house_no']).'"></div>';
            $html .= '<div class="col-md-2"><input class="form-control" placeholder="PLZ" name="meta[tenant_new_addr][postal_code]" value="'.$this->h((string)$na['postal_code']).'"></div>';
            $html .= '<div class="col-md-2"><input class="form-control" placeholder="Ort" name="meta[tenant_new_addr][city]" value="'.$this->h((string)$na['city']).'"></div>';

            // Dritte Person & DocuSign
            $html .= '<div class="col-12"><label class="form-label">Dritte anwesende Person (optional)</label><input class="form-control" name="meta[third_attendee]" value="'.$this->h($third).'" placeholder="Name / Rolle"></div>';
            $html .= '</div>';

            // Einwilligungen (voll)
            $html .= '<h2 class="h6 mt-3">Einwilligungen</h2>';
            // Datenschutz
            $html .= '<div class="mb-2"><div class="small fw-semibold mb-1">Datenschutzerklärung (v'.(int)($rtD['version']??0).')</div><div class="border p-2 small">'.$rtD['content'].'</div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="meta[consents][privacy]" '.(!empty($cons['privacy'])?'checked':'').'> <label class="form-check-label">Ich habe die Datenschutzerklärung gelesen und akzeptiere sie.</label></div></div>';
            // Entsorgung
            $html .= '<div class="mb-2"><div class="small fw-semibold mb-1">Einverständnis Entsorgung (v'.(int)($rtE['version']??0).')</div><div class="border p-2 small">'.$rtE['content'].'</div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="meta[consents][disposal]" '.(!empty($cons['disposal'])?'checked':'').'> <label class="form-check-label">Ich stimme zu.</label></div></div>';
            // Marketing
            $html .= '<div class="mb-2"><div class="small fw-semibold mb-1">E‑Mail‑Marketing (v'.(int)($rtM['version']??0).')</div><div class="border p-2 small">'.$rtM['content'].'</div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="meta[consents][marketing]" '.(!empty($cons['marketing'])?'checked':'').'> <label class="form-check-label">Ich willige ein.</label></div></div>';

            // DocuSign‑Platzhalter
            $html .= '<h2 class="h6 mt-3">Unterschriften (DocuSign)</h2><div class="row g-3 small">';
            $html .= '<div class="col-md-4"><div class="border p-3" style="height:90px"><div class="fw-semibold">Mieter</div><div class="text-muted">Platzhalter für DocuSign‑Signatur</div></div></div>';
            $html .= '<div class="col-md-4"><div class="border p-3" style="height:90px"><div class="fw-semibold">Eigentümer</div><div class="text-muted">Platzhalter für DocuSign‑Signatur</div></div></div>';
            $html .= '<div class="col-md-4"><div class="border p-3" style="height:90px"><div class="fw-semibold">Dritte Person</div><div class="text-muted">Platzhalter (optional)</div></div></div>';
            $html .= '</div>';

            $html .= '<div class="d-flex justify-content-between mt-3"><a class="btn btn-ghost" href="/protocols/wizard?step=3&draft='.$this->h($draft).'"><i class="bi bi-arrow-left"></i> Zurück</a><button class="btn btn-primary btn-lg">Weiter</button></div>';
        }

        $html .= '</form>';
        View::render('Protokoll Wizard', $html);
    }

    /** Speichert Schritt 1–4 in protocol_drafts.data und leitet weiter. */
    public function save(): void
    {
        Auth::requireAuth();
        $pdo   = Database::pdo();
        $step  = max(1, min(4, (int)($_GET['step'] ?? 1)));
        $draft = (string)($_GET['draft'] ?? '');

        $st=$pdo->prepare("SELECT * FROM protocol_drafts WHERE id=? AND status='draft'");
        $st->execute([$draft]); $d=$st->fetch(PDO::FETCH_ASSOC);
        if(!$d){ Flash::add('error','Entwurf nicht gefunden.'); header('Location:/protocols'); return; }

        $data=json_decode((string)($d['data']??'{}'), true) ?: [];

        if ($step===1) {
            $addr=(array)($_POST['address'] ?? []);
            $city=trim((string)($addr['city']??'')); $street=trim((string)($addr['street']??'')); $house=trim((string)($addr['house_no']??'')); $postal=trim((string)($addr['postal_code']??'')); $unit=trim((string)($addr['unit_label']??'')); $floor=trim((string)($addr['floor']??''));
            $type=(string)($_POST['type'] ?? ($d['type'] ?? 'einzug')); $tenant=(string)($_POST['tenant_name'] ?? ($d['tenant_name'] ?? '')); $ts=(string)($_POST['timestamp'] ?? ($data['timestamp'] ?? ''));

            if ($city==='' || $street==='' || $house==='') { Flash::add('error','Bitte Ort, Straße und Haus‑Nr. angeben.'); header('Location:/protocols/wizard?step=1&draft='.$draft); return; }

            // objects/units
            $so=$pdo->prepare("SELECT id FROM objects WHERE city=? AND street=? AND house_no=? LIMIT 1");
            $so->execute([$city,$street,$house]); $objectId=(string)($so->fetchColumn() ?: '');
            if ($objectId==='') {
                $objectId=(string)$pdo->query('SELECT UUID()')->fetchColumn();
                $pdo->prepare("INSERT INTO objects (id,city,postal_code,street,house_no,created_at) VALUES (?,?,?,?,?,NOW())")->execute([$objectId,$city,($postal!==''?$postal:null),$street,$house]);
            } elseif ($postal!=='') {
                $pdo->prepare("UPDATE objects SET postal_code=? WHERE id=? AND (postal_code IS NULL OR postal_code='')")->execute([$postal,$objectId]);
            }
            $su=$pdo->prepare("SELECT id FROM units WHERE object_id=? AND label=? LIMIT 1");
            $su->execute([$objectId,$unit]); $unitId=(string)($su->fetchColumn() ?: '');
            if ($unitId==='') {
                $unitId=(string)$pdo->query('SELECT UUID()')->fetchColumn();
                $pdo->prepare("INSERT INTO units (id,object_id,label,floor,created_at) VALUES (?,?,?, ?, NOW())")->execute([$unitId,$objectId,$unit,($floor!==''?$floor:null)]);
            } elseif ($floor!=='') {
                $pdo->prepare("UPDATE units SET floor=? WHERE id=? AND (floor IS NULL OR floor='')")->execute([$floor,$unitId]);
            }

            // owner
            $ownerId=null; $ownerSnap=['name'=>'','company'=>'','address'=>'','email'=>'','phone'=>''];
            $ownerIdPost=(string)($_POST['owner_id'] ?? '');
            if ($ownerIdPost!=='') {
                $q=$pdo->prepare('SELECT name,company,address,email,phone FROM owners WHERE id=?'); $q->execute([$ownerIdPost]); $ownerId=$ownerIdPost; $ownerSnap=$q->fetch(PDO::FETCH_ASSOC) ?: $ownerSnap;
            } else {
                $on=(array)($_POST['owner_new'] ?? []);
                if (trim((string)($on['name'] ?? ''))!=='') {
                    $ownerId=(string)$pdo->query('SELECT UUID()')->fetchColumn();
                    $pdo->prepare('INSERT INTO owners (id,name,company,address,email,phone,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([$ownerId,(string)$on['name'],(string)($on['company']??null),(string)($on['address']??null),(string)($on['email']??null),(string)($on['phone']??null)]);
                    $ownerSnap=['name'=>(string)$on['name'],'company'=>(string)($on['company']??''),'address'=>(string)($on['address']??''),'email'=>(string)($on['email']??''),'phone'=>(string)($on['phone']??'')];
                }
            }
            $managerId = (string)($_POST['manager_id'] ?? '');

            $data['address']=['city'=>$city,'postal_code'=>$postal,'street'=>$street,'house_no'=>$house,'unit_label'=>$unit,'floor'=>$floor];
            $data['timestamp']=$ts;
            $data['owner']=$ownerSnap;

            $pdo->prepare("UPDATE protocol_drafts SET unit_id=?, type=?, tenant_name=?, owner_id=?, manager_id=?, data=?, updated_at=NOW() WHERE id=?")
                ->execute([$unitId,$type,$tenant,($ownerId?:null),($managerId?:null), json_encode($data, JSON_UNESCAPED_UNICODE), $draft]);

            header('Location:/protocols/wizard?step=2&draft='.$draft); return;
        }

        if ($step===2) {
            $data['rooms'] = is_array($_POST['rooms'] ?? null) ? (array)$_POST['rooms'] : [];
            $pdo->prepare("UPDATE protocol_drafts SET data=?, updated_at=NOW() WHERE id=?")->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $draft]);
            header('Location:/protocols/wizard?step=3&draft='.$draft); return;
        }

        if ($step===3) {
            $data['meters'] = is_array($_POST['meters'] ?? null) ? (array)$_POST['meters'] : [];
            $pdo->prepare("UPDATE protocol_drafts SET data=?, updated_at=NOW() WHERE id=?")->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $draft]);
            header('Location:/protocols/wizard?step=4&draft='.$draft); return;
        }

        if ($step===4) {
            $keys=[]; if (!empty($_POST['keys']) && is_array($_POST['keys'])) {
                foreach ($_POST['keys'] as $k) { $keys[]=['label'=>trim((string)($k['label'] ?? '')),'qty'=>(int)($k['qty'] ?? 0),'no'=>trim((string)($k['no'] ?? ''))]; }
            }
            $meta=(array)($_POST['meta'] ?? []);
            $data['keys']=$keys;
            $data['meta']=[
                'notes'=>(string)($meta['notes'] ?? ''),
                'bank'           => (array)($meta['bank'] ?? []),
                'tenant_contact' => (array)($meta['tenant_contact'] ?? []),
                'tenant_new_addr'=> (array)($meta['tenant_new_addr'] ?? []),
                'third_attendee' => (string)($meta['third_attendee'] ?? ''),
                'consents'=>[
                    'privacy'  => !empty($meta['consents']['privacy']),
                    'marketing'=> !empty($meta['consents']['marketing']),
                    'disposal' => !empty($meta['consents']['disposal']),
                ],
                'owner_send'   => !empty($meta['owner_send']),
                'manager_send' => !empty($meta['manager_send']),
            ];
            $pdo->prepare("UPDATE protocol_drafts SET data=?, updated_at=NOW() WHERE id=?")->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $draft]);
            header('Location:/protocols/wizard/review?draft='.$draft); return;
        }

        Flash::add('error','Unbekannter Schritt.'); header('Location:/protocols');
    }

    public function review(): void
    {
        Auth::requireAuth();
        $pdo=Database::pdo();
        $draft=(string)($_GET['draft'] ?? '');
        $st=$pdo->prepare("SELECT * FROM protocol_drafts WHERE id=? AND status='draft'");
        $st->execute([$draft]); $d=$st->fetch(PDO::FETCH_ASSOC);
        if(!$d){ Flash::add('error','Entwurf nicht gefunden.'); header('Location:/protocols'); return; }
        $data=json_decode((string)($d['data']??'{}'), true) ?: [];

        $unitTitle='—';
        if (!empty($d['unit_id'])) {
            $q=$pdo->prepare("SELECT u.label,o.city,o.street,o.house_no FROM units u JOIN objects o ON o.id=u.object_id WHERE u.id=?");
            $q->execute([$d['unit_id']]); if($r=$q->fetch(PDO::FETCH_ASSOC)){
                $unitTitle=$r['city'].', '.$r['street'].' '.$r['house_no'].' – '.$r['label'];
            }
        }

        ob_start(); ?>
        <h1 class="h5 mb-3">Review</h1>
        <dl class="row">
          <dt class="col-sm-3">Wohneinheit</dt><dd class="col-sm-9"><?= htmlspecialchars($unitTitle) ?></dd>
          <dt class="col-sm-3">Art</dt><dd class="col-sm-9"><?= htmlspecialchars(($d['type']==='einzug'?'Einzugsprotokoll':($d['type']==='auszug'?'Auszugsprotokoll':'Zwischenabnahme'))) ?></dd>
          <dt class="col-sm-3">Mieter</dt><dd class="col-sm-9"><?= htmlspecialchars((string)($d['tenant_name'] ?? '')) ?></dd>
          <dt class="col-sm-3">Zeitstempel</dt><dd class="col-sm-9"><?= htmlspecialchars((string)($data['timestamp'] ?? '')) ?></dd>
        </dl>
        <form method="post" action="/protocols/wizard/finish?draft=<?= htmlspecialchars($draft) ?>">
          <button class="btn btn-success btn-lg">Abschließen & Speichern</button>
          <a class="btn btn-ghost" href="/protocols/wizard?step=4&draft=<?= htmlspecialchars($draft) ?>">Zurück</a>
        </form>
        <?php
        View::render('Review', ob_get_clean());
    }

    public function finish(): void
    {
        Auth::requireAuth();
        $pdo=Database::pdo();
        $draft=(string)($_GET['draft'] ?? '');
        $st=$pdo->prepare("SELECT * FROM protocol_drafts WHERE id=? AND status='draft'");
        $st->execute([$draft]); $d=$st->fetch(PDO::FETCH_ASSOC);
        if(!$d){ Flash::add('error','Entwurf nicht gefunden.'); header('Location:/protocols'); return; }
        $data=json_decode((string)($d['data']??'{}'), true) ?: [];

        if (empty($d['unit_id']) || empty($d['type'])) {
            Flash::add('error','Bitte Schritt 1 vervollständigen.'); header('Location:/protocols/wizard?step=1&draft='.$draft); return;
        }

        $pid=(string)$pdo->query('SELECT UUID()')->fetchColumn();
        $pdo->prepare("INSERT INTO protocols (id,unit_id,type,tenant_name,payload,owner_id,manager_id,created_at) VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([$pid,(string)$d['unit_id'],(string)$d['type'],(string)($d['tenant_name'] ?? ''), json_encode($data, JSON_UNESCAPED_UNICODE), ($d['owner_id'] ?? null), ($d['manager_id'] ?? null)]);
        $pdo->prepare("INSERT INTO protocol_versions (id,protocol_id,version_no,data,created_by,created_at) VALUES (UUID(),?,?,?,?,NOW())")
            ->execute([$pid,1,json_encode($data, JSON_UNESCAPED_UNICODE),(string)(\App\Auth::user()['email'] ?? 'system')]);

        try { $pdo->prepare("UPDATE protocol_files SET protocol_id=?, draft_id=NULL WHERE draft_id=?")->execute([$pid,$draft]); } catch (\Throwable $e) {}
        $pdo->prepare("UPDATE protocol_drafts SET status='finished', updated_at=NOW() WHERE id=?")->execute([$draft]);

        Flash::add('success','Protokoll gespeichert (v1).');
        header('Location:/protocols/edit?id='.$pid);
    }
}
