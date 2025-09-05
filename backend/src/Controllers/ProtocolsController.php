<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\View;
use App\Settings;
use App\Flash;
use App\AuditLogger;
use PDO;

final class ProtocolsController
{
    private static function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

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
            $like = '%'.$q.'%'; 
            array_push($args,$like,$like,$like,$like,$like);
        }
        if ($type !== '' && in_array($type, ['einzug','auszug','zwischen'], true)) {
            $where[] = "p.type = ?"; 
            $args[] = $type;
        }
        if ($from !== '') { 
            $where[] = "DATE(p.created_at) >= ?"; 
            $args[] = $from; 
        }
        if ($to !== '') { 
            $where[] = "DATE(p.created_at) <= ?"; 
            $args[] = $to;   
        }

        $sql = "
          SELECT p.id,p.type,p.tenant_name,p.created_at,p.unit_id,
                 u.label AS unit_label, o.city,o.street,o.house_no
          FROM protocols p
          JOIN units u   ON u.id=p.unit_id
          JOIN objects o ON o.id=u.object_id
          WHERE ".implode(" AND ", $where)."
          ORDER BY o.city,o.street,o.house_no,u.label,p.created_at DESC
        ";
        $st = $pdo->prepare($sql); 
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Gruppieren: Haus → Einheit → Protokolle
        $grp = [];
        foreach ($rows as $r) {
            $hk = $r['city'].'|'.$r['street'].'|'.$r['house_no'];
            $uk = $r['unit_label'];
            if (!isset($grp[$hk])) {
                $grp[$hk] = ['title'=>$r['city'].', '.$r['street'].' '.$r['house_no'], 'units'=>[]];
            }
            if (!isset($grp[$hk]['units'][$uk])) {
                $grp[$hk]['units'][$uk] = ['title'=>$r['unit_label'],'items'=>[]];
            }
            $grp[$hk]['units'][$uk]['items'][] = $r;
        }

        $h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $badge = function(string $t): string {
            $map = ['einzug'=>['success','Einzugsprotokoll'],'auszug'=>['danger','Auszugsprotokoll'],'zwischen'=>['warning','Zwischenprotokoll']];
            [$cls,$lbl] = $map[$t] ?? ['secondary',$t];
            return '<span class="badge bg-'.$cls.'">'.$lbl.'</span>';
        };

        // Header mit Such- und Filterformular
        $html  = '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h1 class="h4 mb-0">Protokoll‑Übersicht</h1>';
        $html .= '<a class="btn btn-primary" href="/protocols/wizard/start">Neues Protokoll</a>';
        $html .= '</div>';

        // Such- und Filterformular
        $html .= '<div class="card mb-4"><div class="card-body">';
        $html .= '<form method="get" class="row g-3">';
        $html .= '<div class="col-md-4">';
        $html .= '<label class="form-label">Suche</label>';
        $html .= '<input class="form-control" name="q" value="'.$h($q).'" placeholder="Stadt, Straße, Mieter...">';
        $html .= '</div>';
        $html .= '<div class="col-md-2">';
        $html .= '<label class="form-label">Art</label>';
        $html .= '<select class="form-select" name="type">';
        $html .= '<option value="">Alle</option>';
        $html .= '<option value="einzug"'.($type==='einzug'?' selected':'').'>Einzug</option>';
        $html .= '<option value="auszug"'.($type==='auszug'?' selected':'').'>Auszug</option>';
        $html .= '<option value="zwischen"'.($type==='zwischen'?' selected':'').'>Zwischen</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-md-2">';
        $html .= '<label class="form-label">Von</label>';
        $html .= '<input class="form-control" type="date" name="from" value="'.$h($from).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-2">';
        $html .= '<label class="form-label">Bis</label>';
        $html .= '<input class="form-control" type="date" name="to" value="'.$h($to).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-2 d-flex align-items-end">';
        $html .= '<button class="btn btn-outline-primary me-2">Filter</button>';
        $html .= '<a class="btn btn-outline-secondary" href="/protocols">Reset</a>';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '</div></div>';

        // Ergebnisse
        if (empty($grp)) {
            $html .= '<div class="card"><div class="card-body text-center text-muted">';
            $html .= '<p>Keine Protokolle gefunden.</p>';
            $html .= '<a class="btn btn-primary" href="/protocols/wizard/start">Erstes Protokoll erstellen</a>';
            $html .= '</div></div>';
        } else {
            $html .= '<div class="accordion" id="protocolAccordion">';
            $houseIndex = 0;
            
            foreach ($grp as $houseKey => $house) {
                $houseIndex++;
                $houseId = 'house'.$houseIndex;
                $showHouse = !empty($q) || !empty($type) || !empty($from) || !empty($to); // Bei Suche/Filter alle aufklappen
                
                $html .= '<div class="accordion-item">';
                $html .= '<h2 class="accordion-header">';
                $html .= '<button class="accordion-button'.($showHouse?'':' collapsed').'" type="button" data-bs-toggle="collapse" data-bs-target="#'.$houseId.'">';
                $html .= '<div class="d-flex justify-content-between align-items-center w-100 me-3">';
                $html .= '<strong>'.$h($house['title']).'</strong>';
                $html .= '<span class="text-muted">'.count($house['units']).' Einheit'.((count($house['units']) == 1) ? '' : 'en').'</span>';
                $html .= '</div>';
                $html .= '</button>';
                $html .= '</h2>';
                $html .= '<div id="'.$houseId.'" class="accordion-collapse collapse'.($showHouse?' show':'').'" data-bs-parent="#protocolAccordion">';
                $html .= '<div class="accordion-body p-0">';
                
                // Verschachtelte Accordion für Einheiten
                $html .= '<div class="accordion" id="units'.$houseIndex.'">';
                $unitIndex = 0;
                
                foreach ($house['units'] as $unitKey => $unit) {
                    $unitIndex++;
                    $unitId = 'unit'.$houseIndex.'_'.$unitIndex;
                    $showUnit = $showHouse; // Bei Suche auch Einheiten aufklappen
                    
                    $html .= '<div class="accordion-item border-0">';
                    $html .= '<h3 class="accordion-header">';
                    $html .= '<button class="accordion-button'.($showUnit?'':' collapsed').' bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#'.$unitId.'">';
                    $html .= '<div class="d-flex justify-content-between align-items-center w-100 me-3">';
                    $html .= '<span>Einheit '.$h($unit['title']).'</span>';
                    $html .= '<span class="badge bg-secondary">'.count($unit['items']).' Protokoll'.((count($unit['items']) == 1) ? '' : 'e').'</span>';
                    $html .= '</div>';
                    $html .= '</button>';
                    $html .= '</h3>';
                    $html .= '<div id="'.$unitId.'" class="accordion-collapse collapse'.($showUnit?' show':'').'" data-bs-parent="#units'.$houseIndex.'">';
                    $html .= '<div class="accordion-body">';
                    
                    if (empty($unit['items'])) {
                        $html .= '<p class="text-muted mb-0">Keine Protokolle vorhanden.</p>';
                    } else {
                        $html .= '<div class="table-responsive">';
                        $html .= '<table class="table table-sm table-hover">';
                        $html .= '<thead class="table-light"><tr>';
                        $html .= '<th>Datum</th><th>Art</th><th>Mieter</th><th>Aktionen</th>';
                        $html .= '</tr></thead>';
                        $html .= '<tbody>';
                        
                        foreach ($unit['items'] as $item) {
                            $html .= '<tr>';
                            $html .= '<td><small>'.date('d.m.Y H:i', strtotime($item['created_at'])).'</small></td>';
                            $html .= '<td>'.$badge($item['type']).'</td>';
                            $html .= '<td>'.$h($item['tenant_name']).'</td>';
                            $html .= '<td>';
                            $html .= '<div class="btn-group btn-group-sm">';
                            $html .= '<a class="btn btn-outline-primary" href="/protocols/edit?id='.$h($item['id']).'">Bearbeiten</a>';
                            $html .= '<a class="btn btn-outline-secondary" href="/protocols/pdf?protocol_id='.$h($item['id']).'&version=latest" target="_blank">PDF</a>';
                            $html .= '</div>';
                            $html .= '</td>';
                            $html .= '</tr>';
                        }
                        
                        $html .= '</tbody>';
                        $html .= '</table>';
                        $html .= '</div>';
                    }
                    
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                
                $html .= '</div>'; // Ende units accordion
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }

        View::render('Protokoll‑Übersicht', $html);
    }

    /** Editor (Tabs) mit vollständiger Funktionalität */
    public function form(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = (string)($_GET['id'] ?? '');
        
        if ($id === '') {
            Flash::add('error', 'ID fehlt.');
            header('Location: /protocols');
            return;
        }

        $st = $pdo->prepare("SELECT p.*, u.label AS unit_label, o.city,o.street,o.house_no, p.owner_id, p.manager_id
                           FROM protocols p 
                           JOIN units u ON u.id=p.unit_id 
                           JOIN objects o ON o.id=u.object_id
                           WHERE p.id=? LIMIT 1");
        $st->execute([$id]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        
        if (!$p) {
            Flash::add('error', 'Protokoll nicht gefunden.');
            header('Location: /protocols');
            return;
        }

        // Stammdaten laden
        $owners = $pdo->query("SELECT id,name,company FROM owners WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $managers = $pdo->query("SELECT id,name,company FROM managers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $payload = json_decode((string)($p['payload'] ?? '{}'), true) ?: [];
        $addr = (array)($payload['address'] ?? []);
        $rooms = (array)($payload['rooms'] ?? []);
        $meters = (array)($payload['meters'] ?? []);
        $keys = (array)($payload['keys'] ?? []);
        $meta = (array)($payload['meta'] ?? []);

        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sel = fn($v, $cur) => ((string)$v === (string)$cur) ? ' selected' : '';

        $title = $p['city'] . ', ' . $p['street'] . ' ' . $p['house_no'] . ' – ' . $p['unit_label'];

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
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-historie" type="button">Historie & Versand</button></li>
          </ul>

          <div class="tab-content border-start border-end border-bottom p-3">
            <!-- Tab: Kopf -->
            <div class="tab-pane fade show active" id="tab-kopf">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Art</label>
                  <select class="form-select" name="type">
                    <option value="einzug"<?= $sel('einzug', (string)$p['type']) ?>>Einzugsprotokoll</option>
                    <option value="auszug"<?= $sel('auszug', (string)$p['type']) ?>>Auszugsprotokoll</option>
                    <option value="zwischen"<?= $sel('zwischen', (string)$p['type']) ?>>Zwischenabnahme</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Mietername</label>
                  <input class="form-control" name="tenant_name" value="<?= $h((string)($p['tenant_name'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Eigentümer</label>
                  <select class="form-select" name="owner_id">
                    <option value="">-- Eigentümer wählen --</option>
                    <?php foreach ($owners as $ow): ?>
                      <option value="<?= $h($ow['id']) ?>"<?= $sel($ow['id'], $p['owner_id'] ?? '') ?>><?= $h($ow['name']) ?><?= $ow['company'] ? ' (' . $h($ow['company']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Hausverwaltung</label>
                  <select class="form-select" name="manager_id">
                    <option value="">-- Hausverwaltung wählen --</option>
                    <?php foreach ($managers as $mg): ?>
                      <option value="<?= $h($mg['id']) ?>"<?= $sel($mg['id'], $p['manager_id'] ?? '') ?>><?= $h($mg['name']) ?><?= $mg['company'] ? ' (' . $h($mg['company']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Bemerkungen</label>
                  <textarea class="form-control" name="meta[remarks]" rows="3"><?= $h((string)($meta['remarks'] ?? '')) ?></textarea>
                </div>
              </div>
            </div>

            <!-- Tab: Räume -->
            <div class="tab-pane fade" id="tab-raeume">
              <div id="rooms-container">
                <?php if (empty($rooms)): ?>
                  <p class="text-muted">Keine Räume definiert.</p>
                <?php else: ?>
                  <?php foreach ($rooms as $idx => $room): ?>
                    <div class="card mb-3 room-item">
                      <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Raum <?= $idx + 1 ?>: <?= $h((string)($room['name'] ?? 'Unbenannt')) ?></h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRoom(this)">Entfernen</button>
                      </div>
                      <div class="card-body">
                        <div class="row g-3">
                          <div class="col-md-6">
                            <label class="form-label">Raumname</label>
                            <input class="form-control" name="rooms[<?= $idx ?>][name]" value="<?= $h((string)($room['name'] ?? '')) ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Zustand</label>
                            <textarea class="form-control" name="rooms[<?= $idx ?>][condition]" rows="2"><?= $h((string)($room['condition'] ?? '')) ?></textarea>
                          </div>
                          <div class="col-md-4">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="rooms[<?= $idx ?>][accepted]" value="1"<?= !empty($room['accepted']) ? ' checked' : '' ?>>
                              <label class="form-check-label">Abnahme erfolgt</label>
                            </div>
                          </div>
                          <div class="col-md-8">
                            <label class="form-label">Geruch</label>
                            <input class="form-control" name="rooms[<?= $idx ?>][odor]" value="<?= $h((string)($room['odor'] ?? '')) ?>">
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <button type="button" class="btn btn-outline-primary" onclick="addRoom()">Raum hinzufügen</button>
            </div>

            <!-- Tab: Zähler -->
            <div class="tab-pane fade" id="tab-zaehler">
              <div class="row g-3">
                <!-- Stromzähler -->
                <div class="col-12"><h6>Stromzähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">Strom Wohnung - Zählernummer</label>
                  <input class="form-control" name="meters[power_unit_number]" value="<?= $h((string)($meters['power_unit_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Strom Wohnung - Zählerstand</label>
                  <input class="form-control" name="meters[power_unit_reading]" value="<?= $h((string)($meters['power_unit_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Strom Haus Allgemein - Zählernummer</label>
                  <input class="form-control" name="meters[power_house_number]" value="<?= $h((string)($meters['power_house_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Strom Haus Allgemein - Zählerstand</label>
                  <input class="form-control" name="meters[power_house_reading]" value="<?= $h((string)($meters['power_house_reading'] ?? '')) ?>">
                </div>
                
                <!-- Gaszähler -->
                <div class="col-12"><h6 class="mt-3">Gaszähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">Gas Wohnung - Zählernummer</label>
                  <input class="form-control" name="meters[gas_unit_number]" value="<?= $h((string)($meters['gas_unit_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Gas Wohnung - Zählerstand</label>
                  <input class="form-control" name="meters[gas_unit_reading]" value="<?= $h((string)($meters['gas_unit_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Gas Haus Allgemein - Zählernummer</label>
                  <input class="form-control" name="meters[gas_house_number]" value="<?= $h((string)($meters['gas_house_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Gas Haus Allgemein - Zählerstand</label>
                  <input class="form-control" name="meters[gas_house_reading]" value="<?= $h((string)($meters['gas_house_reading'] ?? '')) ?>">
                </div>
                
                <!-- Wasserzähler -->
                <div class="col-12"><h6 class="mt-3">Wasserzähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Küche (blau) - Zählernummer</label>
                  <input class="form-control" name="meters[cold_kitchen_number]" value="<?= $h((string)($meters['cold_kitchen_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Küche (blau) - Zählerstand</label>
                  <input class="form-control" name="meters[cold_kitchen_reading]" value="<?= $h((string)($meters['cold_kitchen_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Küche (rot) - Zählernummer</label>
                  <input class="form-control" name="meters[hot_kitchen_number]" value="<?= $h((string)($meters['hot_kitchen_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Küche (rot) - Zählerstand</label>
                  <input class="form-control" name="meters[hot_kitchen_reading]" value="<?= $h((string)($meters['hot_kitchen_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Badezimmer (blau) - Zählernummer</label>
                  <input class="form-control" name="meters[cold_bathroom_number]" value="<?= $h((string)($meters['cold_bathroom_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Badezimmer (blau) - Zählerstand</label>
                  <input class="form-control" name="meters[cold_bathroom_reading]" value="<?= $h((string)($meters['cold_bathroom_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Badezimmer (rot) - Zählernummer</label>
                  <input class="form-control" name="meters[hot_bathroom_number]" value="<?= $h((string)($meters['hot_bathroom_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Badezimmer (rot) - Zählerstand</label>
                  <input class="form-control" name="meters[hot_bathroom_reading]" value="<?= $h((string)($meters['hot_bathroom_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Wasserzähler Waschmaschine (blau) - Zählernummer</label>
                  <input class="form-control" name="meters[cold_washing_number]" value="<?= $h((string)($meters['cold_washing_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Wasserzähler Waschmaschine (blau) - Zählerstand</label>
                  <input class="form-control" name="meters[cold_washing_reading]" value="<?= $h((string)($meters['cold_washing_reading'] ?? '')) ?>">
                </div>
                
                <!-- Wärmemengenzähler -->
                <div class="col-12"><h6 class="mt-3">Wärmemengenzähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 1 - Zählernummer</label>
                  <input class="form-control" name="meters[wmz1_number]" value="<?= $h((string)($meters['wmz1_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 1 - Zählerstand</label>
                  <input class="form-control" name="meters[wmz1_reading]" value="<?= $h((string)($meters['wmz1_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 2 - Zählernummer</label>
                  <input class="form-control" name="meters[wmz2_number]" value="<?= $h((string)($meters['wmz2_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 2 - Zählerstand</label>
                  <input class="form-control" name="meters[wmz2_reading]" value="<?= $h((string)($meters['wmz2_reading'] ?? '')) ?>">
                </div>
              </div>
            </div>
              </div>
            </div>

            <!-- Tab: Schlüssel & Meta -->
            <div class="tab-pane fade" id="tab-schluessel">
              <div class="row g-3">
                <div class="col-12"><h6>Schlüssel</h6></div>
                <div id="keys-container">
                  <?php if (empty($keys)): ?>
                    <div class="col-12"><p class="text-muted">Keine Schlüssel definiert.</p></div>
                  <?php else: ?>
                    <?php foreach ($keys as $idx => $key): ?>
                      <div class="row g-2 mb-2 key-item">
                        <div class="col-md-4">
                          <input class="form-control" name="keys[<?= $idx ?>][type]" value="<?= $h((string)($key['type'] ?? '')) ?>" placeholder="Schlüssel-Art">
                        </div>
                        <div class="col-md-3">
                          <input class="form-control" name="keys[<?= $idx ?>][count]" value="<?= $h((string)($key['count'] ?? '')) ?>" placeholder="Anzahl">
                        </div>
                        <div class="col-md-4">
                          <input class="form-control" name="keys[<?= $idx ?>][number]" value="<?= $h((string)($key['number'] ?? '')) ?>" placeholder="Schlüssel-Nr.">
                        </div>
                        <div class="col-md-1">
                          <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeKey(this)">×</button>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <div class="col-12">
                  <button type="button" class="btn btn-outline-primary btn-sm" onclick="addKey()">Schlüssel hinzufügen</button>
                </div>
                
                <div class="col-12"><h6 class="mt-3">Zusätzliche Angaben</h6></div>
                <div class="col-md-6">
                  <label class="form-label">E-Mail</label>
                  <input class="form-control" type="email" name="meta[email]" value="<?= $h((string)($meta['email'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Telefon</label>
                  <input class="form-control" name="meta[phone]" value="<?= $h((string)($meta['phone'] ?? '')) ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Neue Meldeadresse</label>
                  <textarea class="form-control" name="meta[new_address]" rows="2"><?= $h((string)($meta['new_address'] ?? '')) ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <!-- Speichern-Button -->
          <div class="mt-4 d-flex justify-content-between">
            <a href="/protocols" class="btn btn-secondary">Zurück zur Übersicht</a>
            <div>
              <a href="/protocols/pdf?protocol_id=<?= $h($p['id']) ?>&version=latest" class="btn btn-outline-secondary me-2" target="_blank">PDF ansehen</a>
              <button type="submit" class="btn btn-primary">Speichern</button>
            </div>
          </div>
        </form>

        <script>
        let roomIndex = <?= count($rooms) ?>;
        let keyIndex = <?= count($keys) ?>;

        function addRoom() {
            const container = document.getElementById('rooms-container');
            const roomHtml = `
                <div class="card mb-3 room-item">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Raum ${roomIndex + 1}: <span class="room-name">Neuer Raum</span></h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRoom(this)">Entfernen</button>
                  </div>
                  <div class="card-body">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label">Raumname</label>
                        <input class="form-control" name="rooms[${roomIndex}][name]" onchange="updateRoomName(this)">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Zustand</label>
                        <textarea class="form-control" name="rooms[${roomIndex}][condition]" rows="2"></textarea>
                      </div>
                      <div class="col-md-4">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="rooms[${roomIndex}][accepted]" value="1">
                          <label class="form-check-label">Abnahme erfolgt</label>
                        </div>
                      </div>
                      <div class="col-md-8">
                        <label class="form-label">Geruch</label>
                        <input class="form-control" name="rooms[${roomIndex}][odor]">
                      </div>
                    </div>
                  </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', roomHtml);
            roomIndex++;
        }

        function removeRoom(button) {
            button.closest('.room-item').remove();
        }

        function updateRoomName(input) {
            const nameSpan = input.closest('.room-item').querySelector('.room-name');
            nameSpan.textContent = input.value || 'Unbenannt';
        }

        function addKey() {
            const container = document.getElementById('keys-container');
            const keyHtml = `
                <div class="row g-2 mb-2 key-item">
                  <div class="col-md-4">
                    <input class="form-control" name="keys[${keyIndex}][type]" placeholder="Schlüssel-Art">
                  </div>
                  <div class="col-md-3">
                    <input class="form-control" name="keys[${keyIndex}][count]" placeholder="Anzahl">
                  </div>
                  <div class="col-md-4">
                    <input class="form-control" name="keys[${keyIndex}][number]" placeholder="Schlüssel-Nr.">
                  </div>
                  <div class="col-md-1">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeKey(this)">×</button>
                  </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', keyHtml);
            keyIndex++;
        }

        function removeKey(button) {
            button.closest('.key-item').remove();
        }
        </script>

        <?php
        $html = ob_get_clean();
        View::render('Protokoll bearbeiten', $html);
    }

    /** Protokoll speichern */
    public function save(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = (string)($_POST['id'] ?? '');
        
        if ($id === '') {
            Flash::add('error', 'ID fehlt.');
            header('Location: /protocols');
            return;
        }

        // Daten sammeln
        $type = (string)($_POST['type'] ?? '');
        $tenantName = (string)($_POST['tenant_name'] ?? '');
        $ownerId = ($_POST['owner_id'] ?? '') ?: null;
        $managerId = ($_POST['manager_id'] ?? '') ?: null;
        
        $rooms = (array)($_POST['rooms'] ?? []);
        $meters = (array)($_POST['meters'] ?? []);
        $keys = (array)($_POST['keys'] ?? []);
        $meta = (array)($_POST['meta'] ?? []);

        $payload = [
            'rooms' => $rooms,
            'meters' => $meters,
            'keys' => $keys,
            'meta' => $meta,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Protokoll aktualisieren
        $st = $pdo->prepare("UPDATE protocols SET type=?, tenant_name=?, payload=?, owner_id=?, manager_id=?, updated_at=NOW() WHERE id=?");
        $st->execute([$type, $tenantName, json_encode($payload, JSON_UNESCAPED_UNICODE), $ownerId, $managerId, $id]);

        // Neue Version erstellen
        $versionSt = $pdo->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM protocol_versions WHERE protocol_id=?");
        $versionSt->execute([$id]);
        $nextVersion = (int)$versionSt->fetchColumn();

        $st = $pdo->prepare("INSERT INTO protocol_versions (id, protocol_id, version_no, data, created_by, created_at) VALUES (UUID(), ?, ?, ?, ?, NOW())");
        $st->execute([$id, $nextVersion, json_encode($payload, JSON_UNESCAPED_UNICODE), Auth::user()['email'] ?? 'system']);

        // Event für neue Version loggen
        try {
            $currentUser = Auth::user()['email'] ?? 'system';
            $pdo->prepare("INSERT INTO protocol_events (id, protocol_id, type, message, created_at) VALUES (UUID(), ?, 'other', ?, NOW())")
                ->execute([$id, "Version $nextVersion erstellt von $currentUser"]);
        } catch (\Throwable $e) {
            error_log("Event-Logging fehlgeschlagen: ".$e->getMessage());
        }

        Flash::add('success', 'Protokoll gespeichert und neue Version (' . $nextVersion . ') erstellt.');
        header('Location: /protocols/edit?id=' . urlencode($id));
    }

    /** PDF anzeigen (bevorzugt signiert) - Verbesserte Version mit Debugging */
    public function pdf(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $pid = (string)($_GET['protocol_id'] ?? ''); 
        $ver = (string)($_GET['version'] ?? 'latest');
        
        if ($pid === '') { 
            http_response_code(400); 
            echo 'protocol_id fehlt'; 
            return; 
        }
        
        // Protokoll existiert?
        $st = $pdo->prepare("SELECT id FROM protocols WHERE id=? AND deleted_at IS NULL");
        $st->execute([$pid]);
        if (!$st->fetch()) {
            http_response_code(404);
            echo 'Protokoll nicht gefunden';
            return;
        }
        
        if ($ver === 'latest') { 
            $st = $pdo->prepare("SELECT COALESCE(MAX(version_no),1) FROM protocol_versions WHERE protocol_id=?"); 
            $st->execute([$pid]); 
            $ver = (string)((int)$st->fetchColumn() ?: 1); 
        }
        
        $v = (int)$ver; 
        if ($v <= 0) $v = 1;
        
        try { 
            // Debugging: Log PDF-Generation Start
            error_log("PDF-Generation gestartet für Protokoll: $pid, Version: $v");
            
            $path = \App\PdfService::getOrRender($pid, $v, true);
            
            if (!$path) { 
                error_log("PDF-Generation fehlgeschlagen: Leerer Pfad");
                http_response_code(500); 
                echo 'PDF konnte nicht generiert werden (leerer Pfad)'; 
                return; 
            }
            
            if (!is_file($path)) { 
                error_log("PDF-Datei nicht gefunden: $path");
                http_response_code(404); 
                echo 'PDF-Datei nicht gefunden: '.basename($path); 
                return; 
            }
            
            $filesize = filesize($path);
            if ($filesize === false || $filesize === 0) {
                error_log("PDF-Datei ist leer oder nicht lesbar: $path");
                http_response_code(500);
                echo 'PDF-Datei ist leer oder beschädigt';
                return;
            }
            
            // Event loggen
            try {
                $pdo->prepare("INSERT INTO protocol_events (id, protocol_id, type, message, created_at) VALUES (UUID(), ?, 'other', ?, NOW())")
                    ->execute([$pid, 'PDF angezeigt (Version: '.$v.')']);
            } catch (\Throwable $e) {
                error_log("Event-Logging fehlgeschlagen: ".$e->getMessage());
            }
            
            // PDF ausgeben
            header('Content-Type: application/pdf'); 
            header('Content-Disposition: inline; filename="protokoll-v'.$v.'.pdf"'); 
            header('Content-Length: '.$filesize); 
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // PDF-Inhalt ausgeben
            if (readfile($path) === false) {
                error_log("Fehler beim Lesen der PDF-Datei: $path");
                http_response_code(500);
                echo 'Fehler beim Lesen der PDF-Datei';
            }
            
        } catch (\Throwable $e) { 
            error_log("PDF-Fehler: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine());
            http_response_code(500); 
            echo 'PDF-Fehler: '.$e->getMessage(); 
        }
    }

    /** Protokoll löschen */
    public function delete(): void
    {
        Auth::requireAuth();
        $id = (string)($_POST['id'] ?? '');
        
        if ($id === '') {
            Flash::add('error', 'ID fehlt.');
            header('Location: /protocols');
            return;
        }

        $pdo = Database::pdo();
        $st = $pdo->prepare("UPDATE protocols SET deleted_at=NOW() WHERE id=?");
        $st->execute([$id]);

        Flash::add('success', 'Protokoll gelöscht.');
        header('Location: /protocols');
    }

    /** Export */
    public function export(): void
    {
        Auth::requireAuth();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="protokolle_'.date('Y-m-d').'.csv"');
        
        $pdo = Database::pdo();
        $sql = "SELECT p.id,p.type,p.tenant_name,p.created_at,o.city,o.street,o.house_no,u.label AS unit_label
                FROM protocols p 
                JOIN units u ON u.id=p.unit_id 
                JOIN objects o ON o.id=u.object_id 
                WHERE p.deleted_at IS NULL 
                ORDER BY p.created_at DESC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Art','Mieter','Stadt','Straße','Hausnr','WE','Erstellt']);
        foreach($rows as $r) { 
            fputcsv($out, [$r['id'],$r['type'],$r['tenant_name'],$r['city'],$r['street'],$r['house_no'],$r['unit_label'],$r['created_at']]); 
        }
        fclose($out);
    }

    /** Editor mit Historie und Versandfunktionen */
    public function edit(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = (string)($_GET['id'] ?? '');
        
        if ($id === '') {
            Flash::add('error', 'ID fehlt.');
            header('Location: /protocols');
            return;
        }

        $st = $pdo->prepare("SELECT p.*, u.label AS unit_label, o.city,o.street,o.house_no, p.owner_id, p.manager_id
                           FROM protocols p 
                           JOIN units u ON u.id=p.unit_id 
                           JOIN objects o ON o.id=u.object_id
                           WHERE p.id=? LIMIT 1");
        $st->execute([$id]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        
        if (!$p) {
            Flash::add('error', 'Protokoll nicht gefunden.');
            header('Location: /protocols');
            return;
        }

        // Stammdaten laden
        $owners = $pdo->query("SELECT id,name,company FROM owners WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $managers = $pdo->query("SELECT id,name,company FROM managers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $payload = json_decode((string)($p['payload'] ?? '{}'), true) ?: [];
        $addr = (array)($payload['address'] ?? []);
        $rooms = (array)($payload['rooms'] ?? []);
        $meters = (array)($payload['meters'] ?? []);
        $keys = (array)($payload['keys'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
        $cons = (array)($meta['consents'] ?? []);

        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sel = fn($v, $cur) => ((string)$v === (string)$cur) ? ' selected' : '';

        $title = $p['city'] . ', ' . $p['street'] . ' ' . $p['house_no'] . ' – ' . $p['unit_label'];

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
            <!-- Tab: Kopf -->
            <div class="tab-pane fade show active" id="tab-kopf">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Art</label>
                  <select class="form-select" name="type">
                    <option value="einzug"<?= $sel('einzug', (string)$p['type']) ?>>Einzugsprotokoll</option>
                    <option value="auszug"<?= $sel('auszug', (string)$p['type']) ?>>Auszugsprotokoll</option>
                    <option value="zwischen"<?= $sel('zwischen', (string)$p['type']) ?>>Zwischenabnahme</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Mietername</label>
                  <input class="form-control" name="tenant_name" value="<?= $h((string)($p['tenant_name'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Eigentümer</label>
                  <select class="form-select" name="owner_id">
                    <option value="">-- Eigentümer wählen --</option>
                    <?php foreach ($owners as $ow): ?>
                      <option value="<?= $h($ow['id']) ?>"<?= $sel($ow['id'], $p['owner_id'] ?? '') ?>><?= $h($ow['name']) ?><?= $ow['company'] ? ' (' . $h($ow['company']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Hausverwaltung</label>
                  <select class="form-select" name="manager_id">
                    <option value="">-- Hausverwaltung wählen --</option>
                    <?php foreach ($managers as $mg): ?>
                      <option value="<?= $h($mg['id']) ?>"<?= $sel($mg['id'], $p['manager_id'] ?? '') ?>><?= $h($mg['name']) ?><?= $mg['company'] ? ' (' . $h($mg['company']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Bemerkungen</label>
                  <textarea class="form-control" name="meta[remarks]" rows="3"><?= $h((string)($meta['remarks'] ?? '')) ?></textarea>
                </div>
              </div>
            </div>

            <!-- Tab: Räume -->
            <div class="tab-pane fade" id="tab-raeume">
              <div id="rooms-container">
                <?php if (empty($rooms)): ?>
                  <p class="text-muted">Keine Räume definiert.</p>
                <?php else: ?>
                  <?php foreach ($rooms as $idx => $room): ?>
                    <div class="card mb-3 room-item">
                      <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Raum <?= $idx + 1 ?>: <?= $h((string)($room['name'] ?? 'Unbenannt')) ?></h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRoom(this)">Entfernen</button>
                      </div>
                      <div class="card-body">
                        <div class="row g-3">
                          <div class="col-md-6">
                            <label class="form-label">Raumname</label>
                            <input class="form-control" name="rooms[<?= $idx ?>][name]" value="<?= $h((string)($room['name'] ?? '')) ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Zustand</label>
                            <textarea class="form-control" name="rooms[<?= $idx ?>][condition]" rows="2"><?= $h((string)($room['condition'] ?? '')) ?></textarea>
                          </div>
                          <div class="col-md-4">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="rooms[<?= $idx ?>][accepted]" value="1"<?= !empty($room['accepted']) ? ' checked' : '' ?>>
                              <label class="form-check-label">Abnahme erfolgt</label>
                            </div>
                          </div>
                          <div class="col-md-8">
                            <label class="form-label">Geruch</label>
                            <input class="form-control" name="rooms[<?= $idx ?>][odor]" value="<?= $h((string)($room['odor'] ?? '')) ?>">
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <button type="button" class="btn btn-outline-primary" onclick="addRoom()">Raum hinzufügen</button>
            </div>

            <!-- Tab: Zähler -->
            <div class="tab-pane fade" id="tab-zaehler">
              <div class="row g-3">
                <!-- Stromzähler -->
                <div class="col-12"><h6>Stromzähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">Strom Wohnung - Zählernummer</label>
                  <input class="form-control" name="meters[power_unit_number]" value="<?= $h((string)($meters['power_unit_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Strom Wohnung - Zählerstand</label>
                  <input class="form-control" name="meters[power_unit_reading]" value="<?= $h((string)($meters['power_unit_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Strom Haus Allgemein - Zählernummer</label>
                  <input class="form-control" name="meters[power_house_number]" value="<?= $h((string)($meters['power_house_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Strom Haus Allgemein - Zählerstand</label>
                  <input class="form-control" name="meters[power_house_reading]" value="<?= $h((string)($meters['power_house_reading'] ?? '')) ?>">
                </div>
                
                <!-- Gaszähler -->
                <div class="col-12"><h6 class="mt-3">Gaszähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">Gas Wohnung - Zählernummer</label>
                  <input class="form-control" name="meters[gas_unit_number]" value="<?= $h((string)($meters['gas_unit_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Gas Wohnung - Zählerstand</label>
                  <input class="form-control" name="meters[gas_unit_reading]" value="<?= $h((string)($meters['gas_unit_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Gas Haus Allgemein - Zählernummer</label>
                  <input class="form-control" name="meters[gas_house_number]" value="<?= $h((string)($meters['gas_house_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Gas Haus Allgemein - Zählerstand</label>
                  <input class="form-control" name="meters[gas_house_reading]" value="<?= $h((string)($meters['gas_house_reading'] ?? '')) ?>">
                </div>
                
                <!-- Wasserzähler -->
                <div class="col-12"><h6 class="mt-3">Wasserzähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Küche (blau) - Zählernummer</label>
                  <input class="form-control" name="meters[cold_kitchen_number]" value="<?= $h((string)($meters['cold_kitchen_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Küche (blau) - Zählerstand</label>
                  <input class="form-control" name="meters[cold_kitchen_reading]" value="<?= $h((string)($meters['cold_kitchen_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Küche (rot) - Zählernummer</label>
                  <input class="form-control" name="meters[hot_kitchen_number]" value="<?= $h((string)($meters['hot_kitchen_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Küche (rot) - Zählerstand</label>
                  <input class="form-control" name="meters[hot_kitchen_reading]" value="<?= $h((string)($meters['hot_kitchen_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Badezimmer (blau) - Zählernummer</label>
                  <input class="form-control" name="meters[cold_bathroom_number]" value="<?= $h((string)($meters['cold_bathroom_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kaltwasser Badezimmer (blau) - Zählerstand</label>
                  <input class="form-control" name="meters[cold_bathroom_reading]" value="<?= $h((string)($meters['cold_bathroom_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Badezimmer (rot) - Zählernummer</label>
                  <input class="form-control" name="meters[hot_bathroom_number]" value="<?= $h((string)($meters['hot_bathroom_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Warmwasser Badezimmer (rot) - Zählerstand</label>
                  <input class="form-control" name="meters[hot_bathroom_reading]" value="<?= $h((string)($meters['hot_bathroom_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Wasserzähler Waschmaschine (blau) - Zählernummer</label>
                  <input class="form-control" name="meters[cold_washing_number]" value="<?= $h((string)($meters['cold_washing_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Wasserzähler Waschmaschine (blau) - Zählerstand</label>
                  <input class="form-control" name="meters[cold_washing_reading]" value="<?= $h((string)($meters['cold_washing_reading'] ?? '')) ?>">
                </div>
                
                <!-- Wärmemengenzähler -->
                <div class="col-12"><h6 class="mt-3">Wärmemengenzähler</h6></div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 1 - Zählernummer</label>
                  <input class="form-control" name="meters[wmz1_number]" value="<?= $h((string)($meters['wmz1_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 1 - Zählerstand</label>
                  <input class="form-control" name="meters[wmz1_reading]" value="<?= $h((string)($meters['wmz1_reading'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 2 - Zählernummer</label>
                  <input class="form-control" name="meters[wmz2_number]" value="<?= $h((string)($meters['wmz2_number'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WMZ 2 - Zählerstand</label>
                  <input class="form-control" name="meters[wmz2_reading]" value="<?= $h((string)($meters['wmz2_reading'] ?? '')) ?>">
                </div>
              </div>
            </div>

            <!-- Tab: Schlüssel & Meta -->
            <div class="tab-pane fade" id="tab-schluessel">
              <div class="row g-3">
                <div class="col-12"><h6>Schlüssel</h6></div>
                <div id="keys-container">
                  <?php if (empty($keys)): ?>
                    <div class="col-12"><p class="text-muted">Keine Schlüssel definiert.</p></div>
                  <?php else: ?>
                    <?php foreach ($keys as $idx => $key): ?>
                      <div class="row g-2 mb-2 key-item">
                        <div class="col-md-4">
                          <input class="form-control" name="keys[<?= $idx ?>][type]" value="<?= $h((string)($key['type'] ?? '')) ?>" placeholder="Schlüssel-Art">
                        </div>
                        <div class="col-md-3">
                          <input class="form-control" name="keys[<?= $idx ?>][count]" value="<?= $h((string)($key['count'] ?? '')) ?>" placeholder="Anzahl">
                        </div>
                        <div class="col-md-4">
                          <input class="form-control" name="keys[<?= $idx ?>][number]" value="<?= $h((string)($key['number'] ?? '')) ?>" placeholder="Schlüssel-Nr.">
                        </div>
                        <div class="col-md-1">
                          <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeKey(this)">×</button>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <div class="col-12">
                  <button type="button" class="btn btn-outline-primary btn-sm" onclick="addKey()">Schlüssel hinzufügen</button>
                </div>
                
                <div class="col-12"><h6 class="mt-3">Zusätzliche Angaben</h6></div>
                <div class="col-md-6">
                  <label class="form-label">E-Mail</label>
                  <input class="form-control" type="email" name="meta[email]" value="<?= $h((string)($meta['email'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Telefon</label>
                  <input class="form-control" name="meta[phone]" value="<?= $h((string)($meta['phone'] ?? '')) ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Neue Meldeadresse</label>
                  <textarea class="form-control" name="meta[new_address]" rows="2"><?= $h((string)($meta['new_address'] ?? '')) ?></textarea>
                </div>
                
                <div class="col-12"><h6 class="mt-3">Einverständniserklärungen</h6></div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="meta[consents][privacy]"<?= !empty($cons['privacy']) ? ' checked' : '' ?>>
                    <label class="form-check-label">Datenschutzerklärung akzeptiert</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="meta[consents][marketing]"<?= !empty($cons['marketing']) ? ' checked' : '' ?>>
                    <label class="form-check-label">E‑Mail‑Marketing</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="meta[consents][disposal]"<?= !empty($cons['disposal']) ? ' checked' : '' ?>>
                    <label class="form-check-label">Entsorgung zurückgelassener Gegenstände</label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tab: Historie & Versand -->
            <div class="tab-pane fade" id="tab-historie">
              <?php
              // Events und Mail-Historie laden
              $ev = $pdo->prepare("SELECT type, message, created_at FROM protocol_events WHERE protocol_id=? ORDER BY created_at DESC LIMIT 100");
              $ev->execute([$p['id']]);  
              $events = $ev->fetchAll(PDO::FETCH_ASSOC);
              
              $ml = $pdo->prepare("SELECT recipient_type, to_email, subject, status, error_msg, sent_at, created_at FROM email_log WHERE protocol_id=? ORDER BY created_at DESC LIMIT 100");
              $ml->execute([$p['id']]);
              $mails = $ml->fetchAll(PDO::FETCH_ASSOC);
              
              // Protokoll-Versionen laden
              $vl = $pdo->prepare("SELECT version_no, created_at, created_by, pdf_path, signed_pdf_path FROM protocol_versions WHERE protocol_id=? ORDER BY version_no DESC");
              $vl->execute([$p['id']]);
              $versions = $vl->fetchAll(PDO::FETCH_ASSOC);
              ?>
              
              <!-- Versand-Buttons -->
              <div class="row mb-4">
                <div class="col-12">
                  <h6>Schnellversand</h6>
                  <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-outline-primary btn-sm" href="/protocols/send?protocol_id=<?= $h($p['id']) ?>&to=owner">PDF an Eigentümer senden</a>
                    <a class="btn btn-outline-primary btn-sm" href="/protocols/send?protocol_id=<?= $h($p['id']) ?>&to=manager">PDF an Hausverwaltung senden</a>
                    <a class="btn btn-outline-primary btn-sm" href="/protocols/send?protocol_id=<?= $h($p['id']) ?>&to=tenant">PDF an Mieter senden</a>
                    <a class="btn btn-outline-secondary btn-sm" href="/protocols/pdf?protocol_id=<?= $h($p['id']) ?>&version=latest" target="_blank">PDF ansehen</a>
                  </div>
                </div>
              </div>
              
              <!-- Drei-spaltige Anzeige -->
              <div class="row g-3">
                <!-- Versionen -->
                <div class="col-md-4">
                  <div class="card h-100">
                    <div class="card-header">
                      <h6 class="mb-0">Versionen (<?= count($versions) ?>)</h6>
                    </div>
                    <div class="card-body p-2">
                      <?php if ($versions): ?>
                        <div class="list-group list-group-flush">
                          <?php foreach ($versions as $v): ?>
                            <div class="list-group-item px-2 py-2">
                              <div class="d-flex justify-content-between align-items-start">
                                <div>
                                  <strong>v<?= (int)$v['version_no'] ?></strong>
                                  <br><small class="text-muted"><?= date('d.m.Y H:i', strtotime($v['created_at'])) ?></small>
                                  <?php if ($v['created_by']): ?>
                                    <br><small class="text-muted">von <?= $h($v['created_by']) ?></small>
                                  <?php endif; ?>
                                </div>
                                <div class="btn-group-vertical btn-group-sm">
                                  <?php if (!empty($v['signed_pdf_path']) && is_file($v['signed_pdf_path'])): ?>
                                    <a class="btn btn-success btn-sm" href="/protocols/pdf?protocol_id=<?= $h($p['id']) ?>&version=<?= (int)$v['version_no'] ?>" target="_blank" title="Signierte Version">S</a>
                                  <?php elseif (!empty($v['pdf_path']) && is_file($v['pdf_path'])): ?>
                                    <a class="btn btn-outline-secondary btn-sm" href="/protocols/pdf?protocol_id=<?= $h($p['id']) ?>&version=<?= (int)$v['version_no'] ?>" target="_blank" title="PDF ansehen">PDF</a>
                                  <?php else: ?>
                                    <span class="btn btn-outline-light btn-sm disabled" title="PDF nicht verfügbar">-</span>
                                  <?php endif; ?>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-muted">Noch keine Versionen.</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                
                <!-- Ereignisse -->
                <div class="col-md-4">
                  <div class="card h-100">
                    <div class="card-header">
                      <h6 class="mb-0">Ereignisse (<?= count($events) ?>)</h6>
                    </div>
                    <div class="card-body p-2" style="max-height: 400px; overflow-y: auto;">
                      <?php if ($events): ?>
                        <div class="list-group list-group-flush">
                          <?php foreach ($events as $e): ?>
                            <div class="list-group-item px-2 py-2">
                              <div class="d-flex justify-content-between align-items-start">
                                <div>
                                  <div class="fw-bold small"><?= $h($e['type']) ?></div>
                                  <?php if ($e['message']): ?>
                                    <div class="small"><?= $h($e['message']) ?></div>
                                  <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= date('d.m H:i', strtotime($e['created_at'])) ?></small>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-muted">Noch keine Ereignisse.</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                
                <!-- E-Mail-Versand -->
                <div class="col-md-4">
                  <div class="card h-100">
                    <div class="card-header">
                      <h6 class="mb-0">E-Mail-Versand (<?= count($mails) ?>)</h6>
                    </div>
                    <div class="card-body p-2" style="max-height: 400px; overflow-y: auto;">
                      <?php if ($mails): ?>
                        <div class="list-group list-group-flush">
                          <?php foreach ($mails as $m): ?>
                            <div class="list-group-item px-2 py-2">
                              <div class="d-flex justify-content-between align-items-start">
                                <div>
                                  <div class="fw-bold small"><?= $h($m['recipient_type']) ?></div>
                                  <div class="small text-muted"><?= $h($m['to_email']) ?></div>
                                  <div class="small"><?= $h($m['subject']) ?></div>
                                  <?php if ($m['status'] === 'failed' && $m['error_msg']): ?>
                                    <div class="small text-danger"><?= $h($m['error_msg']) ?></div>
                                  <?php endif; ?>
                                </div>
                                <div class="text-end">
                                  <span class="badge bg-<?= $m['status'] === 'sent' ? 'success' : ($m['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= $h($m['status']) ?></span>
                                  <br><small class="text-muted"><?= date('d.m H:i', strtotime($m['sent_at'] ?? $m['created_at'])) ?></small>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-muted">Noch kein Versand.</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Speichern-Button -->
          <div class="mt-4 d-flex justify-content-between">
            <a href="/protocols" class="btn btn-secondary">Zurück zur Übersicht</a>
            <button type="submit" class="btn btn-primary">Speichern (neue Version)</button>
          </div>
        </form>



        <script>
        let roomIndex = <?= count($rooms) ?>;
        let keyIndex = <?= count($keys) ?>;

        function addRoom() {
            const container = document.getElementById('rooms-container');
            const roomHtml = `
                <div class="card mb-3 room-item">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Raum ${roomIndex + 1}: <span class="room-name">Neuer Raum</span></h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRoom(this)">Entfernen</button>
                  </div>
                  <div class="card-body">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label">Raumname</label>
                        <input class="form-control" name="rooms[${roomIndex}][name]" onchange="updateRoomName(this)">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Zustand</label>
                        <textarea class="form-control" name="rooms[${roomIndex}][condition]" rows="2"></textarea>
                      </div>
                      <div class="col-md-4">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="rooms[${roomIndex}][accepted]" value="1">
                          <label class="form-check-label">Abnahme erfolgt</label>
                        </div>
                      </div>
                      <div class="col-md-8">
                        <label class="form-label">Geruch</label>
                        <input class="form-control" name="rooms[${roomIndex}][odor]">
                      </div>
                    </div>
                  </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', roomHtml);
            roomIndex++;
        }

        function removeRoom(button) {
            button.closest('.room-item').remove();
        }

        function updateRoomName(input) {
            const nameSpan = input.closest('.room-item').querySelector('.room-name');
            nameSpan.textContent = input.value || 'Unbenannt';
        }

        function addKey() {
            const container = document.getElementById('keys-container');
            const keyHtml = `
                <div class="row g-2 mb-2 key-item">
                  <div class="col-md-4">
                    <input class="form-control" name="keys[${keyIndex}][type]" placeholder="Schlüssel-Art">
                  </div>
                  <div class="col-md-3">
                    <input class="form-control" name="keys[${keyIndex}][count]" placeholder="Anzahl">
                  </div>
                  <div class="col-md-4">
                    <input class="form-control" name="keys[${keyIndex}][number]" placeholder="Schlüssel-Nr.">
                  </div>
                  <div class="col-md-1">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeKey(this)">×</button>
                  </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', keyHtml);
            keyIndex++;
        }

        function removeKey(button) {
            button.closest('.key-item').remove();
        }
        </script>

        <?php
        $html = ob_get_clean();
        View::render('Protokoll – Bearbeiten', $html);
    }

    /** Mail versenden */
    public function send(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $pid = (string)($_GET['protocol_id'] ?? ''); 
        $to = (string)($_GET['to'] ?? 'owner');
        
        if ($pid === '') { 
            http_response_code(400); 
            header('Content-Type:application/json'); 
            echo json_encode(['error'=>'protocol_id fehlt']); 
            return; 
        }

        $st = $pdo->prepare("SELECT p.*,u.label AS unit_label,o.city,o.street,o.house_no FROM protocols p JOIN units u ON u.id=p.unit_id JOIN objects o ON o.id=u.object_id WHERE p.id=? LIMIT 1");
        $st->execute([$pid]); 
        $p = $st->fetch(PDO::FETCH_ASSOC);
        
        if (!$p) { 
            http_response_code(404); 
            header('Content-Type:application/json'); 
            echo json_encode(['error'=>'Protokoll nicht gefunden']); 
            return; 
        }
        
        $payload = json_decode((string)$p['payload'], true) ?: []; 
        $addr = (array)($payload['address'] ?? []);

        // Empfänger ermitteln
        $email = ''; $name = '';
        if ($to === 'tenant') { 
            $email = (string)($payload['meta']['tenant_contact']['email'] ?? ''); 
            $name = (string)($p['tenant_name'] ?? ''); 
        }
        elseif ($to === 'manager') { 
            $id = (string)($p['manager_id'] ?? ''); 
            if ($id !== '') { 
                $q = $pdo->prepare("SELECT email,name FROM managers WHERE id=?"); 
                $q->execute([$id]); 
                if ($r = $q->fetch(PDO::FETCH_ASSOC)) { 
                    $email = (string)($r['email'] ?? ''); 
                    $name = (string)($r['name'] ?? ''); 
                } 
            } 
        }
        else { 
            $id = (string)($p['owner_id'] ?? ''); 
            if ($id !== '') { 
                $q = $pdo->prepare("SELECT email,name FROM owners WHERE id=?"); 
                $q->execute([$id]); 
                if ($r = $q->fetch(PDO::FETCH_ASSOC)) { 
                    $email = (string)($r['email'] ?? ''); 
                    $name = (string)($r['name'] ?? ''); 
                } 
            } 
        }

        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $redirect = (strpos($referer, '/protocols/edit') !== false);

        if ($email === '') {
            if ($redirect) { 
                \App\Flash::add('error','Empfängeradresse fehlt – bitte im Kopf/Kontakt ergänzen.'); 
                header('Location: '.$referer); 
            } else { 
                http_response_code(400); 
                header('Content-Type:application/json'); 
                echo json_encode(['error'=>'Empfängeradresse fehlt']); 
            }
            return;
        }

        // Version & PDF
        $st = $pdo->prepare("SELECT COALESCE(MAX(version_no),1) FROM protocol_versions WHERE protocol_id=?"); 
        $st->execute([$pid]); 
        $versionNo = (int)$st->fetchColumn(); 
        if ($versionNo <= 0) $versionNo = 1;
        
        try { 
            $pdfPath = \App\PdfService::getOrRender($pid, $versionNo, true); 
        } catch (\Throwable $e) {
            if ($redirect) { 
                \App\Flash::add('error','PDF-Fehler: '.$e->getMessage()); 
                header('Location: '.$referer); 
            } else { 
                http_response_code(500); 
                header('Content-Type:application/json'); 
                echo json_encode(['error'=>'PDF-Fehler: '.$e->getMessage()]); 
            } 
            return;
        }

        // SMTP Settings
        $host = (string)Settings::get('smtp_host',''); 
        if ($host === '') $host = 'mailpit';
        $port = (int)Settings::get('smtp_port','1025'); 
        $user = (string)Settings::get('smtp_user',''); 
        $pass = (string)Settings::get('smtp_pass',''); 
        $sec = (string)Settings::get('smtp_secure','');
        $fromName = (string)Settings::get('smtp_from_name','Wohnungsübergabe'); 
        $fromMail = (string)Settings::get('smtp_from_email','no-reply@example.com');

        $betreff = 'Übergabeprotokoll – '.(string)($addr['street'] ?? '').' '.(string)($addr['house_no'] ?? '').' – '.(string)($addr['city'] ?? '').' (v'.$versionNo.')';
        $text = "Guten Tag\n\nim Anhang erhalten Sie das Übergabeprotokoll (v$versionNo).\nObjekt: ".(string)($addr['street'] ?? '').' '.(string)($addr['house_no'] ?? '').', '.(string)($addr['city'] ?? '')."\n\nMit freundlichen Grüßen\nWohnungsübergabe";

        $ok = false; $err = '';
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP(); 
            $mail->Host = $host; 
            $mail->Port = $port;
            if ($sec !== '') $mail->SMTPSecure = $sec;
            if ($user !== '') { 
                $mail->SMTPAuth = true; 
                $mail->Username = $user; 
                $mail->Password = $pass; 
            }
            $mail->setFrom($fromMail, $fromName); 
            $mail->addAddress($email, $name);
            $mail->Subject = $betreff; 
            $mail->Body = $text;
            $mail->addAttachment($pdfPath, 'uebergabeprotokoll_v'.$versionNo.'.pdf'); 
            $mail->send(); 
            $ok = true;
        } catch (\Throwable $e) { 
            $err = $e->getMessage(); 
            $ok = false; 
        }

        // Log schreiben
        try {
            $pdo->prepare("INSERT INTO email_log (id,protocol_id,recipient_type,to_email,subject,status,sent_at,created_at) VALUES (UUID(),?,?,?,?,?,NOW(),NOW())")
                ->execute([$pid, $to, $email, $betreff, $ok ? 'sent' : 'failed']);
            $pdo->prepare("INSERT INTO protocol_events (id,protocol_id,type,message,created_at) VALUES (UUID(),?,?,?,NOW())")
                ->execute([$pid, 'mail_'.$to, $ok ? 'E-Mail versendet an '.$email : 'Versand fehlgeschlagen: '.$err]);
        } catch (\Throwable $e) {}

        if ($redirect) { 
            \App\Flash::add($ok ? 'success' : 'error', $ok ? 'E‑Mail versendet.' : 'Versand fehlgeschlagen: '.$err); 
            header('Location: '.$referer); 
        } else { 
            header('Content-Type:application/json'); 
            echo json_encode(['ok'=>$ok,'error'=>$ok ? null : $err]); 
        }
    }
}
