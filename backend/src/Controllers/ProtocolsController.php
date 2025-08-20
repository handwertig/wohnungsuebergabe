<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Flash;
use App\View;
use App\AuditLogger;
use App\Uploads;
use App\Validation;
use PDO;

final class ProtocolsController
{
    private static function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Übersicht (Accordion) */
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

        $sql = "SELECT o.city,o.street,o.house_no,u.id AS unit_id,u.label AS unit_label,
                       p.id AS protocol_id,p.type,p.tenant_name,
                       pv.version_no,pv.created_at AS version_created_at
                FROM protocols p
                JOIN units u ON u.id=p.unit_id
                JOIN objects o ON o.id=u.object_id
                LEFT JOIN protocol_versions pv ON pv.protocol_id=p.id
                WHERE $whereSql
                ORDER BY o.city,o.street,o.house_no,u.label,pv.created_at DESC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Gruppieren
        $tree = [];
        foreach ($rows as $r) {
            $objKey = $r['city'].'|'.$r['street'].'|'.$r['house_no'];
            $unitId = $r['unit_id'];
            if (!isset($tree[$objKey])) {
                $tree[$objKey] = ['title'=>$r['city'].', '.$r['street'].' '.$r['house_no'], 'units'=>[]];
            }
            if (!isset($tree[$objKey]['units'][$unitId])) {
                $tree[$objKey]['units'][$unitId] = ['label'=>'Einheit '.$r['unit_label'], 'versions'=>[]];
            }
            if ($r['version_no'] !== null) {
                $tree[$objKey]['units'][$unitId]['versions'][] = [
                    'protocol_id' => $r['protocol_id'],
                    'date'        => $r['version_created_at'],
                    'type'        => $r['type'],
                    'tenant'      => $r['tenant_name'],
                    'version_no'  => $r['version_no'],
                ];
            }
        }

        $html  = '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h1 class="h4 mb-0">Protokoll‑Übersicht</h1>';
        $exportUrl = '/protocols/export?'.http_build_query($_GET);
        $html .= '<div class="d-flex gap-2">';
        $html .= '<a class="btn btn-outline-secondary" href="'.$exportUrl.'">CSV‑Export</a>';
        $html .= '<a class="btn btn-primary" href="/protocols/wizard/start">Neues Protokoll</a>';
        $html .= '</div></div>';

        // Filter
        $html .= '<form class="row g-2 mb-3" method="get" action="/protocols">';
        $html .= '<div class="col-md-4"><label class="form-label">Suche (Mieter/Adresse)</label><input class="form-control" name="q" value="'.self::h($q).'"></div>';
        $html .= '<div class="col-md-3"><label class="form-label">Typ</label><select class="form-select" name="type">';
        foreach ([''=>'— alle —','einzug'=>'einzug','auszug'=>'auszug','zwischen'=>'zwischen'] as $val=>$lbl) {
            $sel = ($val === $type) ? ' selected' : '';
            $html .= '<option value="'.self::h($val).'"'.$sel.'>'.$lbl.'</option>';
        }
        $html .= '</select></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Filtern</button></div></form>';

        // Accordion
        $html .= '<div class="accordion" id="acc-objects">';
        if (!$tree) $html .= '<div class="text-muted">Keine Einträge.</div>';
        $i=0;
        foreach ($tree as $obj) {
            $i++; $oid='obj-'.$i;
            $html .= '<div class="accordion-item">';
            $html .= '<h2 class="accordion-header" id="h-'.$oid.'"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-'.$oid.'">'.self::h($obj['title']).'</button></h2>';
            $html .= '<div id="c-'.$oid.'" class="accordion-collapse collapse"><div class="accordion-body">';
            $html .= '<div class="accordion" id="acc-units-'.$oid.'">';
            $j=0;
            foreach (($obj['units']??[]) as $unit) {
                $j++; $uid=$oid.'-u-'.$j;
                $html .= '<div class="accordion-item">';
                $html .= '<h2 class="accordion-header" id="h-'.$uid.'"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-'.$uid.'">'.self::h($unit['label']).'</button></h2>';
                $html .= '<div id="c-'.$uid.'" class="accordion-collapse collapse"><div class="accordion-body">';
                if (!empty($unit['versions'])) {
                    $html .= '<div class="table-responsive"><table class="table table-sm align-middle">';
                    $html .= '<thead><tr><th>Datum</th><th>Art</th><th>Mieter</th><th class="text-end">Aktion</th></tr></thead><tbody>';
                    foreach ($unit['versions'] as $v) {
                        $badge = '<span class="badge bg-secondary">'.self::h($v['type']).'</span>';
                        if ($v['type']==='einzug')   $badge = '<span class="badge bg-success">Einzugsprotokoll</span>';
                        if ($v['type']==='auszug')   $badge = '<span class="badge bg-danger">Auszugsprotokoll</span>';
                        if ($v['type']==='zwischen') $badge = '<span class="badge bg-warning text-dark">Zwischenprotokoll</span>';
                        $html .= '<tr><td>'.self::h($v['date']).' (v'.self::h($v['version_no']).')</td><td>'.$badge.'</td><td>'.self::h($v['tenant']).'</td>';
                        $html .= '<td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/protocols/edit?id='.self::h($v['protocol_id']).'">Protokoll öffnen</a></td></tr>';
                    }
                    $html .= '</tbody></table></div>';
                } else {
                    $html .= '<div class="text-muted">Keine Versionen vorhanden.</div>';
                }
                $html .= '</div></div></div>';
            }
            $html .= '</div></div></div>';
        }
        $html .= '</div>';

