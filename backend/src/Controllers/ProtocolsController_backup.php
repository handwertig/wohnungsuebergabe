<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\View;
use App\Settings;
use App\Flash;
use App\AuditLogger;
use App\SystemLogger;
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

        // Protokoll-Anzeige loggen
        try {
            if (class_exists('\\App\\SystemLogger')) {
                SystemLogger::logProtocolViewed($id, [
                    'tenant_name' => $p['tenant_name'] ?? 'Unbekannt',
                    'type' => $p['type'] ?? '',
                    'city' => $p['city'] ?? '',
                    'street' => $p['street'] ?? '',
                    'unit' => $p['unit_label'] ?? ''
                ]);
            }
        } catch (\Throwable $e) {
            error_log("Protocol view logging fehlgeschlagen: ".$e->getMessage());
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
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-historie" type="button">Historie & Versand</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pdf-versionen" type="button"><i class="bi bi-file-earmark-pdf"></i> PDF-Versionen</button></li>
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
                        <h6 class="mb-0">Raum <?= ((int)$idx + 1) ?>: <?= $h((string)($room['name'] ?? 'Unbenannt')) ?></h6>
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

            <!-- Tab: PDF-Versionen -->
            <div class="tab-pane fade" id="tab-pdf-versionen">
              <?php echo $this->renderPDFVersionsTab($p['id']); ?>
            </div>
          </div>
          
          <!-- Versteckter Submit-Button im Formular -->
          <input type="submit" id="hiddenSubmit" style="display: none;">
        </form>

        <!-- Speichern-Button außerhalb der Tabs -->
        <div class="mt-4 d-flex justify-content-between">
          <a href="/protocols" class="btn btn-secondary">Zurück zur Übersicht</a>
          <button type="button" class="btn btn-primary" id="saveButton">Speichern (neue Version)</button>
        </div>

        <script>
        // Einfacher, zuverlässiger Submit-Handler
        document.getElementById('saveButton').addEventListener('click', function() {
            console.log('Save button clicked');
            
            // Methode 1: Normales form.submit()
            const form = document.querySelector('form[action="/protocols/save"]');
            if (form) {
                console.log('Form found, trying method 1: form.submit()');
                try {
                    form.submit();
                    return;
                } catch (e) {
                    console.error('Method 1 failed:', e);
                }
            }
            
            // Methode 2: Versteckten Submit-Button klicken
            const hiddenSubmit = document.getElementById('hiddenSubmit');
            if (hiddenSubmit) {
                console.log('Trying method 2: hidden submit button');
                try {
                    hiddenSubmit.click();
                    return;
                } catch (e) {
                    console.error('Method 2 failed:', e);
                }
            }
            
            // Methode 3: Manueller POST-Request
            console.log('Trying method 3: manual POST request');
            if (form) {
                const formData = new FormData(form);
                fetch('/protocols/save', {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        window.location.reload();
                    }
                }).catch(e => {
                    console.error('Method 3 failed:', e);
                    alert('Fehler beim Speichern!');
                });
            } else {
                alert('Fehler: Formular nicht gefunden!');
            }
        });
        let roomIndex = <?= count($rooms) ?>;
        let keyIndex = <?= count($keys) ?>;

        function addRoom() {
            const container = document.getElementById('rooms-container');
            const roomHtml = `
                <div class="card mb-3 room-item">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Raum ${parseInt(roomIndex) + 1}: <span class="room-name">Neuer Raum</span></h6>
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

        // Vorherige Daten laden für Änderungsvergleich
        $st = $pdo->prepare("SELECT p.*, u.label AS unit_label, o.city, o.street, o.house_no FROM protocols p JOIN units u ON u.id=p.unit_id JOIN objects o ON o.id=u.object_id WHERE p.id=? LIMIT 1");
        $st->execute([$id]);
        $oldProtocol = $st->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldProtocol) {
            Flash::add('error', 'Protokoll nicht gefunden.');
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

        // Änderungen ermitteln
        $changes = [];
        if ($oldProtocol['type'] !== $type) $changes['type'] = ['from' => $oldProtocol['type'], 'to' => $type];
        if ($oldProtocol['tenant_name'] !== $tenantName) $changes['tenant_name'] = ['from' => $oldProtocol['tenant_name'], 'to' => $tenantName];
        if ($oldProtocol['owner_id'] !== $ownerId) $changes['owner_id'] = ['from' => $oldProtocol['owner_id'], 'to' => $ownerId];
        if ($oldProtocol['manager_id'] !== $managerId) $changes['manager_id'] = ['from' => $oldProtocol['manager_id'], 'to' => $managerId];
        
        $oldPayload = json_decode($oldProtocol['payload'] ?? '{}', true) ?: [];
        if (!empty($rooms) && $rooms !== ($oldPayload['rooms'] ?? [])) $changes['rooms'] = count($rooms) . ' Räume';
        if (!empty($keys) && $keys !== ($oldPayload['keys'] ?? [])) $changes['keys'] = count($keys) . ' Schlüssel';
        if (!empty($meters) && $meters !== ($oldPayload['meters'] ?? [])) $changes['meters'] = 'Zählerstände';
        if (!empty($meta) && $meta !== ($oldPayload['meta'] ?? [])) $changes['meta'] = 'Zusatzangaben';

        // Protokoll aktualisieren
        try {
            $st = $pdo->prepare("UPDATE protocols SET type=?, tenant_name=?, payload=?, owner_id=?, manager_id=?, updated_at=NOW() WHERE id=?");
            $result = $st->execute([$type, $tenantName, json_encode($payload, JSON_UNESCAPED_UNICODE), $ownerId, $managerId, $id]);
        } catch (\Throwable $e) {
            Flash::add('error', 'Fehler beim Speichern: ' . $e->getMessage());
            header('Location: /protocols/edit?id=' . urlencode($id));
            return;
        }

        // Neue Version erstellen
        $nextVersion = 1; // Fallback
        $currentUser = Auth::user()['email'] ?? 'system';
        
        try {
            $versionSt = $pdo->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM protocol_versions WHERE protocol_id=?");
            $versionSt->execute([$id]);
            $nextVersion = (int)$versionSt->fetchColumn();

            $st = $pdo->prepare("INSERT INTO protocol_versions (id, protocol_id, version_no, data, created_by, created_at) VALUES (UUID(), ?, ?, ?, ?, NOW())");
            $result = $st->execute([$id, $nextVersion, json_encode($payload, JSON_UNESCAPED_UNICODE), $currentUser]);
        } catch (\Throwable $e) {
            error_log("Version INSERT Fehler: " . $e->getMessage());
        }

        // Event für neue Version loggen
        try {
            $changeText = empty($changes) ? 'Keine Änderungen' : 'Geändert: ' . implode(', ', array_keys($changes));
            
            $pdo->prepare("INSERT INTO protocol_events (id, protocol_id, type, message, created_at) VALUES (UUID(), ?, 'other', ?, NOW())")
                ->execute([$id, "Version $nextVersion erstellt von $currentUser - $changeText"]);
                
            // SystemLogger: Protokoll aktualisiert mit Änderungen
            if (class_exists('\\App\\SystemLogger')) {
                $protocolData = [
                    'tenant_name' => $tenantName,
                    'type' => $type,
                    'city' => $oldProtocol['city'] ?? '',
                    'street' => $oldProtocol['street'] ?? '',
                    'unit' => $oldProtocol['unit_label'] ?? ''
                ];
                SystemLogger::logProtocolUpdated($id, $protocolData, $changes);
            }
        } catch (\Throwable $e) {
            error_log("Event-Logging fehlgeschlagen: ".$e->getMessage());
        }

        Flash::add('success', 'Protokoll gespeichert und neue Version (' . $nextVersion . ') erstellt.');
        header('Location: /protocols/edit?id=' . urlencode($id));
    }

    /** PDF-Generierung mit Versionierung für Wohnungsübergabeprotokolle */
    public function generateVersionedPDF(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        
        $protocolId = (string)($_GET['id'] ?? '');
        $version = (int)($_GET['version'] ?? 1);
        
        if ($protocolId === '') {
            http_response_code(400);
            echo 'Protokoll-ID fehlt';
            return;
        }

        // Protokoll mit Versionsdaten laden
        $stmt = $pdo->prepare("
            SELECT p.*, pv.version_number, pv.version_data, pv.created_at as version_created,
                   u.label AS unit_label, o.city, o.street, o.house_no,
                   ow.name as owner_name, ow.company as owner_company,
                   m.name as manager_name, m.company as manager_company
            FROM protocols p 
            LEFT JOIN protocol_versions pv ON p.id = pv.protocol_id AND pv.version_number = ?
            JOIN units u ON u.id = p.unit_id 
            JOIN objects o ON o.id = u.object_id
            LEFT JOIN owners ow ON ow.id = p.owner_id
            LEFT JOIN managers m ON m.id = p.manager_id
            WHERE p.id = ? AND p.deleted_at IS NULL
            LIMIT 1
        ");
        
        $stmt->execute([$version, $protocolId]);
        $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$protocol) {
            http_response_code(404);
            echo 'Protokoll oder Version nicht gefunden';
            return;
        }

        // Versionsdaten oder aktuelle Daten verwenden
        $payload = json_decode($protocol['version_data'] ?? $protocol['payload'] ?? '{}', true) ?: [];
        
        // PDF-Pfad für diese Version bestimmen
        $pdfFileName = "protokoll_{$protocolId}_v{$version}.pdf";
        $pdfPath = __DIR__ . '/../../storage/pdfs/' . $pdfFileName;
        
        // Verzeichnis erstellen falls nicht vorhanden
        $pdfDir = dirname($pdfPath);
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        // PDF generieren falls nicht vorhanden oder veraltet
        if (!file_exists($pdfPath) || $this->shouldRegeneratePDF($pdfPath, $protocol)) {
            $this->generatePDFFile($protocol, $payload, $pdfPath, $version);
            
            // PDF-Info in Datenbank speichern
            $this->savePDFInfo($pdo, $protocolId, $version, $pdfFileName);
        }

        // PDF ausliefern
        $this->servePDF($pdfPath, $pdfFileName);
    }

    /** Legacy PDF-Methode für Rückwärtskompatibilität */
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
        
        // Redirect zur neuen PDF-Route
        $version = ($ver === 'latest') ? '1' : (int)$ver;
        header("Location: /protocols/pdf?id={$pid}&version={$version}");
        exit;
    }

    /** Mail versenden */
    public function send(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $pid = (string)($_GET['protocol_id'] ?? ''); 
        $to = (string)($_GET['to'] ?? 'owner');
        
        if ($pid === '') { 
            Flash::add('error','protocol_id fehlt');
            header('Location: /protocols');
            return; 
        }

        $st = $pdo->prepare("SELECT p.*,u.label AS unit_label,o.city,o.street,o.house_no FROM protocols p JOIN units u ON u.id=p.unit_id JOIN objects o ON o.id=u.object_id WHERE p.id=? LIMIT 1");
        $st->execute([$pid]); 
        $p = $st->fetch(PDO::FETCH_ASSOC);
        
        if (!$p) { 
            Flash::add('error','Protokoll nicht gefunden');
            header('Location: /protocols');
            return; 
        }
        
        Flash::add('success', 'E-Mail-Versand (Stub) ausgelöst für: ' . $to);
        header('Location: /protocols/edit?id=' . urlencode($pid));
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

    // ==============================================
    // PDF-VERSIONIERUNG METHODEN
    // ==============================================

    /** Erstellt neue Version eines Protokolls */
    public function createVersion(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        
        $protocolId = (string)($_POST['protocol_id'] ?? '');
        $notes = trim((string)($_POST['version_notes'] ?? ''));
        
        if ($protocolId === '') {
            Flash::add('error', 'Protokoll-ID fehlt');
            header("Location: /protocols");
            return;
        }

        // Aktuelles Protokoll laden
        $stmt = $pdo->prepare("SELECT * FROM protocols WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$protocolId]);
        $protocol = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$protocol) {
            Flash::add('error', 'Protokoll nicht gefunden');
            header("Location: /protocols");
            return;
        }

        // Nächste Versionsnummer ermitteln
        $versionStmt = $pdo->prepare("SELECT MAX(version_number) as max_version FROM protocol_versions WHERE protocol_id = ?");
        $versionStmt->execute([$protocolId]);
        $maxVersion = (int)($versionStmt->fetchColumn() ?? 0);
        $newVersion = $maxVersion + 1;

        // Neue Version speichern
        $insertStmt = $pdo->prepare("
            INSERT INTO protocol_versions (protocol_id, version_number, version_data, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $insertStmt->execute([
            $protocolId,
            $newVersion,
            $protocol['payload'],
            $notes,
            $_SESSION['user_id'] ?? null
        ]);

        // Audit-Log
        try {
            $auditStmt = $pdo->prepare("
                INSERT INTO protocol_events (id, protocol_id, type, message, created_at)
                VALUES (UUID(), ?, 'version_created', ?, NOW())
            ");
            
            $auditStmt->execute([
                $protocolId,
                json_encode(['version' => $newVersion, 'notes' => $notes])
            ]);
        } catch (\Throwable $e) {
            error_log("Audit-Log Fehler: " . $e->getMessage());
        }

        Flash::add('success', "Version {$newVersion} erfolgreich erstellt");
        header("Location: /protocols/edit?id={$protocolId}");
    }

    /** Gibt Versionsliste als JSON zurück */
    public function getVersionsJSON(): void
    {
        $pdo = Database::pdo();
        $protocolId = (string)($_GET['id'] ?? '');
        
        if ($protocolId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Protocol ID required']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT pv.version_number, pv.created_at, pv.notes,
                   pp.file_name, pp.file_size, pp.generated_at,
                   u.name as created_by_name
            FROM protocol_versions pv
            LEFT JOIN protocol_pdfs pp ON pv.protocol_id = pp.protocol_id 
                                        AND pv.version_number = pp.version_number
            LEFT JOIN users u ON pv.created_by = u.id
            WHERE pv.protocol_id = ?
            ORDER BY pv.version_number DESC
        ");
        
        $stmt->execute([$protocolId]);
        $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Daten aufbereiten
        foreach ($versions as &$version) {
            $version['created_at_formatted'] = date('d.m.Y H:i', strtotime($version['created_at']));
            $version['has_pdf'] = !empty($version['file_name']);
            $version['file_size_formatted'] = $this->formatFileSize((int)($version['file_size'] ?? 0));
            $version['pdf_url'] = "/protocols/pdf?id={$protocolId}&version={$version['version_number']}";
            
            if ($version['generated_at']) {
                $version['generated_at_formatted'] = date('d.m.Y H:i', strtotime($version['generated_at']));
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($versions);
    }

    /** Prüft PDF-Status für eine Version */
    public function getPDFStatus(): void
    {
        $pdo = Database::pdo();
        $protocolId = (string)($_GET['id'] ?? '');
        $version = (int)($_GET['version'] ?? 0);
        
        if ($protocolId === '' || $version <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT file_name, file_size, generated_at,
                   CASE WHEN file_name IS NOT NULL THEN 1 ELSE 0 END as exists
            FROM protocol_pdfs 
            WHERE protocol_id = ? AND version_number = ?
        ");
        
        $stmt->execute([$protocolId, $version]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            $result = ['exists' => 0, 'file_name' => null, 'file_size' => null, 'generated_at' => null];
        }
        
        // Dateisystem prüfen
        if ($result['exists'] && $result['file_name']) {
            $filePath = __DIR__ . '/../../storage/pdfs/' . $result['file_name'];
            $result['file_exists_on_disk'] = file_exists($filePath);
            if ($result['file_exists_on_disk']) {
                $result['file_size_actual'] = filesize($filePath);
                $result['file_mtime'] = date('Y-m-d H:i:s', filemtime($filePath));
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /** Generiert PDFs für alle Versionen eines Protokolls */
    public function generateAllPDFs(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $protocolId = (string)($_POST['protocol_id'] ?? '');
        
        if ($protocolId === '') {
            Flash::add('error', 'Protokoll-ID fehlt');
            header("Location: /protocols");
            return;
        }
        
        // Alle Versionen ohne PDF laden
        $stmt = $pdo->prepare("
            SELECT pv.version_number, pv.version_data
            FROM protocol_versions pv
            LEFT JOIN protocol_pdfs pp ON pv.protocol_id = pp.protocol_id 
                                        AND pv.version_number = pp.version_number
            WHERE pv.protocol_id = ? AND pp.id IS NULL
            ORDER BY pv.version_number
        ");
        
        $stmt->execute([$protocolId]);
        $versionsToGenerate = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($versionsToGenerate)) {
            Flash::add('info', 'Alle PDFs sind bereits vorhanden');
            header("Location: /protocols/edit?id={$protocolId}");
            return;
        }
        
        // Protokoll-Stammdaten laden
        $protocolStmt = $pdo->prepare("
            SELECT p.*, u.label AS unit_label, o.city, o.street, o.house_no,
                   ow.name as owner_name, ow.company as owner_company,
                   m.name as manager_name, m.company as manager_company
            FROM protocols p 
            JOIN units u ON u.id = p.unit_id 
            JOIN objects o ON o.id = u.object_id
            LEFT JOIN owners ow ON ow.id = p.owner_id
            LEFT JOIN managers m ON m.id = p.manager_id
            WHERE p.id = ? AND p.deleted_at IS NULL
        ");
        
        $protocolStmt->execute([$protocolId]);
        $protocol = $protocolStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$protocol) {
            Flash::add('error', 'Protokoll nicht gefunden');
            header("Location: /protocols");
            return;
        }
        
        $generatedCount = 0;
        $errors = [];
        
        foreach ($versionsToGenerate as $versionData) {
            try {
                $version = (int)$versionData['version_number'];
                $payload = json_decode($versionData['version_data'] ?? '{}', true) ?: [];
                
                $pdfFileName = "protokoll_{$protocolId}_v{$version}.pdf";
                $pdfPath = __DIR__ . '/../../storage/pdfs/' . $pdfFileName;
                
                $this->generatePDFFile($protocol, $payload, $pdfPath, $version);
                $this->savePDFInfo($pdo, $protocolId, $version, $pdfFileName);
                
                $generatedCount++;
                
            } catch (\Throwable $e) {
                $errors[] = "Version {$version}: " . $e->getMessage();
            }
        }
        
        if ($generatedCount > 0) {
            Flash::add('success', "{$generatedCount} PDF(s) erfolgreich generiert");
        }
        
        if (!empty($errors)) {
            Flash::add('error', 'Fehler bei der Generierung: ' . implode(', ', $errors));
        }
        
        header("Location: /protocols/edit?id={$protocolId}");
    }

    // ==============================================
    // HELPER METHODEN
    // ==============================================

    /** Generiert PDF-Datei für spezifische Version */
    private function generatePDFFile(array $protocol, array $payload, string $pdfPath, int $version): void
    {
        // Einfache PDF-Generierung (später durch dompdf ersetzen)
        $title = $protocol['city'] . ', ' . $protocol['street'] . ' ' . $protocol['house_no'] . ' – ' . ($protocol['unit_label'] ?? 'N/A');
        $typeLabel = $this->getTypeLabel($protocol['type'] ?? '');
        $createdAt = date('d.m.Y H:i', strtotime($protocol['created_at']));
        
        // Einfacher PDF-Inhalt für den Anfang
        $pdfContent = '%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj

2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj

3 0 obj
<<
/Type /Page
/Parent 2 0 R
/MediaBox [0 0 612 792]
/Contents 4 0 R
/Resources <<
/Font <<
/F1 5 0 R
>>
>>
>>
endobj

4 0 obj
<<
/Length 200
>>
stream
BT
/F1 18 Tf
50 750 Td
(Wohnungsuebergabeprotokoll v' . $version . ') Tj
0 -30 Td
/F1 12 Tf
(' . addslashes($title) . ') Tj
0 -20 Td
(' . addslashes($typeLabel) . ') Tj
0 -20 Td
(Erstellt: ' . $createdAt . ') Tj
0 -20 Td
(Mieter: ' . addslashes($protocol['tenant_name'] ?? '') . ') Tj
ET
endstream
endobj

5 0 obj
<<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
>>
endobj

xref
0 6
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000274 00000 n 
0000000530 00000 n 
trailer
<<
/Size 6
/Root 1 0 R
>>
startxref
630
%%EOF';
        
        file_put_contents($pdfPath, $pdfContent);
    }

    /** Speichert PDF-Informationen in der Datenbank */
    private function savePDFInfo(\PDO $pdo, string $protocolId, int $version, string $fileName): void
    {
        $filePath = __DIR__ . '/../../storage/pdfs/' . $fileName;
        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        
        $stmt = $pdo->prepare("
            INSERT INTO protocol_pdfs (protocol_id, version_number, file_name, file_size, generated_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            file_name = VALUES(file_name),
            file_size = VALUES(file_size),
            generated_at = NOW()
        ");
        
        $stmt->execute([$protocolId, $version, $fileName, $fileSize]);
    }

    /** Prüft ob PDF neu generiert werden soll */
    private function shouldRegeneratePDF(string $pdfPath, array $protocol): bool
    {
        if (!file_exists($pdfPath)) return true;
        
        $pdfTime = filemtime($pdfPath);
        $protocolTime = strtotime($protocol['updated_at'] ?? $protocol['created_at']);
        
        return $protocolTime > $pdfTime;
    }

    /** Liefert PDF-Datei aus */
    private function servePDF(string $pdfPath, string $fileName): void
    {
        if (!file_exists($pdfPath)) {
            http_response_code(404);
            echo 'PDF-Datei nicht gefunden';
            return;
        }
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($pdfPath));
        header('Cache-Control: public, max-age=3600');
        
        readfile($pdfPath);
        exit;
    }

    /** Formatiert Dateigröße */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /** Gibt Label für Protokolltyp zurück */
    private function getTypeLabel(string $type): string
    {
        return match($type) {
            'einzug' => 'Einzugsprotokoll',
            'auszug' => 'Auszugsprotokoll',
            'zwischen' => 'Zwischenprotokoll',
            default => ucfirst($type)
        };
    }

    /** Rendert den PDF-Versionen-Tab */
    private function renderPDFVersionsTab(string $protocolId): string
    {
        $pdo = Database::pdo();
        
        // Prüfe ob die erforderlichen Tabellen existieren
        try {
            $pdo->query("SELECT 1 FROM protocol_versions LIMIT 1");
            $pdo->query("SELECT 1 FROM protocol_pdfs LIMIT 1");
        } catch (\PDOException $e) {
            // Tabellen existieren noch nicht - Migration erforderlich
            return $this->renderPDFVersionsMigrationRequired($protocolId);
        }
        
        // Aktuelle Versionen laden
        $stmt = $pdo->prepare("
            SELECT pv.version_no, pv.created_at, pv.created_by,
                   pv.pdf_path, pv.signed_pdf_path, pv.signed_at
            FROM protocol_versions pv
            WHERE pv.protocol_id = ?
            ORDER BY pv.version_no DESC
        ");
        
        $stmt->execute([$protocolId]);
        $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<div class="d-flex justify-content-between align-items-center mb-4">';
        $html .= '<div>';
        $html .= '<h5><i class="bi bi-file-earmark-pdf text-primary"></i> PDF-Versionen</h5>';
        $html .= '<p class="text-muted mb-0">Verwalten Sie versionierte PDFs für dieses Protokoll</p>';
        $html .= '</div>';
        $html .= '<div class="btn-group" role="group">';
        $html .= '<button type="button" class="btn btn-outline-primary btn-sm" onclick="generateAllPDFs(\'' . $h($protocolId) . '\')">';
        $html .= '<i class="bi bi-files"></i> Alle PDFs generieren';
        $html .= '</button>';
        $html .= '<button type="button" class="btn btn-primary btn-sm" onclick="createNewVersion(\'' . $h($protocolId) . '\')">';
        $html .= '<i class="bi bi-plus-circle"></i> Neue Version';
        $html .= '</button>';
        $html .= '</div></div>';

        if (empty($versions)) {
            $html .= '<div class="alert alert-info">';
            $html .= '<i class="bi bi-info-circle"></i> ';
            $html .= 'Noch keine Versionen vorhanden. Erstellen Sie eine neue Version, um PDFs zu generieren.';
            $html .= '</div>';
            
            // CSS und JavaScript hinzufügen
            $html .= $this->getPDFVersioningAssets($protocolId);
            return $html;
        }

        $html .= '<div class="card">';
        $html .= '<div class="card-body p-0">';
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-hover mb-0">';
        $html .= '<thead class="table-light">';
        $html .= '<tr>';
        $html .= '<th style="width: 10%;">Version</th>';
        $html .= '<th style="width: 20%;">Erstellt</th>';
        $html .= '<th style="width: 25%;">Notizen</th>';
        $html .= '<th style="width: 25%;">PDF-Status</th>';
        $html .= '<th style="width: 20%;">Aktionen</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($versions as $version) {
            $versionNum = (int)$version['version_no'];
            $createdAt = date('d.m.Y H:i', strtotime($version['created_at']));
            $notes = $h($version['notes'] ?? '');
            $createdBy = $h($version['created_by'] ?? 'System');
            
            $html .= '<tr>';
            
            // Version
            $badgeClass = $versionNum === 1 ? 'bg-primary' : 'bg-secondary';
            $html .= '<td>';
            $html .= "<span class=\"badge {$badgeClass} fs-6\">v{$versionNum}</span>";
            if ($versionNum === 1) {
                $html .= '<br><small class="text-muted">Original</small>';
            }
            $html .= '</td>';
            
            // Erstellt
            $html .= '<td>';
            $html .= "<strong>{$createdAt}</strong><br>";
            $html .= "<small class=\"text-muted\">von {$createdBy}</small>";
            $html .= '</td>';
            
            // Notizen
            $html .= '<td>';
            if ($notes) {
                $shortNotes = strlen($notes) > 50 ? substr($notes, 0, 50) . '...' : $notes;
                $html .= "<span title=\"" . $h($notes) . "\">" . $h($shortNotes) . "</span>";
            } else {
                $html .= '<em class="text-muted">Keine Notizen</em>';
            }
            $html .= '</td>';
            
            // PDF-Status
            $html .= '<td>';
            if (!empty($version['file_name'])) {
                $fileSize = $this->formatFileSize((int)($version['file_size'] ?? 0));
                $generatedAt = date('d.m.Y H:i', strtotime($version['generated_at']));
                
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<span class="pdf-status-indicator available"></span>';
                $html .= '<div>';
                $html .= "<a href=\"/protocols/pdf?id={$protocolId}&version={$versionNum}\" ";
                $html .= 'class="btn btn-sm btn-success" target="_blank">';
                $html .= "<i class=\"bi bi-download\"></i> PDF ({$fileSize})";
                $html .= '</a>';
                $html .= "<br><small class=\"text-muted\">Erstellt: {$generatedAt}</small>";
                $html .= '</div></div>';
            } else {
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<span class="pdf-status-indicator error"></span>';
                $html .= '<div>';
                $html .= "<button class=\"btn btn-sm btn-outline-secondary\" onclick=\"generatePDF('{$protocolId}', {$versionNum})\">";
                $html .= '<i class="bi bi-gear"></i> Generieren';
                $html .= '</button>';
                $html .= '<br><small class="text-muted">Nicht verfügbar</small>';
                $html .= '</div></div>';
            }
            $html .= '</td>';
            
            // Aktionen
            $html .= '<td>';
            $html .= '<div class="btn-group btn-group-sm" role="group">';
            
            // Vorschau
            if (!empty($version['file_name'])) {
                $html .= "<button class=\"btn btn-outline-info\" onclick=\"previewVersion('{$protocolId}', {$versionNum})\" title=\"Vorschau\">";
                $html .= '<i class="bi bi-eye"></i>';
                $html .= '</button>';
            }
            
            // Details
            $html .= "<button class=\"btn btn-outline-primary\" onclick=\"showVersionDetails({$versionNum})\" title=\"Details\">";
            $html .= '<i class="bi bi-info-circle"></i>';
            $html .= '</button>';
            
            $html .= '</div></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div></div></div>';

        // Statistiken
        $totalVersions = count($versions);
        $pdfsGenerated = count(array_filter($versions, fn($v) => !empty($v['file_name'])));
        $totalSize = array_sum(array_map(fn($v) => (int)($v['file_size'] ?? 0), $versions));

        $html .= '<div class="row mt-4">';
        $html .= '<div class="col-md-4">';
        $html .= '<div class="card border-primary">';
        $html .= '<div class="card-body text-center">';
        $html .= '<h5 class="card-title text-primary">' . $totalVersions . '</h5>';
        $html .= '<p class="card-text">Gesamt-Versionen</p>';
        $html .= '</div></div></div>';
        
        $html .= '<div class="col-md-4">';
        $html .= '<div class="card border-success">';
        $html .= '<div class="card-body text-center">';
        $html .= '<h5 class="card-title text-success">' . $pdfsGenerated . '</h5>';
        $html .= '<p class="card-text">PDFs verfügbar</p>';
        $html .= '</div></div></div>';
        
        $html .= '<div class="col-md-4">';
        $html .= '<div class="card border-info">';
        $html .= '<div class="card-body text-center">';
        $html .= '<h5 class="card-title text-info">' . $this->formatFileSize($totalSize) . '</h5>';
        $html .= '<p class="card-text">Gesamt-Größe</p>';
        $html .= '</div></div></div>';
        $html .= '</div>';

        // CSS und JavaScript hinzufügen
        $html .= $this->getPDFVersioningAssets($protocolId);

        return $html;
    }

    /** Zeigt Migrations-Hinweis an, wenn PDF-Versionierung noch nicht verfügbar ist */
    private function renderPDFVersionsMigrationRequired(string $protocolId): string
    {
        $html = '<div class="alert alert-warning">';
        $html .= '<div class="d-flex align-items-center">';
        $html .= '<i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 2rem;"></i>';
        $html .= '<div>';
        $html .= '<h5 class="alert-heading mb-2">PDF-Versionierung nicht verfügbar</h5>';
        $html .= '<p class="mb-2">Die PDF-Versionierung benötigt eine Datenbank-Migration.</p>';
        $html .= '<hr>';
        $html .= '<h6>So führen Sie die Migration aus:</h6>';
        $html .= '<ol class="mb-3">';
        $html .= '<li>Terminal öffnen und ins Projektverzeichnis wechseln</li>';
        $html .= '<li>Ausführen: <code>docker compose exec -T db mariadb -uroot -proot app < migrations/007_pdf_versioning.sql</code></li>';
        $html .= '<li>Seite neu laden</li>';
        $html .= '</ol>';
        $html .= '<p class="mb-0">';
        $html .= '<strong>Alternativ:</strong> Verwenden Sie das Update-Script: <code>./run_migration.sh</code>';
        $html .= '</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Legacy PDF-Link anbieten
        $html .= '<div class="card">';
        $html .= '<div class="card-body">';
        $html .= '<h6>Aktuelle PDF-Generierung (Legacy)</h6>';
        $html .= '<p class="text-muted">Bis zur Migration können Sie weiterhin die normale PDF-Funktion verwenden:</p>';
        $html .= '<a href="/protocols/pdf?protocol_id=' . htmlspecialchars($protocolId) . '&version=latest" class="btn btn-primary" target="_blank">';
        $html .= '<i class="bi bi-file-earmark-pdf"></i> PDF generieren (Legacy)';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    /** Lädt JavaScript und CSS für PDF-Versionierung */
    private function getPDFVersioningAssets(string $protocolId): string
    {
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        
        $html = '<style>
.pdf-status-indicator {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 8px;
    flex-shrink: 0;
}

.pdf-status-indicator.available {
    background-color: #28a745;
    box-shadow: 0 0 5px rgba(40, 167, 69, 0.5);
}

.pdf-status-indicator.generating {
    background-color: #ffc107;
    animation: pulse 1.5s infinite;
}

.pdf-status-indicator.error {
    background-color: #dc3545;
}

@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.1); }
    100% { opacity: 1; transform: scale(1); }
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,.075);
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0,0,0,.125);
}

.alert {
    border: none;
    border-radius: 0.5rem;
}
</style>';

        $html .= '<script>
// PDF-Verwaltung Funktionen
function generatePDF(protocolId, version) {
    const button = event.target.closest("button");
    const originalContent = button.innerHTML;
    
    button.disabled = true;
    button.innerHTML = `<i class="bi bi-hourglass-split"></i> Generiere...`;
    
    fetch(`/protocols/pdf?id=${protocolId}&version=${version}`)
        .then(response => {
            if (response.ok) {
                showToast("PDF erfolgreich generiert!", "success");
                setTimeout(() => window.location.reload(), 1500);
            } else {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
        })
        .catch(error => {
            console.error("PDF-Generierung fehlgeschlagen:", error);
            showToast("Fehler bei der PDF-Generierung: " + error.message, "error");
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalContent;
        });
}

function generateAllPDFs(protocolId) {
    if (!confirm("Möchten Sie PDFs für alle Versionen ohne PDF generieren?")) {
        return;
    }
    
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "/protocols/generate-all-pdfs";
    
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "protocol_id";
    input.value = protocolId;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function createNewVersion(protocolId) {
    const notes = prompt("Notizen für die neue Version (optional):");
    if (notes === null) return;
    
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "/protocols/create-version";
    
    const protocolInput = document.createElement("input");
    protocolInput.type = "hidden";
    protocolInput.name = "protocol_id";
    protocolInput.value = protocolId;
    
    const notesInput = document.createElement("input");
    notesInput.type = "hidden";
    notesInput.name = "version_notes";
    notesInput.value = notes || "";
    
    form.appendChild(protocolInput);
    form.appendChild(notesInput);
    document.body.appendChild(form);
    form.submit();
}

function previewVersion(protocolId, version) {
    window.open(`/protocols/pdf?id=${protocolId}&version=${version}`, "_blank");
}

function showVersionDetails(version) {
    alert(`Details für Version ${version} werden in einem zukünftigen Update verfügbar sein.`);
}

function showToast(message, type = "info") {
    const toastContainer = getOrCreateToastContainer();
    
    const toast = document.createElement("div");
    toast.className = `alert alert-${type === "success" ? "success" : "danger"} alert-dismissible fade show position-fixed`;
    toast.style.cssText = "top: 20px; right: 20px; z-index: 9999; min-width: 300px;";
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    toastContainer.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function getOrCreateToastContainer() {
    let container = document.getElementById("toast-container");
    if (!container) {
        container = document.createElement("div");
        container.id = "toast-container";
        container.className = "toast-container position-fixed top-0 end-0 p-3";
        container.style.zIndex = "9999";
        document.body.appendChild(container);
    }
    return container;
}
</script>';

        return $html;
    }
}
