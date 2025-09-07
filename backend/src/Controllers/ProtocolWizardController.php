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
    
    /**
     * Generiert das Signatur-Modal HTML
     */
    private function getSignatureModal(): string
    {
        return '
        <!-- Signature Modal -->
        <div class="modal fade" id="signatureModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Unterschrift erfassen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <canvas id="signatureCanvas" 
                                    style="border: 2px solid #dee2e6; border-radius: 4px; cursor: crosshair; width: 100%; max-width: 700px;"
                                    width="700" height="200">
                            </canvas>
                        </div>
                        <div class="text-muted small text-center">
                            <i class="bi bi-info-circle"></i> Bitte unterschreiben Sie mit der Maus oder dem Finger im Feld oben
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="clearSignature">
                            <i class="bi bi-eraser"></i> Löschen
                        </button>
                        <button type="button" class="btn btn-primary" id="saveModalSignature">
                            <i class="bi bi-check-lg"></i> Unterschrift speichern
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Signature Pad Integration -->
        <script src="/assets/signature-pad.js"></script>
        <script>
        let signaturePad = null;
        let currentTarget = null;
        
        function openSignatureDialog(target) {
            currentTarget = target;
            const modal = new bootstrap.Modal(document.getElementById("signatureModal"));
            modal.show();
            
            // Initialize signature pad if not already done
            if (!signaturePad) {
                signaturePad = new SignaturePad("signatureCanvas", {
                    strokeStyle: "#000033",
                    lineWidth: 2
                });
                
                // Clear button
                document.getElementById("clearSignature").addEventListener("click", function() {
                    signaturePad.clear();
                });
                
                // Save button
                document.getElementById("saveModalSignature").addEventListener("click", function() {
                    if (signaturePad.isEmpty()) {
                        alert("Bitte unterschreiben Sie zuerst.");
                        return;
                    }
                    
                    const dataURL = signaturePad.toDataURL();
                    
                    // Save to hidden field
                    document.getElementById("signature_" + currentTarget).value = dataURL;
                    
                    // Update preview
                    const previewContainer = document.querySelector("#signature_" + currentTarget).closest(".card").querySelector(".text-muted.mb-3, img");
                    if (previewContainer) {
                        const imgHtml = \'<img src="\' + dataURL + \'" class="img-fluid mb-2" style="max-height: 60px;">\' +
                                       \'<p class="small text-muted mb-2">Unterzeichnet</p>\';
                        previewContainer.parentElement.innerHTML = imgHtml + 
                            \'<button type="button" class="btn btn-sm btn-outline-primary" onclick="openSignatureDialog(\\\'\'+ currentTarget + \'\\\')">\'  +
                            \'<i class="bi bi-pen me-1"></i>Ändern</button>\';
                    }
                    
                    // Update button text
                    const button = document.querySelector("button[onclick=\"openSignatureDialog(\'" + currentTarget + "\')\"]" );
                    if (button) {
                        button.innerHTML = \'<i class="bi bi-pen me-1"></i>Ändern\';
                    }
                    
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById("signatureModal")).hide();
                    signaturePad.clear();
                });
            }
            
            // Clear pad for new signature
            signaturePad.clear();
        }
        </script>
        ';
    }

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

            // Digitale Unterschriften (Open Source oder DocuSign)
            $signatures = (array)($data['signatures'] ?? []);
            $signatureProvider = \App\Settings::get('signature_provider', 'local');
            
            $html .= '<h2 class="h6 mt-3">Digitale Unterschriften</h2>';
            
            if ($signatureProvider === 'local') {
                // Lokale Signatur-Integration
                $html .= '<div class="alert alert-info small">';
                $html .= '<i class="bi bi-info-circle me-2"></i>';
                $html .= 'Die digitalen Unterschriften können nach dem Speichern des Protokolls hinzugefügt werden. ';
                $html .= 'Sie erfüllen die Anforderungen gemäß §126a BGB und sind für Wohnungsübergabeprotokolle rechtlich ausreichend.';
                $html .= '</div>';
                
                $html .= '<div class="row g-3">';
                
                // Mieter Unterschrift
                $html .= '<div class="col-md-4">';
                $html .= '<div class="card">';
                $html .= '<div class="card-body text-center">';
                $html .= '<h6 class="card-title">Mieter</h6>';
                if (!empty($signatures['tenant'])) {
                    $html .= '<img src="' . $this->h($signatures['tenant']) . '" class="img-fluid mb-2" style="max-height: 60px;">';
                    $html .= '<p class="small text-muted mb-2">Unterzeichnet</p>';
                } else {
                    $html .= '<div class="text-muted mb-3" style="height: 60px; display: flex; align-items: center; justify-content: center;">';
                    $html .= '<i class="bi bi-pen" style="font-size: 2rem;"></i>';
                    $html .= '</div>';
                }
                $html .= '<button type="button" class="btn btn-sm btn-outline-primary" onclick="openSignatureDialog(\'tenant\')">';
                $html .= '<i class="bi bi-pen me-1"></i>' . (empty($signatures['tenant']) ? 'Unterschrift hinzufügen' : 'Ändern');
                $html .= '</button>';
                $html .= '<input type="hidden" name="signatures[tenant]" id="signature_tenant" value="' . $this->h($signatures['tenant'] ?? '') . '">';
                $html .= '<div class="mt-2">';
                $html .= '<input type="text" class="form-control form-control-sm" name="signatures[tenant_name]" placeholder="Name des Mieters" value="' . $this->h($signatures['tenant_name'] ?? '') . '">';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Eigentümer/Vermieter Unterschrift
                $html .= '<div class="col-md-4">';
                $html .= '<div class="card">';
                $html .= '<div class="card-body text-center">';
                $html .= '<h6 class="card-title">Eigentümer/Vermieter</h6>';
                if (!empty($signatures['landlord'])) {
                    $html .= '<img src="' . $this->h($signatures['landlord']) . '" class="img-fluid mb-2" style="max-height: 60px;">';
                    $html .= '<p class="small text-muted mb-2">Unterzeichnet</p>';
                } else {
                    $html .= '<div class="text-muted mb-3" style="height: 60px; display: flex; align-items: center; justify-content: center;">';
                    $html .= '<i class="bi bi-pen" style="font-size: 2rem;"></i>';
                    $html .= '</div>';
                }
                $html .= '<button type="button" class="btn btn-sm btn-outline-primary" onclick="openSignatureDialog(\'landlord\')">';
                $html .= '<i class="bi bi-pen me-1"></i>' . (empty($signatures['landlord']) ? 'Unterschrift hinzufügen' : 'Ändern');
                $html .= '</button>';
                $html .= '<input type="hidden" name="signatures[landlord]" id="signature_landlord" value="' . $this->h($signatures['landlord'] ?? '') . '">';
                $html .= '<div class="mt-2">';
                $html .= '<input type="text" class="form-control form-control-sm" name="signatures[landlord_name]" placeholder="Name des Vermieters" value="' . $this->h($signatures['landlord_name'] ?? '') . '">';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Dritte Person (optional)
                $html .= '<div class="col-md-4">';
                $html .= '<div class="card">';
                $html .= '<div class="card-body text-center">';
                $html .= '<h6 class="card-title">Dritte Person (optional)</h6>';
                if (!empty($signatures['witness'])) {
                    $html .= '<img src="' . $this->h($signatures['witness']) . '" class="img-fluid mb-2" style="max-height: 60px;">';
                    $html .= '<p class="small text-muted mb-2">Unterzeichnet</p>';
                } else {
                    $html .= '<div class="text-muted mb-3" style="height: 60px; display: flex; align-items: center; justify-content: center;">';
                    $html .= '<i class="bi bi-pen" style="font-size: 2rem;"></i>';
                    $html .= '</div>';
                }
                $html .= '<button type="button" class="btn btn-sm btn-outline-primary" onclick="openSignatureDialog(\'witness\')">';
                $html .= '<i class="bi bi-pen me-1"></i>' . (empty($signatures['witness']) ? 'Unterschrift hinzufügen' : 'Ändern');
                $html .= '</button>';
                $html .= '<input type="hidden" name="signatures[witness]" id="signature_witness" value="' . $this->h($signatures['witness'] ?? '') . '">';
                $html .= '<div class="mt-2">';
                $html .= '<input type="text" class="form-control form-control-sm" name="signatures[witness_name]" placeholder="Name der dritten Person" value="' . $this->h($signatures['witness_name'] ?? '') . '">';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                $html .= '</div>';
                
                // Signature Modal
                $html .= $this->getSignatureModal();
                
            } else {
                // DocuSign Integration (Platzhalter)
                $html .= '<div class="alert alert-warning small">';
                $html .= '<i class="bi bi-exclamation-triangle me-2"></i>';
                $html .= 'DocuSign ist als Provider ausgewählt. Die Unterschriften werden nach dem Speichern über DocuSign erfasst.';
                $html .= '</div>';
                $html .= '<div class="row g-3 small">';
                $html .= '<div class="col-md-4"><div class="border p-3" style="height:90px"><div class="fw-semibold">Mieter</div><div class="text-muted">Wird über DocuSign erfasst</div></div></div>';
                $html .= '<div class="col-md-4"><div class="border p-3" style="height:90px"><div class="fw-semibold">Eigentümer</div><div class="text-muted">Wird über DocuSign erfasst</div></div></div>';
                $html .= '<div class="col-md-4"><div class="border p-3" style="height:90px"><div class="fw-semibold">Dritte Person</div><div class="text-muted">Optional über DocuSign</div></div></div>';
                $html .= '</div>';
            }

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
            
            // Signaturen speichern
            $signatures = (array)($_POST['signatures'] ?? []);
            $data['signatures'] = [
                'tenant' => (string)($signatures['tenant'] ?? ''),
                'tenant_name' => (string)($signatures['tenant_name'] ?? ''),
                'landlord' => (string)($signatures['landlord'] ?? ''),
                'landlord_name' => (string)($signatures['landlord_name'] ?? ''),
                'witness' => (string)($signatures['witness'] ?? ''),
                'witness_name' => (string)($signatures['witness_name'] ?? ''),
                'timestamp' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ];
            
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
        
        <?php if (!empty($data['signatures'])): ?>
        <h2 class="h6 mt-3">Unterschriften</h2>
        <div class="row g-2">
          <?php if (!empty($data['signatures']['tenant'])): ?>
          <div class="col-md-4">
            <div class="card">
              <div class="card-body text-center">
                <h6 class="card-title">Mieter</h6>
                <img src="<?= htmlspecialchars($data['signatures']['tenant']) ?>" class="img-fluid" style="max-height: 60px;">
                <p class="small text-muted mb-0"><?= htmlspecialchars($data['signatures']['tenant_name'] ?? '') ?></p>
              </div>
            </div>
          </div>
          <?php endif; ?>
          <?php if (!empty($data['signatures']['landlord'])): ?>
          <div class="col-md-4">
            <div class="card">
              <div class="card-body text-center">
                <h6 class="card-title">Eigentümer/Vermieter</h6>
                <img src="<?= htmlspecialchars($data['signatures']['landlord']) ?>" class="img-fluid" style="max-height: 60px;">
                <p class="small text-muted mb-0"><?= htmlspecialchars($data['signatures']['landlord_name'] ?? '') ?></p>
              </div>
            </div>
          </div>
          <?php endif; ?>
          <?php if (!empty($data['signatures']['witness'])): ?>
          <div class="col-md-4">
            <div class="card">
              <div class="card-body text-center">
                <h6 class="card-title">Dritte Person</h6>
                <img src="<?= htmlspecialchars($data['signatures']['witness']) ?>" class="img-fluid" style="max-height: 60px;">
                <p class="small text-muted mb-0"><?= htmlspecialchars($data['signatures']['witness_name'] ?? '') ?></p>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <form method="post" action="/protocols/wizard/finish?draft=<?= htmlspecialchars($draft) ?>" class="mt-3">
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
        
        // Signaturen in separate Tabelle speichern wenn vorhanden
        if (!empty($data['signatures'])) {
            $this->saveSignaturesToDatabase($pdo, $pid, $data['signatures']);
        }

        try { $pdo->prepare("UPDATE protocol_files SET protocol_id=?, draft_id=NULL WHERE draft_id=?")->execute([$pid,$draft]); } catch (\Throwable $e) {}
        $pdo->prepare("UPDATE protocol_drafts SET status='finished', updated_at=NOW() WHERE id=?")->execute([$draft]);

        Flash::add('success','Protokoll gespeichert (v1).');
        header('Location:/protocols/edit?id='.$pid);
    }
    
    /**
     * Speichert Signaturen in die protocol_signatures Tabelle
     */
    private function saveSignaturesToDatabase(PDO $pdo, string $protocolId, array $signatures): void
    {
        // Stelle sicher dass die Tabelle existiert
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS protocol_signatures (
                    id VARCHAR(36) PRIMARY KEY,
                    protocol_id VARCHAR(36) NOT NULL,
                    signer_name VARCHAR(255) NOT NULL,
                    signer_role VARCHAR(50) NOT NULL,
                    signer_email VARCHAR(255),
                    signature_data LONGTEXT,
                    signature_hash VARCHAR(64),
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_by VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    is_valid BOOLEAN DEFAULT TRUE,
                    INDEX idx_protocol_id (protocol_id),
                    INDEX idx_signer_role (signer_role),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\PDOException $e) {
            // Tabelle existiert vermutlich schon
        }
        
        $user = \App\Auth::user();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Mieter-Signatur
        if (!empty($signatures['tenant'])) {
            $uuid = (string)$pdo->query('SELECT UUID()')->fetchColumn();
            $hash = hash('sha256', $signatures['tenant'] . $signatures['tenant_name'] . time());
            
            $stmt = $pdo->prepare("
                INSERT INTO protocol_signatures (
                    id, protocol_id, signer_name, signer_role, signature_data,
                    signature_hash, ip_address, user_agent, created_by, created_at
                ) VALUES (
                    ?, ?, ?, 'tenant', ?, ?, ?, ?, ?, NOW()
                )
            ");
            $stmt->execute([
                $uuid,
                $protocolId,
                $signatures['tenant_name'] ?? 'Mieter',
                $signatures['tenant'],
                $hash,
                $ipAddress,
                $userAgent,
                $user['email'] ?? 'system'
            ]);
        }
        
        // Vermieter-Signatur
        if (!empty($signatures['landlord'])) {
            $uuid = (string)$pdo->query('SELECT UUID()')->fetchColumn();
            $hash = hash('sha256', $signatures['landlord'] . $signatures['landlord_name'] . time());
            
            $stmt = $pdo->prepare("
                INSERT INTO protocol_signatures (
                    id, protocol_id, signer_name, signer_role, signature_data,
                    signature_hash, ip_address, user_agent, created_by, created_at
                ) VALUES (
                    ?, ?, ?, 'landlord', ?, ?, ?, ?, ?, NOW()
                )
            ");
            $stmt->execute([
                $uuid,
                $protocolId,
                $signatures['landlord_name'] ?? 'Vermieter',
                $signatures['landlord'],
                $hash,
                $ipAddress,
                $userAgent,
                $user['email'] ?? 'system'
            ]);
        }
        
        // Zeugen-Signatur
        if (!empty($signatures['witness'])) {
            $uuid = (string)$pdo->query('SELECT UUID()')->fetchColumn();
            $hash = hash('sha256', $signatures['witness'] . $signatures['witness_name'] . time());
            
            $stmt = $pdo->prepare("
                INSERT INTO protocol_signatures (
                    id, protocol_id, signer_name, signer_role, signature_data,
                    signature_hash, ip_address, user_agent, created_by, created_at
                ) VALUES (
                    ?, ?, ?, 'witness', ?, ?, ?, ?, ?, NOW()
                )
            ");
            $stmt->execute([
                $uuid,
                $protocolId,
                $signatures['witness_name'] ?? 'Zeuge',
                $signatures['witness'],
                $hash,
                $ipAddress,
                $userAgent,
                $user['email'] ?? 'system'
            ]);
        }
    }
}
