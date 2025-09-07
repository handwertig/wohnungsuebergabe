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
     * Generiert das Signatur-Script HTML (nur einmal pro Seite)
     */
    private function getSignatureScript(): string
    {
        return '
        <!-- Signature Pad Integration -->
        <script src="/assets/signature-pad.js"></script>
        <script>
        if (typeof window.signatureInitialized === "undefined") {
            window.signatureInitialized = true;
            let signaturePad = null;
            let currentTarget = null;
            
            window.openSignatureDialog = function(target) {
                currentTarget = target;
                const modal = new bootstrap.Modal(document.getElementById("signatureModal"));
                modal.show();
                
                if (!signaturePad) {
                    signaturePad = new SignaturePad("signatureCanvas", {
                        strokeStyle: "#000033",
                        lineWidth: 2
                    });
                    
                    document.getElementById("clearSignature").addEventListener("click", function() {
                        signaturePad.clear();
                    });
                    
                    document.getElementById("saveModalSignature").addEventListener("click", function() {
                        if (signaturePad.isEmpty()) {
                            alert("Bitte unterschreiben Sie zuerst.");
                            return;
                        }
                        
                        const dataURL = signaturePad.toDataURL();
                        document.getElementById("signature_" + currentTarget).value = dataURL;
                        
                        const previewContainer = document.querySelector("#signature_" + currentTarget).closest(".card").querySelector(".text-muted.mb-3, img");
                        if (previewContainer) {
                            const imgHtml = `<img src="${dataURL}" class="img-fluid mb-2" style="max-height: 60px;"><p class="small text-muted mb-2">Unterzeichnet</p>`;
                            previewContainer.parentElement.innerHTML = imgHtml + `<button type="button" class="btn btn-sm btn-outline-primary" onclick="openSignatureDialog(\'${currentTarget}\')"><i class="bi bi-pen me-1"></i>Ändern</button>`;
                        }
                        
                        const button = document.querySelector(`button[onclick="openSignatureDialog(\'${currentTarget}\')"]`);
                        if (button) {
                            button.innerHTML = `<i class="bi bi-pen me-1"></i>Ändern`;
                        }
                        
                        bootstrap.Modal.getInstance(document.getElementById("signatureModal")).hide();
                        signaturePad.clear();
                    });
                }
                
                signaturePad.clear();
            };
        }
        </script>
        ';
    }
    
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

        /* ------------------ Schritt 4: Schlüssel + Bank + Dritte Person + Einwilligungen + DocuSign ------------------ */
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
            $keys = (array)($data['keys'] ?? []);

            // SCHLÜSSEL-SEKTION
            $html .= '<h2 class="h6 mb-3">Schlüssel</h2>';
            $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
            $html .= '<span class="text-muted small">Erfassen Sie alle übergebenen Schlüssel</span>';
            $html .= '<button type="button" id="add-key" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus"></i> Schlüssel hinzufügen</button>';
            $html .= '</div>';
            
            if (empty($keys)) {
                $html .= '<div class="alert alert-info" id="no-keys-info">';
                $html .= '<i class="bi bi-info-circle me-2"></i>';
                $html .= 'Noch keine Schlüssel erfasst. Verwenden Sie den Button oben, um Schlüssel hinzuzufügen.';
                $html .= '</div>';
            }
            
            $html .= '<div id="keys-container">';
            $keyIndex = 0;
            foreach ($keys as $key) {
                $keyIndex++;
                $html .= '<div class="card mb-2 key-item">';
                $html .= '<div class="card-body">';
                $html .= '<div class="row g-3 align-items-center">';
                $html .= '<div class="col-md-4">';
                $html .= '<label class="form-label">Bezeichnung</label>';
                $html .= '<input class="form-control" name="keys[' . $keyIndex . '][label]" value="' . $this->h($key['label'] ?? '') . '" placeholder="z.B. Haustür, Wohnung, Keller">';
                $html .= '</div>';
                $html .= '<div class="col-md-3">';
                $html .= '<label class="form-label">Anzahl</label>';
                $html .= '<input class="form-control" type="number" min="1" name="keys[' . $keyIndex . '][qty]" value="' . $this->h($key['qty'] ?? 1) . '">';
                $html .= '</div>';
                $html .= '<div class="col-md-3">';
                $html .= '<label class="form-label">Schlüssel-Nr.</label>';
                $html .= '<input class="form-control" name="keys[' . $keyIndex . '][no]" value="' . $this->h($key['no'] ?? '') . '" placeholder="Optional">';
                $html .= '</div>';
                $html .= '<div class="col-md-2 d-flex align-items-end">';
                $html .= '<button type="button" class="btn btn-outline-danger btn-sm remove-key w-100">';
                $html .= '<i class="bi bi-trash"></i> Entfernen';
                $html .= '</button>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
            
            // Template für neue Schlüssel
            $html .= '<template id="key-template">';
            $html .= '<div class="card mb-2 key-item">';
            $html .= '<div class="card-body">';
            $html .= '<div class="row g-3 align-items-center">';
            $html .= '<div class="col-md-4">';
            $html .= '<label class="form-label">Bezeichnung</label>';
            $html .= '<input class="form-control" name="keys[__IDX__][label]" placeholder="z.B. Haustür, Wohnung, Keller">';
            $html .= '</div>';
            $html .= '<div class="col-md-3">';
            $html .= '<label class="form-label">Anzahl</label>';
            $html .= '<input class="form-control" type="number" min="1" name="keys[__IDX__][qty]" value="1">';
            $html .= '</div>';
            $html .= '<div class="col-md-3">';
            $html .= '<label class="form-label">Schlüssel-Nr.</label>';
            $html .= '<input class="form-control" name="keys[__IDX__][no]" placeholder="Optional">';
            $html .= '</div>';
            $html .= '<div class="col-md-2 d-flex align-items-end">';
            $html .= '<button type="button" class="btn btn-outline-danger btn-sm remove-key w-100">';
            $html .= '<i class="bi bi-trash"></i> Entfernen';
            $html .= '</button>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</template>';
            
            // JavaScript für Schlüssel-Management
            $html .= '
<script>
document.addEventListener("DOMContentLoaded", function() {
  var addBtn = document.getElementById("add-key");
  var container = document.getElementById("keys-container");
  var template = document.getElementById("key-template");
  var noKeysInfo = document.getElementById("no-keys-info");
  
  console.log("Schlüssel-Management initialisiert", {addBtn: addBtn, container: container, template: template, noKeysInfo: noKeysInfo});
  
  function getNextIndex() {
    var max = 0;
    var inputs = document.querySelectorAll("[name^=\\"keys[\\"][name$=\\"[label]\\"]");
    inputs.forEach(function(input) {
      var match = input.name.match(/^keys\\[(\\d+)\\]/);
      if (match) {
        var num = parseInt(match[1], 10);
        if (num > max) max = num;
      }
    });
    return max + 1;
  }
  
  function wireRemove() {
    var removeButtons = document.querySelectorAll(".remove-key");
    removeButtons.forEach(function(btn) {
      if (btn.dataset.wired) return;
      btn.dataset.wired = "true";
      btn.addEventListener("click", function() {
        var keyItem = btn.closest(".key-item");
        if (keyItem) {
          keyItem.remove();
          var remainingKeys = container.querySelectorAll(".key-item");
          if (remainingKeys.length === 0 && noKeysInfo) {
            noKeysInfo.style.display = "block";
          }
        }
      });
    });
  }
  
  // Bestehende Remove-Buttons verknüpfen
  wireRemove();
  
  // Add-Button Event
  if (addBtn && template && container) {
    addBtn.addEventListener("click", function(e) {
      e.preventDefault();
      console.log("Schlüssel hinzufügen geklickt");
      
      var idx = getNextIndex();
      console.log("Nächster Index:", idx);
      
      var templateContent = template.innerHTML;
      var newContent = templateContent.replace(/__IDX__/g, String(idx));
      
      var tempDiv = document.createElement("div");
      tempDiv.innerHTML = newContent;
      var newElement = tempDiv.firstElementChild;
      
      container.appendChild(newElement);
      wireRemove();
      
      // Info-Box verstecken
      if (noKeysInfo) {
        noKeysInfo.style.display = "none";
      }
      
      console.log("Schlüssel hinzugefügt");
    });
  } else {
    console.error("Erforderliche Elemente nicht gefunden:", {addBtn: addBtn, template: template, container: container});
  }
});
</script>';
            
            $html .= '<hr class="my-4">';
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
                
                // Signature Modal und Script
                $html .= $this->getSignatureModal();
                $html .= $this->getSignatureScript();
                
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

    /**
     * Zeigt eine vollständige Review-Übersicht aller 4 Wizard-Schritte
     * Inklusive: Grunddaten, Räume, Zählerstände, Schlüssel, Bankdaten, 
     * Kontaktdaten, Einwilligungen und digitale Unterschriften
     * 
     * WICHTIG: Zeigt ALLE Bereiche an, auch wenn sie leer sind, 
     * damit Benutzer sehen können was fehlt
     */
    public function review(): void
    {
        Auth::requireAuth();
        $pdo=Database::pdo();
        $draft=(string)($_GET['draft'] ?? '');
        
        if (empty($draft)) {
            Flash::add('error','Draft-ID fehlt in URL.');
            header('Location:/protocols');
            return;
        }
        
        $st=$pdo->prepare("SELECT * FROM protocol_drafts WHERE id=? AND status='draft'");
        $st->execute([$draft]); 
        $d=$st->fetch(PDO::FETCH_ASSOC);
        
        if(!$d){ 
            Flash::add('error','Entwurf nicht gefunden.'); 
            header('Location:/protocols'); 
            return; 
        }
        
        // Sicherer JSON-Decode mit Fallback
        $data = [];
        if (!empty($d['data'])) {
            $decoded = json_decode((string)$d['data'], true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        
        // Debug-Logging für Troubleshooting
        error_log('[ReviewDebug] Draft ID: ' . $draft);
        error_log('[ReviewDebug] Raw data: ' . ($d['data'] ?? 'NULL'));
        error_log('[ReviewDebug] Parsed data keys: ' . implode(', ', array_keys($data)));

        // Hole Eigentümer und Hausverwaltung Daten
        $ownerInfo = '';
        $managerInfo = '';
        
        if (!empty($d['owner_id'])) {
            $q=$pdo->prepare("SELECT name, company, address, email, phone FROM owners WHERE id=?");
            $q->execute([$d['owner_id']]);
            if($r=$q->fetch(PDO::FETCH_ASSOC)){
                $ownerInfo = $r['name'];
                if (!empty($r['company'])) $ownerInfo .= ' (' . $r['company'] . ')';
                if (!empty($r['address'])) $ownerInfo .= ', ' . $r['address'];
                if (!empty($r['email'])) $ownerInfo .= ', ' . $r['email'];
                if (!empty($r['phone'])) $ownerInfo .= ', ' . $r['phone'];
            }
        } elseif (!empty($data['owner'])) {
            $owner = $data['owner'];
            $ownerInfo = $owner['name'] ?? '';
            if (!empty($owner['company'])) $ownerInfo .= ' (' . $owner['company'] . ')';
            if (!empty($owner['address'])) $ownerInfo .= ', ' . $owner['address'];
            if (!empty($owner['email'])) $ownerInfo .= ', ' . $owner['email'];
            if (!empty($owner['phone'])) $ownerInfo .= ', ' . $owner['phone'];
        }
        
        if (!empty($d['manager_id'])) {
            $q=$pdo->prepare("SELECT name, company FROM managers WHERE id=?");
            $q->execute([$d['manager_id']]);
            if($r=$q->fetch(PDO::FETCH_ASSOC)){
                $managerInfo = $r['name'];
                if (!empty($r['company'])) $managerInfo .= ' (' . $r['company'] . ')';
            }
        }

        $unitTitle='—';
        if (!empty($d['unit_id'])) {
            $q=$pdo->prepare("SELECT u.label,u.floor,o.city,o.postal_code,o.street,o.house_no FROM units u JOIN objects o ON o.id=u.object_id WHERE u.id=?");
            $q->execute([$d['unit_id']]); if($r=$q->fetch(PDO::FETCH_ASSOC)){
                $unitTitle = '';
                if (!empty($r['postal_code'])) $unitTitle .= $r['postal_code'] . ' ';
                $unitTitle .= $r['city'] . ', ' . $r['street'] . ' ' . $r['house_no'];
                if (!empty($r['label'])) $unitTitle .= ' – ' . $r['label'];
                if (!empty($r['floor'])) $unitTitle .= ' (Etage: ' . $r['floor'] . ')';
            }
        }
        
        // Meter-Labels
        $meterLabels = [
            'strom_we' => 'Strom (Wohneinheit)',
            'strom_allg' => 'Strom (Haus allgemein)',
            'gas_we' => 'Gas (Wohneinheit)',
            'gas_allg' => 'Gas (Haus allgemein)',
            'wasser_kueche_kalt' => 'Wasser Küche (kalt)',
            'wasser_kueche_warm' => 'Wasser Küche (warm)',
            'wasser_bad_kalt' => 'Wasser Bad (kalt)',
            'wasser_bad_warm' => 'Wasser Bad (warm)',
            'wasser_wm' => 'Wasser Waschmaschine (blau)'
        ];

        ob_start(); ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 mb-0">Protokoll-Review</h1>
            <div class="text-muted small">Bitte prüfen Sie alle Angaben vor dem Abschluss</div>
        </div>
        
        <!-- Schritt 1: Grunddaten -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h6 mb-0"><i class="bi bi-1-circle me-2"></i>Grunddaten & Adresse</h2>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Wohneinheit</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($unitTitle) ?></dd>
                    
                    <dt class="col-sm-3">Art</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars(($d['type']==='einzug'?'Einzugsprotokoll':($d['type']==='auszug'?'Auszugsprotokoll':'Zwischenabnahme'))) ?></dd>
                    
                    <dt class="col-sm-3">Mieter</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars((string)($d['tenant_name'] ?? '')) ?></dd>
                    
                    <dt class="col-sm-3">Zeitstempel</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars((string)($data['timestamp'] ?? '')) ?></dd>
                    
                    <?php if ($ownerInfo): ?>
                    <dt class="col-sm-3">Eigentümer</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($ownerInfo) ?></dd>
                    <?php endif; ?>
                    
                    <?php if ($managerInfo): ?>
                    <dt class="col-sm-3">Hausverwaltung</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($managerInfo) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        
        <!-- Schritt 2: Räume -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="bi bi-2-circle me-2"></i>Räume (<?= count($data['rooms'] ?? []) ?>)</h2>
                <a href="/protocols/wizard?step=2&draft=<?= htmlspecialchars($draft) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Bearbeiten
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($data['rooms'])): ?>
                <div class="row g-3">
                    <?php foreach ($data['rooms'] as $i => $room): ?>
                    <?php if (!empty($room['name'])): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded p-3">
                            <h6 class="fw-bold mb-2"><?= htmlspecialchars($room['name']) ?></h6>
                            
                            <p class="small mb-2"><strong>Zustand:</strong> <?= !empty($room['state']) ? htmlspecialchars($room['state']) : '<span class="text-muted">Nicht angegeben</span>' ?></p>
                            
                            <p class="small mb-2"><strong>Geruch:</strong> <?= !empty($room['smell']) ? htmlspecialchars($room['smell']) : '<span class="text-muted">Nicht angegeben</span>' ?></p>
                            
                            <?php if (!empty($room['wmz_no']) || !empty($room['wmz_val'])): ?>
                            <p class="small mb-2"><strong>WMZ:</strong> 
                                <?= htmlspecialchars($room['wmz_no'] ?? 'Keine Nr.') ?> 
                                (Stand: <?= htmlspecialchars($room['wmz_val'] ?? '—') ?>)
                            </p>
                            <?php else: ?>
                            <p class="small mb-2"><strong>WMZ:</strong> <span class="text-muted">Nicht erfasst</span></p>
                            <?php endif; ?>
                            
                            <div class="d-flex align-items-center">
                                <span class="badge <?= !empty($room['accepted']) ? 'bg-success' : 'bg-warning' ?>">
                                    <?= !empty($room['accepted']) ? 'Abgenommen' : 'Nicht abgenommen' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Noch keine Räume erfasst.</strong>
                    <p class="mb-0 mt-2">Gehen Sie zu <a href="/protocols/wizard?step=2&draft=<?= htmlspecialchars($draft) ?>" class="alert-link">Schritt 2</a> um Räume hinzuzufügen.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Schritt 3: Zählerstände -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="bi bi-3-circle me-2"></i>Zählerstände</h2>
                <a href="/protocols/wizard?step=3&draft=<?= htmlspecialchars($draft) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Bearbeiten
                </a>
            </div>
            <div class="card-body">
                <?php 
                $meters = $data['meters'] ?? [];
                $hasAnyMeterData = false;
                if (!empty($meters)) {
                    foreach ($meters as $meter) {
                        if (!empty($meter['no']) || !empty($meter['val'])) {
                            $hasAnyMeterData = true;
                            break;
                        }
                    }
                }
                ?>
                
                <div class="row g-3">
                    <?php foreach ($meterLabels as $key => $label): ?>
                    <?php $meter = $meters[$key] ?? []; ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded p-3 <?= (empty($meter['no']) && empty($meter['val'])) ? 'bg-light' : '' ?>">
                            <h6 class="fw-bold mb-2"><?= htmlspecialchars($label) ?></h6>
                            <p class="small mb-1">
                                <strong>Nummer:</strong> 
                                <?= !empty($meter['no']) ? htmlspecialchars($meter['no']) : '<span class="text-muted">Nicht angegeben</span>' ?>
                            </p>
                            <p class="small mb-0">
                                <strong>Stand:</strong> 
                                <?= !empty($meter['val']) ? htmlspecialchars($meter['val']) : '<span class="text-muted">Nicht angegeben</span>' ?>
                            </p>
                            <?php if (empty($meter['no']) && empty($meter['val'])): ?>
                            <div class="mt-2">
                                <span class="badge bg-light text-dark"><i class="bi bi-clock"></i> Noch zu erfassen</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!$hasAnyMeterData): ?>
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Noch keine Zählerstände erfasst.</strong>
                    <p class="mb-0 mt-2">Gehen Sie zu <a href="/protocols/wizard?step=3&draft=<?= htmlspecialchars($draft) ?>" class="alert-link">Schritt 3</a> um Zählerstände einzutragen.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Schritt 4: Schlüssel & weitere Angaben -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="bi bi-4-circle me-2"></i>Schlüssel & weitere Angaben</h2>
                <a href="/protocols/wizard?step=4&draft=<?= htmlspecialchars($draft) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Bearbeiten
                </a>
            </div>
            <div class="card-body">
                
                <!-- Schlüssel -->
                <h6 class="mb-3">Schlüssel</h6>
                <?php if (!empty($data['keys'])): ?>
                <div class="row g-2 mb-4">
                    <?php foreach ($data['keys'] as $key): ?>
                    <?php if (!empty($key['label'])): ?>
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <div class="fw-bold"><?= htmlspecialchars($key['label']) ?></div>
                            <div class="small text-muted">Anzahl: <?= (int)($key['qty'] ?? 1) ?></div>
                            <?php if (!empty($key['no'])): ?>
                            <div class="small text-muted">Nr.: <?= htmlspecialchars($key['no']) ?></div>
                            <?php else: ?>
                            <div class="small text-muted">Nr.: <span class="text-muted">Nicht angegeben</span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mb-4">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Noch keine Schlüssel erfasst.</strong>
                    <p class="mb-0 mt-2">Gehen Sie zu <a href="/protocols/wizard?step=4&draft=<?= htmlspecialchars($draft) ?>" class="alert-link">Schritt 4</a> um Schlüssel hinzuzufügen.</p>
                </div>
                <?php endif; ?>
                
                <!-- Bankdaten -->
                <h6 class="mb-3">Bankverbindung</h6>
                <?php 
                $bank = $data['meta']['bank'] ?? [];
                $hasBankData = !empty($bank['bank']) || !empty($bank['iban']) || !empty($bank['holder']);
                ?>
                <?php if ($hasBankData): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="small text-muted">Bank</div>
                        <div><?= !empty($bank['bank']) ? htmlspecialchars($bank['bank']) : '<span class="text-muted">Nicht angegeben</span>' ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">IBAN</div>
                        <div><?= !empty($bank['iban']) ? htmlspecialchars($bank['iban']) : '<span class="text-muted">Nicht angegeben</span>' ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Kontoinhaber</div>
                        <div><?= !empty($bank['holder']) ? htmlspecialchars($bank['holder']) : '<span class="text-muted">Nicht angegeben</span>' ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Keine Bankdaten erfasst.</strong>
                    <p class="mb-0 mt-2">Für Kautionsrückzahlungen können Sie in <a href="/protocols/wizard?step=4&draft=<?= htmlspecialchars($draft) ?>" class="alert-link">Schritt 4</a> Bankdaten hinterlegen.</p>
                </div>
                <?php endif; ?>
                
                <!-- Kontaktdaten -->
                <h6 class="mb-3">Kontaktdaten</h6>
                <?php 
                $contact = $data['meta']['tenant_contact'] ?? [];
                $hasContactData = !empty($contact['email']) || !empty($contact['phone']);
                ?>
                <?php if ($hasContactData): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="small text-muted">E-Mail</div>
                        <div><?= !empty($contact['email']) ? htmlspecialchars($contact['email']) : '<span class="text-muted">Nicht angegeben</span>' ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Telefon</div>
                        <div><?= !empty($contact['phone']) ? htmlspecialchars($contact['phone']) : '<span class="text-muted">Nicht angegeben</span>' ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Keine Kontaktdaten erfasst.</strong>
                    <p class="mb-0 mt-2">In <a href="/protocols/wizard?step=4&draft=<?= htmlspecialchars($draft) ?>" class="alert-link">Schritt 4</a> können Sie E-Mail und Telefonnummer hinterlegen.</p>
                </div>
                <?php endif; ?>
                
                <!-- Neue Meldeadresse -->
                <?php 
                $newAddr = $data['meta']['tenant_new_addr'] ?? [];
                if (!empty($newAddr['street']) || !empty($newAddr['city'])):
                ?>
                <h6 class="mb-3">Neue Meldeadresse</h6>
                <div class="mb-4">
                    <?php
                    $addrParts = [];
                    if (!empty($newAddr['street'])) $addrParts[] = $newAddr['street'];
                    if (!empty($newAddr['house_no'])) $addrParts[] = $newAddr['house_no'];
                    $street = implode(' ', $addrParts);
                    
                    $cityParts = [];
                    if (!empty($newAddr['postal_code'])) $cityParts[] = $newAddr['postal_code'];
                    if (!empty($newAddr['city'])) $cityParts[] = $newAddr['city'];
                    $city = implode(' ', $cityParts);
                    
                    if ($street) echo '<div>' . htmlspecialchars($street) . '</div>';
                    if ($city) echo '<div>' . htmlspecialchars($city) . '</div>';
                    ?>
                </div>
                <?php endif; ?>
                
                <!-- Dritte Person -->
                <?php if (!empty($data['meta']['third_attendee'])): ?>
                <h6 class="mb-3">Anwesende dritte Person</h6>
                <div class="mb-4">
                    <div><?= htmlspecialchars($data['meta']['third_attendee']) ?></div>
                </div>
                <?php endif; ?>
                
                <!-- Einwilligungen -->
                <?php 
                $consents = $data['meta']['consents'] ?? [];
                if (!empty($consents)):
                ?>
                <h6 class="mb-3">Einwilligungen</h6>
                <div class="mb-4">
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!empty($consents['privacy'])): ?>
                        <span class="badge bg-success">Datenschutz akzeptiert</span>
                        <?php endif; ?>
                        <?php if (!empty($consents['disposal'])): ?>
                        <span class="badge bg-success">Entsorgung akzeptiert</span>
                        <?php endif; ?>
                        <?php if (!empty($consents['marketing'])): ?>
                        <span class="badge bg-success">Marketing akzeptiert</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        
        <!-- Unterschriften -->
        <?php if (!empty($data['signatures'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h6 mb-0"><i class="bi bi-pen me-2"></i>Digitale Unterschriften</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php if (!empty($data['signatures']['tenant'])): ?>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <h6 class="mb-3">Mieter</h6>
                            <img src="<?= htmlspecialchars($data['signatures']['tenant']) ?>" class="img-fluid mb-2" style="max-height: 60px; border: 1px solid #dee2e6;">
                            <div class="small text-muted"><?= htmlspecialchars($data['signatures']['tenant_name'] ?? 'Mieter') ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($data['signatures']['landlord'])): ?>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <h6 class="mb-3">Eigentümer/Vermieter</h6>
                            <img src="<?= htmlspecialchars($data['signatures']['landlord']) ?>" class="img-fluid mb-2" style="max-height: 60px; border: 1px solid #dee2e6;">
                            <div class="small text-muted"><?= htmlspecialchars($data['signatures']['landlord_name'] ?? 'Vermieter') ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($data['signatures']['witness'])): ?>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <h6 class="mb-3">Dritte Person</h6>
                            <img src="<?= htmlspecialchars($data['signatures']['witness']) ?>" class="img-fluid mb-2" style="max-height: 60px; border: 1px solid #dee2e6;">
                            <div class="small text-muted"><?= htmlspecialchars($data['signatures']['witness_name'] ?? 'Zeuge') ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Aktionen -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Protokoll abschließen</h6>
                        <div class="small text-muted">Nach dem Abschluss wird das Protokoll gespeichert und kann bearbeitet werden.</div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-success btn-lg me-2" 
                                onclick="finishProtocol()">
                            <i class="bi bi-check-lg me-1"></i>Abschließen & Speichern
                        </button>
                        
                        <a class="btn btn-outline-secondary" href="/protocols/wizard?step=4&draft=<?= htmlspecialchars($draft) ?>">
                            <i class="bi bi-arrow-left me-1"></i>Zurück
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Verstecktes POST-Formular für Abschluss -->
        <form id="finishForm" method="post" action="/protocols/wizard/finish?draft=<?= htmlspecialchars($draft) ?>" style="display: none;">
            <input type="hidden" name="draft_id" value="<?= htmlspecialchars($draft) ?>">
            <input type="hidden" name="action" value="finish_protocol">
        </form>
        
        <script>
        function finishProtocol() {
            // Button deaktivieren um Doppel-Submissions zu verhindern
            const button = event.target;
            button.disabled = true;
            button.innerHTML = '<i class="spinner-border spinner-border-sm me-1"></i>Speichere...';
            
            // Form abrufen und absenden
            const form = document.getElementById('finishForm');
            form.noValidate = true;
            
            // Kurzer Timeout für visuelles Feedback
            setTimeout(() => {
                form.submit();
            }, 100);
        }
        </script>
        
        <?php
        View::render('Protokoll Review', ob_get_clean());
    }

    /**
     * Schließt den Wizard ab und speichert das finale Protokoll
     * Erstellt ein neues Protokoll in der Datenbank mit allen Wizard-Daten
     * Unterstützt sowohl GET- als auch POST-Requests für maximale Kompatibilität
     */
    public function finish(): void
    {
        Auth::requireAuth();
        $pdo=Database::pdo();
        $draft=(string)($_GET['draft'] ?? '');
        
        // Fallback: draft_id aus POST falls GET leer ist
        if (empty($draft) && !empty($_POST['draft_id'])) {
            $draft = (string)$_POST['draft_id'];
        }
        
        if (empty($draft)) {
            Flash::add('error','Draft-ID fehlt.');
            header('Location:/protocols');
            return;
        }
        
        $st=$pdo->prepare("SELECT * FROM protocol_drafts WHERE id=? AND status='draft'");
        $st->execute([$draft]); $d=$st->fetch(PDO::FETCH_ASSOC);
        
        if(!$d){ 
            Flash::add('error','Entwurf nicht gefunden.'); 
            header('Location:/protocols'); 
            return; 
        }
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

        try { 
            $pdo->prepare("UPDATE protocol_files SET protocol_id=?, draft_id=NULL WHERE draft_id=?")->execute([$pid,$draft]); 
        } catch (\Throwable $e) {
            // Log error if needed, but don't stop execution
        }
        
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