        View::render('Protokolle – Akkordeon', $html);
    }

    /** CSV-Export */
    public function export(): void
    {
        Auth::requireAuth();
        $pdo=Database::pdo();
        $q=trim((string)($_GET['q']??'')); $type=(string)($_GET['type']??'');
        $where=['p.deleted_at IS NULL']; $params=[];
        if($type!=='' && in_array($type,['einzug','auszug','zwischen'],true)){ $where[]='p.type=?'; $params[]=$type; }
        if($q!==''){ $where[]='(p.tenant_name LIKE ? OR o.city LIKE ? OR o.street LIKE ? OR o.house_no LIKE ? OR u.label LIKE ?)';
            $like='%'.$q.'%'; array_push($params,$like,$like,$like,$like,$like); }
        $whereSql=implode(' AND ',$where);
        $sql="SELECT p.id,p.type,p.tenant_name,p.created_at,o.city,o.street,o.house_no,u.label AS unit_label
              FROM protocols p JOIN units u ON u.id=p.unit_id JOIN objects o ON o.id=u.object_id
              WHERE $whereSql ORDER BY o.city,o.street,o.house_no,u.label,p.created_at DESC";
        $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=protokolle_export.csv');
        $out=fopen('php://output','w'); fputcsv($out,['ID','Typ','Mieter','Ort','Straße','Hausnr.','WE','Erstellt']);
        foreach($rows as $r){ fputcsv($out,[$r['id'],$r['type'],$r['tenant_name'],$r['city'],$r['street'],$r['house_no'],$r['unit_label'],$r['created_at']]); }
        fclose($out); exit;
    }

    /** Editor (Tabs) inkl. Eigentümer/HV, Room-Keys, Foto-Uploads, Status/Versand unten */
    public function form(): void
    {
        Auth::requireAuth();
        $pdo=Database::pdo();
        $id=(string)($_GET['id']??''); if($id===''){ Flash::add('error','ID fehlt.'); header('Location:/protocols'); return; }

        $st=$pdo->prepare("SELECT p.*, u.label AS unit_label, o.city,o.street,o.house_no, p.owner_id, p.manager_id
                           FROM protocols p JOIN units u ON u.id=p.unit_id JOIN objects o ON o.id=u.object_id
                           WHERE p.id=? LIMIT 1");
        $st->execute([$id]); $p=$st->fetch(PDO::FETCH_ASSOC);
        if(!$p){ Flash::add('error','Protokoll nicht gefunden.'); header('Location:/protocols'); return; }

        // Stammdaten
        $owners   = $pdo->query("SELECT id,name,company FROM owners WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $managers = $pdo->query("SELECT id,name,company FROM managers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $payload=json_decode((string)($p['payload']??'{}'), true) ?: [];
        $addr=$payload['address']??[]; $rooms=$payload['rooms']??[]; $meters=$payload['meters']??[]; $keys=$payload['keys']??[]; $meta=$payload['meta']??[];

        // Room-Keys
        foreach ($rooms as $k=>$r) {
            if (!isset($r['key']) || $r['key']==='') {
                $rooms[$k]['key'] = isset($r['name']) ? 'r'.substr(sha1((string)$r['name'].$k),0,6) : 'r'.substr(sha1((string)microtime(true).$k),0,6);
            }
        }

        // Fotos je room_key (Thumb bevorzugt)
        $pf=$pdo->prepare("SELECT room_key, original_name, path, thumb_path FROM protocol_files WHERE protocol_id=? AND section='room_photo' ORDER BY created_at DESC");
        $pf->execute([$p['id']]); $files=$pf->fetchAll(PDO::FETCH_ASSOC);
        $byKey=[]; $base=realpath(__DIR__.'/../../storage/uploads');
        $url=function(string $abs) use($base):string{
            $real=realpath($abs)?:$abs;
            return ($base && str_starts_with($real,$base)) ? '/uploads/'.ltrim(substr($real, strlen($base)),'/') : '';
        };
        foreach($files as $f){ $rk=(string)($f['room_key']??''); if($rk==='') continue; $byKey[$rk][]=['name'=>$f['original_name'],'url'=>$url((string)($f['thumb_path']?:$f['path']))]; }

        $title=$p['city'].', '.$p['street'].' '.$p['house_no'].' – '.$p['unit_label'];

        $html  = '<h1 class="h5 mb-2">Protokoll bearbeiten</h1>';
        $html .= '<div class="text-muted mb-3">'.self::h($title).'</div>';

        $html .= '<form method="post" action="/protocols/save" class="needs-validation" novalidate enctype="multipart/form-data">';
        $html .= '<input type="hidden" name="id" value="'.self::h($p['id']).'">';

        // Tabs
        $html .= '<ul class="nav nav-tabs" role="tablist">';
        $html .= '<li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-kopf" type="button">Kopf</button></li>';
        $html .= '<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-raeume" type="button">Räume</button></li>';
        $html .= '<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-zaehler" type="button">Zähler</button></li>';
        $html .= '<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-schluessel" type="button">Schlüssel & Meta</button></li>';
        $html .= '</ul>';

        $html .= '<div class="tab-content border-start border-end border-bottom p-3">';

        // Tab Kopf
        $html .= '<div class="tab-pane fade show active" id="tab-kopf">';
        $html .= '<div class="row g-3">';
        // Art
        $opts = [['einzug','Einzugsprotokoll'],['auszug','Auszugsprotokoll'],['zwischen','Zwischenprotokoll']];
        $html .= '<div class="col-md-4"><label class="form-label">Art</label><select class="form-select" name="type">';
        foreach ($opts as $o) {
            $sel = ($p['type']===$o[0]) ? ' selected' : '';
            $html .= '<option value="'.self::h($o[0]).'"'.$sel.'>'.self::h($o[1]).'</option>';
        }
        $html .= '</select></div>';
        // Mieter
        $html .= '<div class="col-md-6"><label class="form-label">Mietername</label><input class="form-control" name="tenant_name" value="'.self::h($p['tenant_name'] ?? '').'" required></div>';
        // Zeit
        $html .= '<div class="col-md-2"><label class="form-label">Zeitstempel</label><input class="form-control" type="datetime-local" name="timestamp" value="'.self::h($payload['timestamp'] ?? '').'"></div>';
        $html .= '</div><hr>';

        // Adresse
        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-4"><label class="form-label">Ort</label><input class="form-control" name="address[city]" value="'.self::h($addr['city'] ?? '').'" required></div>';
        $html .= '<div class="col-md-2"><label class="form-label">PLZ</label><input class="form-control" name="address[postal_code]" value="'.self::h($addr['postal_code'] ?? '').'"></div>';
        $html .= '<div class="col-md-4"><label class="form-label">Straße</label><input class="form-control" name="address[street]" value="'.self::h($addr['street'] ?? '').'" required></div>';
        $html .= '<div class="col-md-2"><label class="form-label">Haus‑Nr.</label><input class="form-control" name="address[house_no]" value="'.self::h($addr['house_no'] ?? '').'" required></div>';
        $html .= '<div class="col-md-4"><label class="form-label">WE‑Bezeichnung</label><input class="form-control" name="address[unit_label]" value="'.self::h($addr['unit_label'] ?? '').'"></div>';
        $html .= '<div class="col-md-2"><label class="form-label">Etage</label><input class="form-control" name="address[floor]" value="'.self::h($addr['floor'] ?? '').'"></div>';
        $html .= '</div><hr>';

        // Eigentümer / HV
        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-6"><label class="form-label">Eigentümer</label><select class="form-select" name="owner_id"><option value="">— bitte wählen —</option>';
        foreach ($owners as $o) {
            $label = $o['name'].(!empty($o['company'])?' ('.$o['company'].')':'');
            $sel = (($p['owner_id'] ?? '') === $o['id']) ? ' selected' : '';
            $html .= '<option value="'.self::h($o['id']).'"'.$sel.'>'.self::h($label).'</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="col-md-6"><label class="form-label">Hausverwaltung</label><select class="form-select" name="manager_id"><option value="">— bitte wählen —</option>';
        foreach ($managers as $m) {
            $label = $m['name'].(!empty($m['company'])?' ('.$m['company'].')':'');
            $sel = (($p['manager_id'] ?? '') === $m['id']) ? ' selected' : '';
            $html .= '<option value="'.self::h($m['id']).'"'.$sel.'>'.self::h($label).'</option>';
        }
        $html .= '</select></div>';
        $html .= '</div>';
        $html .= '</div>'; // tab-kopf

        // Tab Räume
        $html .= '<div class="tab-pane fade" id="tab-raeume">';
        $html .= '<div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Räume</h6><button class="btn btn-sm btn-outline-primary" id="add-room-btn">+ Raum</button></div>';
        $html .= '<div id="rooms-wrap">';
        foreach ($rooms as $r) {
            $rk = (string)$r['key'];
            $html .= '<div class="card mb-3"><div class="card-body"><div class="row g-3">';
            $html .= '<input type="hidden" name="rooms['.self::h($rk).'][key]" value="'.self::h($rk).'">';
            $html .= '<div class="col-md-4"><label class="form-label">Raumname</label><input class="form-control" name="rooms['.self::h($rk).'][name]" value="'.self::h($r['name'] ?? '').'"></div>';
            $html .= '<div class="col-md-4"><label class="form-label">Geruch</label><input class="form-control" name="rooms['.self::h($rk).'][smell]" value="'.self::h($r['smell'] ?? '').'"></div>';
            $html .= '<div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="rooms['.self::h($rk).'][accepted]" '.(!empty($r['accepted'])?'checked':'').'> <label class="form-check-label">Abnahme erfolgt</label></div></div>';
            $html .= '<div class="col-12"><label class="form-label">IST‑Zustand</label><textarea class="form-control" rows="3" name="rooms['.self::h($rk).'][state]">'.self::h($r['state'] ?? '').'</textarea></div>';
            $html .= '<div class="col-md-6"><label class="form-label">WMZ Nummer</label><input class="form-control" name="rooms['.self::h($rk).'][wmz_no]" value="'.self::h($r['wmz_no'] ?? '').'"></div>';
            $html .= '<div class="col-md-6"><label class="form-label">WMZ Stand</label><input class="form-control" inputmode="decimal" pattern="^[0-9]+([.,][0-9]{1,3})?$" name="rooms['.self::h($rk).'][wmz_val]" value="'.self::h($r['wmz_val'] ?? '').'"></div>';
            $html .= '<div class="col-12"><label class="form-label">Fotos (JPG/PNG, max. 10MB)</label><input class="form-control" type="file" name="room_photos['.self::h($rk).'][]" multiple accept="image/*"></div>';
            $thumbs = $byKey[$rk] ?? [];
            if ($thumbs) {
                $html .= '<div class="col-12"><div class="d-flex flex-wrap gap-2">';
                foreach ($thumbs as $t) {
                    if (!empty($t['url'])) $html .= '<a href="'.self::h($t['url']).'" target="_blank"><img src="'.self::h($t['url']).'" style="width:120px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #ddd;"></a>';
                }
                $html .= '</div></div>';
            } else {
                $html .= '<div class="col-12 text-muted">Keine Fotos vorhanden.</div>';
            }
            $html .= '</div><div class="mt-2 text-end"><button class="btn btn-sm btn-outline-danger" data-remove-room>Entfernen</button></div></div></div>';
        }
        $html .= '</div>'; // rooms-wrap
        // Template
        $html .= '<template id="room-template"><div class="card mb-3"><div class="card-body"><div class="row g-3">';
        $html .= '<input type="hidden" name="rooms[__IDX__][key]" value="__IDX__">';
        $html .= '<div class="col-md-4"><label class="form-label">Raumname</label><input class="form-control" name="rooms[__IDX__][name]"></div>';
        $html .= '<div class="col-md-4"><label class="form-label">Geruch</label><input class="form-control" name="rooms[__IDX__][smell]"></div>';
        $html .= '<div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="rooms[__IDX__][accepted]"> <label class="form-check-label">Abnahme erfolgt</label></div></div>';
        $html .= '<div class="col-12"><label class="form-label">IST‑Zustand</label><textarea class="form-control" rows="3" name="rooms[__IDX__][state]"></textarea></div>';
        $html .= '<div class="col-md-6"><label class="form-label">WMZ Nummer</label><input class="form-control" name="rooms[__IDX__][wmz_no]"></div>';
        $html .= '<div class="col-md-6"><label class="form-label">WMZ Stand</label><input class="form-control" inputmode="decimal" pattern="^[0-9]+([.,][0-9]{1,3})?$" name="rooms[__IDX__][wmz_val]"></div>';
        $html .= '<div class="col-12"><label class="form-label">Fotos (JPG/PNG)</label><input class="form-control" type="file" name="room_photos[__IDX__][]" multiple accept="image/*"></div>';
        $html .= '</div><div class="mt-2 text-end"><button class="btn btn-sm btn-outline-danger" data-remove-room>Entfernen</button></div></div></div></template>';
        $html .= '</div>'; // tab-raeume

        // Zähler
        $labels = [
            'strom_we'=>'Strom (Wohneinheit)','strom_allg'=>'Strom (Haus allgemein)',
            'gas_we'=>'Gas (Wohneinheit)','gas_allg'=>'Gas (Haus allgemein)',
            'wasser_kueche_kalt'=>'Kaltwasser Küche (blau)','wasser_kueche_warm'=>'Warmwasser Küche (rot)',
            'wasser_bad_kalt'=>'Kaltwasser Bad (blau)','wasser_bad_warm'=>'Warmwasser Bad (rot)',
            'wasser_wm'=>'Wasserzähler Waschmaschine (blau)',
        ];
        $html .= '<div class="tab-pane fade" id="tab-zaehler">';
        foreach ($labels as $key=>$label) {
            $row = $meters[$key] ?? ['no'=>'','val'=>''];
            $html .= '<div class="row g-3 align-items-end mb-2">';
            $html .= '<div class="col-md-5"><label class="form-label">'.self::h($label).' – Nummer</label><input class="form-control" name="meters['.$key.'][no]" value="'.self::h($row['no']).'"></div>';
            $html .= '<div class="col-md-5"><label class="form-label">'.self::h($label).' – Stand</label><input class="form-control" inputmode="decimal" pattern="^[0-9]+([.,][0-9]{1,3})?$" name="meters['.$key.'][val]" value="'.self::h($row['val']).'"></div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        // Schlüssel & Meta
        $html .= '<div class="tab-pane fade" id="tab-schluessel">';
        $html .= '<h6>Schlüssel</h6><div id="keys-wrap">';
        $i=0; foreach ($keys as $k) { $i++;
            $html .= '<div class="row g-2 align-items-end mb-2">';
            $html .= '<div class="col-md-5"><label class="form-label">Bezeichnung</label><input class="form-control" name="keys['.$i.'][label]" value="'.self::h($k['label'] ?? '').'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Anzahl</label><input type="number" min="0" class="form-control" name="keys['.$i.'][qty]" value="'.self::h($k['qty'] ?? '0').'"></div>';
            $html .= '<div class="col-md-3"><label class="form-label">Schlüssel‑Nr.</label><input class="form-control" name="keys['.$i.'][no]" value="'.self::h($k['no'] ?? '').'"></div>';
            $html .= '<div class="col-md-1"><button class="btn btn-sm btn-outline-danger w-100" data-remove-key>−</button></div>';
            $html .= '</div>';
        }
        $html .= '</div><button class="btn btn-sm btn-outline-primary" id="add-key-btn">+ Schlüssel</button>';

        $bank = $meta['bank'] ?? ['bank'=>'','iban'=>'','holder'=>''];
        $tc   = $meta['tenant_contact'] ?? ['email'=>'','phone'=>''];
        $na   = $meta['tenant_new_addr'] ?? ['street'=>'','house_no'=>'','postal_code'=>'','city'=>''];
        $cons = $meta['consents'] ?? ['marketing'=>false,'disposal'=>false];
        $third= $meta['third_attendee'] ?? '';

        $html .= '<hr><h6>Weitere Angaben</h6><div class="row g-3">';
        $html .= '<div class="col-md-4"><label class="form-label">Bank</label><input class="form-control" name="meta[bank][bank]" value="'.self::h($bank['bank']).'"></div>';
        $html .= '<div class="col-md-4"><label class="form-label">IBAN</label><input class="form-control" name="meta[bank][iban]" value="'.self::h($bank['iban']).'"></div>';
        $html .= '<div class="col-md-4"><label class="form-label">Kontoinhaber</label><input class="form-control" name="meta[bank][holder]" value="'.self::h($bank['holder']).'"></div>';
        $html .= '<div class="col-md-4"><label class="form-label">Mieter E‑Mail</label><input type="email" class="form-control" name="meta[tenant_contact][email]" value="'.self::h($tc['email']).'"></div>';
        $html .= '<div class="col-md-4"><label class="form-label">Mieter Telefon</label><input class="form-control" name="meta[tenant_contact][phone]" value="'.self::h($tc['phone']).'"></div>';
        $html .= '<div class="col-12"><label class="form-label">Neue Meldeadresse</label></div>';
        $html .= '<div class="col-md-6"><label class="form-label">Straße</label><input class="form-control" name="meta[tenant_new_addr][street]" value="'.self::h($na['street']).'"></div>';
        $html .= '<div class="col-md-2"><label class="form-label">Haus‑Nr.</label><input class="form-control" name="meta[tenant_new_addr][house_no]" value="'.self::h($na['house_no']).'"></div>';
        $html .= '<div class="col-md-2"><label class="form-label">PLZ</label><input class="form-control" name="meta[tenant_new_addr][postal_code]" value="'.self::h($na['postal_code']).'"></div>';
        $html .= '<div class="col-md-2"><label class="form-label">Ort</label><input class="form-control" name="meta[tenant_new_addr][city]" value="'.self::h($na['city']).'"></div>';
        $html .= '<div class="col-12"><label class="form-label">Einwilligungen</label></div>';
        $html .= '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[consents][marketing]" '.(!empty($cons['marketing'])?'checked':'').'> <label class="form-check-label">E‑Mail‑Marketing (außerhalb Mietverhältnis)</label></div></div>';
        $html .= '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[consents][disposal]" '.(!empty($cons['disposal'])?'checked':'').'> <label class="form-check-label">Einverständnis Entsorgung zurückgelassener Gegenstände</label></div></div>';
        $html .= '</div>'; // weitere Angaben

        $html .= '<hr><h6>Versand</h6><div class="row g-3">';
        $html .= '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[owner_send]" '.(!empty($meta['owner_send'])?'checked':'').'> <label class="form-check-label">an Eigentümer senden</label></div></div>';
        $html .= '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="meta[manager_send]" '.(!empty($meta['manager_send'])?'checked':'').'> <label class="form-check-label">an Verwaltung senden</label></div></div>';
        $html .= '</div>';

        $html .= '<hr><h6>Dritte anwesende Person (optional)</h6><div class="row g-3">';
        $html .= '<div class="col-md-6"><input class="form-control" name="meta[third_attendee]" value="'.self::h($third).'"></div>';
        $html .= '</div>';

        $html .= '<div class="alert alert-info mt-3">Platzhalter für Unterschriften (Mieter, Eigentümer, optional dritte Person). DocuSign‑Versand folgt.</div>';

        $html .= '</div>'; // tab-schluessel
        $html .= '</div>'; // tab-content

        $html .= '<div class="kt-sticky-actions">
  <div>
    <a class="btn btn-ghost" href="/protocols"><i class="bi bi-x-lg"></i> Zurück</a>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-primary btn-lg"><i class="bi bi-save2"></i> Speichern (neue Version)</button>
  </div>
</div>
</form>';

        // Versand/Status-Log
        $evStmt = $pdo->prepare("SELECT type, message, created_at FROM protocol_events WHERE protocol_id=? ORDER BY created_at DESC LIMIT 50");
        $evStmt->execute([$p['id']]); $events = $evStmt->fetchAll(PDO::FETCH_ASSOC);
        $mlStmt = $pdo->prepare("SELECT recipient_type, to_email, subject, status, sent_at, created_at FROM email_log WHERE protocol_id=? ORDER BY created_at DESC LIMIT 50");
        $mlStmt->execute([$p['id']]); $mails = $mlStmt->fetchAll(PDO::FETCH_ASSOC);
        $pidEsc = self::h($p['id']);

        $html .= '<hr><h2 class="h6">Versand</h2><div class="d-flex gap-2 mb-3">';
        $html .= '<a class="btn btn-outline-primary" href="/protocols/send?protocol_id='.$pidEsc.'&to=owner">PDF an Eigentümer senden</a>';
        $html .= '<a class="btn btn-outline-primary" href="/protocols/send?protocol_id='.$pidEsc.'&to=manager">PDF an Hausverwaltung senden</a>';
        $html .= '<a class="btn btn-outline-primary" href="/protocols/send?protocol_id='.$pidEsc.'&to=tenant">PDF an Mieter senden</a>';
        $html .= '<a class="btn btn-outline-secondary" href="/protocols/pdf?protocol_id='.$pidEsc.'&version=latest" target="_blank">PDF ansehen</a>';
        $html .= '</div>';

        $html .= '<h2 class="h6">Status</h2><div class="row g-3">';
        // Events
        $html .= '<div class="col-md-6"><div class="card"><div class="card-body"><div class="fw-bold mb-2">Ereignisse</div>';
        if ($events) {
            $html .= '<ul class="list-group">';
            foreach ($events as $e) {
                $typ = self::h($e['type']);
                $msg = (isset($e['message']) && $e['message']!=='') ? ': '.self::h($e['message']) : '';
                $dt  = self::h($e['created_at']);
                $html .= '<li class="list-group-item d-flex justify-content-between"><span>'.$typ.$msg.'</span><span class="text-muted small">'.$dt.'</span></li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<div class="text-muted">Noch keine Ereignisse.</div>';
        }
        $html .= '</div></div></div>';

        // Mail Log
        $html .= '<div class="col-md-6"><div class="card"><div class="card-body"><div class="fw-bold mb-2">E‑Mail‑Versand</div>';
        if ($mails) {
            $html .= '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Empfänger</th><th>E‑Mail</th><th>Betreff</th><th>Status</th><th>Gesendet</th></tr></thead><tbody>';
            foreach ($mails as $m) {
                $html .= '<tr><td>'.self::h($m['recipient_type']).'</td><td>'.self::h($m['to_email']).'</td><td>'.self::h($m['subject']).'</td><td>'.self::h($m['status']).'</td><td>'.self::h($m['sent_at'] ?? $m['created_at']).'</td></tr>';
            }
            $html .= '</tbody></table></div>';
        } else {
            $html .= '<div class="text-muted">Noch kein Versand.</div>';
        }
        $html .= '</div></div></div></div>'; // row

        // Scripts
        $html .= '<script src="/assets/protocol_form.js"></script><script src="/assets/ui_enhancements.js"></script>';

        View::render('Protokoll – Bearbeiten', $html);
    }

    /** Speichern: Validierung, Payload, Foto-Uploads (mit thumb_path), Versionierung */
    public function save(): void
    {
        Auth::requireAuth();
        $pdo=Database::pdo();
        $id=(string)($_POST['id']??''); if($id===''){ Flash::add('error','ID fehlt.'); header('Location:/protocols'); return; }

        $type=(string)($_POST['type']??'einzug'); if(!in_array($type,['einzug','auszug','zwischen'],true)) $type='einzug';

        // Pflichtfelder je Typ prüfen
        $valErrors = Validation::protocolPostErrors($type, $_POST);
        if (!empty($valErrors)) {
            Flash::add('error','Bitte Eingaben prüfen: '.implode(' | ', $valErrors));
            header('Location:/protocols/edit?id='.$id); return;
        }

        $tenant=trim((string)($_POST['tenant_name']??'')); $timestamp=(string)($_POST['timestamp']??'');
        $addr=(array)($_POST['address']??[]);
        $ownerId = (string)($_POST['owner_id'] ?? '');
        $managerId = (string)($_POST['manager_id'] ?? '');

        // Räume mit key
        $rooms=[];
        foreach((array)($_POST['rooms']??[]) as $rk=>$r){
            $key=(string)($r['key'] ?? $rk);
            $rooms[]=[
                'key'=>$key,
                'name'=>trim((string)($r['name']??'')),
                'smell'=>trim((string)($r['smell']??'')),
                'state'=>trim((string)($r['state']??'')),
                'accepted'=>isset($r['accepted']),
                'wmz_no'=>trim((string)($r['wmz_no']??'')),
                'wmz_val'=>trim((string)($r['wmz_val']??'')),
            ];
        }

        $meters=(array)($_POST['meters']??[]);
        $keys=[];
        foreach((array)($_POST['keys']??[]) as $k){
            if(($k['label']??'')==='' && ($k['qty']??'')==='' && ($k['no']??'')==='') continue;
            $keys[]=['label'=>trim((string)($k['label']??'')),'qty'=>(int)($k['qty']??0),'no'=>trim((string)($k['no']??''))];
        }

        $metaPost=(array)($_POST['meta']??[]);
        $meta=['notes'=>trim((string)($metaPost['notes']??'')),'owner_send'=>!empty($metaPost['owner_send']),'manager_send'=>!empty($metaPost['manager_send'])];
        if (isset($metaPost['bank']))           $meta['bank'] = $metaPost['bank'];
        if (isset($metaPost['tenant_contact'])) $meta['tenant_contact'] = $metaPost['tenant_contact'];
        if (isset($metaPost['tenant_new_addr']))$meta['tenant_new_addr'] = $metaPost['tenant_new_addr'];
        if (isset($metaPost['consents']))       $meta['consents'] = $metaPost['consents'];
        if (isset($metaPost['third_attendee'])) $meta['third_attendee'] = $metaPost['third_attendee'];

        $payload=[
            'timestamp'=>$timestamp,
            'address'=>[
                'city'=>trim((string)($addr['city']??'')),
                'postal_code'=>trim((string)($addr['postal_code']??'')),
                'street'=>trim((string)($addr['street']??'')),
                'house_no'=>trim((string)($addr['house_no']??'')),
                'unit_label'=>trim((string)($addr['unit_label']??'')),
                'floor'=>trim((string)($addr['floor']??'')),
            ],
            'rooms'=>$rooms,'meters'=>$meters,'keys'=>$keys,'meta'=>$meta
        ];

        // Kopf & Payload & Owner/HV speichern
        $pdo->prepare('UPDATE protocols SET type=?, tenant_name=?, payload=?, owner_id=?, manager_id=?, updated_at=NOW() WHERE id=?')
            ->execute([$type, ($tenant!==''?$tenant:null), json_encode($payload, JSON_UNESCAPED_UNICODE), ($ownerId!==''?$ownerId:null), ($managerId!==''?$managerId:null), $id]);

        // Foto-Uploads je room_key (inkl. thumb_path)
        if (!empty($_FILES['room_photos']['name']) && is_array($_FILES['room_photos']['name'])) {
            foreach ($_FILES['room_photos']['name'] as $rk => $arr) {
                $count = count((array)$arr);
                for ($i=0; $i<$count; $i++) {
                    $file = [
                        'name'     => $_FILES['room_photos']['name'][$rk][$i] ?? null,
                        'type'     => $_FILES['room_photos']['type'][$rk][$i] ?? null,
                        'tmp_name' => $_FILES['room_photos']['tmp_name'][$rk][$i] ?? null,
                        'error'    => $_FILES['room_photos']['error'][$rk][$i] ?? null,
                        'size'     => $_FILES['room_photos']['size'][$rk][$i] ?? null,
                    ];
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                    try {
                        $saved = Uploads::saveProtocolFile($id, $file, 'room_photo', (string)$rk);
                        $pdo->prepare('INSERT INTO protocol_files (id,protocol_id,section,room_key,original_name,path,thumb_path,mime,size,created_at) VALUES (UUID(),?,?,?,?,?,?,?,NOW())')
                            ->execute([$id,$saved['section'],$saved['room_key'],$saved['name'],$saved['path'],($saved['thumb']??null),$saved['mime'],$saved['size']]);
                    } catch (\Throwable $e) {
                        Flash::add('error','Upload-Fehler: '.$e->getMessage());
                    }
                }
            }
        }

        // neue Version anlegen
        $stmt=$pdo->prepare('SELECT COALESCE(MAX(version_no),0)+1 AS v FROM protocol_versions WHERE protocol_id=?');
        $stmt->execute([$id]); $verNo=(int)$stmt->fetchColumn();
        $user=Auth::user();
        $pdo->prepare('INSERT INTO protocol_versions (id,protocol_id,version_no,data,created_by,created_at) VALUES (UUID(),?,?,?,?,NOW())')
            ->execute([$id,$verNo,json_encode($payload, JSON_UNESCAPED_UNICODE), $user['email'] ?? 'system']);

        AuditLogger::log('protocols',$id,'update',['type'=>$type,'tenant'=>$tenant,'version'=>$verNo]);
        Flash::add('success','Gespeichert. Neue Version v'.$verNo.' angelegt.');
        header('Location:/protocols/edit?id='.$id); exit;
    }

    public function delete(): void
    {
        Auth::requireAuth();
        $id=$_GET['id']??''; if($id){
            Database::pdo()->prepare('UPDATE protocols SET deleted_at=NOW() WHERE id=?')->execute([$id]);
            AuditLogger::log('protocols',$id,'soft_delete');
            Flash::add('success','Protokoll gelöscht (Soft-Delete).');
        }
        header('Location:/protocols'); exit;
    }
}
