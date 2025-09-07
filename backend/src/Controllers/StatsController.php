<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\View;
use PDO;

final class StatsController
{
    public function index(): void
    {
        Auth::requireAuth();
        $pdo  = Database::pdo();
        $year = (int)($_GET['year'] ?? (int)date('Y'));
        if ($year < 2000 || $year > 2100) $year = (int)date('Y');

        // ---- Protokolle (Basis) ----
        $st = $pdo->query("
          SELECT p.id, p.unit_id, p.type, p.payload, p.created_at,
                 u.label AS unit_label, o.id AS object_id, o.city, o.postal_code, o.street, o.house_no
          FROM protocols p
          JOIN units u ON u.id=p.unit_id
          JOIN objects o ON o.id=u.object_id
          WHERE p.deleted_at IS NULL
          ORDER BY o.city,o.street,o.house_no,u.label,p.created_at
        ");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Einheiten je Haus (für Quote)
        $unitsByHouse = [];
        $rs = $pdo->query("SELECT object_id, COUNT(*) AS n FROM units GROUP BY object_id");
        foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $unitsByHouse[$r['object_id']] = (int)$r['n'];
        }

        // Fotos je Protokoll (für Qualitätsscore)
        $photosByProtocol = [];
        $rs = $pdo->query("SELECT protocol_id, COUNT(*) AS n FROM protocol_files WHERE protocol_id IS NOT NULL GROUP BY protocol_id");
        foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $photosByProtocol[$r['protocol_id']] = (int)$r['n'];
        }

        // Helper
        $payload = function (array $r): array {
            return json_decode((string)($r['payload'] ?? '{}'), true) ?: [];
        };
        $tsOf = function (array $p, string $created): int {
            $ts = (string)($p['timestamp'] ?? '');
            $ts = str_replace('T', ' ', $ts);
            return $ts ? (int)strtotime($ts) : (int)strtotime($created);
        };
        $toFloat = function ($v): ?float {
            if ($v === '' || $v === null) return null;
            $s = str_replace([',', ' '], ['.', ''], (string)$v);
            return is_numeric($s) ? (float)$s : null;
        };

        // ---- Aggregate vorbereiten ----
        $meterKeys = [
            "strom_we" => "Strom (WE)",      "strom_allg" => "Strom (Allg.)",
            "gas_we"   => "Gas (WE)",        "gas_allg"   => "Gas (Allg.)",
            "wasser_kueche_kalt" => "Wasser Küche (kalt)", "wasser_kueche_warm" => "Wasser Küche (warm)",
            "wasser_bad_kalt"    => "Wasser Bad (kalt)",   "wasser_bad_warm"    => "Wasser Bad (warm)",
            "wasser_wm"          => "Wasser WM"
        ];
        $mAgg = []; foreach (array_keys($meterKeys) as $k) $mAgg[$k] = ['sum' => 0.0, 'n' => 0];

        $flukByHouse = [];        // Haus-Auszüge
        $flukByUnit  = [];        // WE-Auszüge
        $monthsEin   = array_fill(1, 12, 0);
        $monthsAus   = array_fill(1, 12, 0);
        $monthsZwischen = array_fill(1, 12, 0);
        $byUnit      = [];
        $countEinzug = 0; $countAuszug = 0; $countZwischen = 0;

        // NEU: Zusätzliche Statistiken
        $protocolsPerMonth = array_fill(1, 12, 0); // Alle Protokolle pro Monat
        $qualityScoreByMonth = array_fill(1, 12, ['total' => 0, 'count' => 0]); // Qualität pro Monat
        $protokolleByWeekday = array_fill(0, 7, 0); // Mo-So (7 Elemente)
        $protokolleByHour = array_fill(0, 24, 0); // 0-23 Uhr (24 Elemente)
        $avgPhotosByType = ['einzug' => ['total' => 0, 'count' => 0], 'auszug' => ['total' => 0, 'count' => 0], 'zwischen' => ['total' => 0, 'count' => 0]];
        $consentsStats = ['privacy' => 0, 'marketing' => 0, 'total' => 0];
        $roomTypesCount = [];

        foreach ($rows as $r) {
            $p  = $payload($r);
            $t  = $tsOf($p, (string)$r['created_at']);
            $uid = (string)$r['unit_id'];
            $byUnit[$uid][] = [
                'type'      => (string)$r['type'],
                'ts'        => $t,
                'meters'    => (array)($p['meters'] ?? []),
                'object_id' => (string)$r['object_id'],
            ];

            // NEU: Erweiterte Statistikerfassung
            $ct = (int)strtotime((string)$r['created_at']);
            $yearOfProtocol = (int)date('Y', $ct);
            
            if ($yearOfProtocol === $year) {
                $m = (int)date('n', $ct);
                $protocolsPerMonth[$m]++;
                
                // Wochentag-Statistik
                $weekday = (int)date('w', $ct);
                $protokolleByWeekday[$weekday == 0 ? 6 : $weekday - 1]++;
                
                // Uhrzeiten-Statistik
                $hour = (int)date('G', $ct);
                if ($hour >= 0 && $hour <= 23) {
                    $protokolleByHour[$hour]++;
                }
                
                // Nach Typ
                if ($r['type'] === 'einzug') {
                    $monthsEin[$m]++;
                    $countEinzug++;
                } elseif ($r['type'] === 'auszug') {
                    $monthsAus[$m]++;
                    $countAuszug++;
                } elseif ($r['type'] === 'zwischen') {
                    $monthsZwischen[$m]++;
                    $countZwischen++;
                }
                
                // Qualitätsscore pro Monat
                $crit = 0; $hit = 0;
                $addr = (array)($p['address'] ?? []);
                $okAddr = (trim((string)($addr['city'] ?? '')) !== '' && trim((string)($addr['street'] ?? '')) !== '' && trim((string)($addr['house_no'] ?? '')) !== '');
                $crit++; if ($okAddr) $hit++;
                $crit++; if (!empty($p['rooms'])) $hit++;
                $crit++; $okMeter=false; foreach ((array)($p['meters'] ?? []) as $meter) { if (trim((string)($meter['val'] ?? '')) !== '') { $okMeter = true; break; } } if ($okMeter) $hit++;
                $crit++; if (!empty($p['keys'])) $hit++;
                $crit++; if (($photosByProtocol[$r['id']] ?? 0) > 0) $hit++;
                $crit++; if (!empty(($p['meta']['consents']['privacy'] ?? false))) $hit++;
                
                $score = $crit ? round(($hit / $crit) * 100.0, 1) : 0.0;
                $qualityScoreByMonth[$m]['total'] += $score;
                $qualityScoreByMonth[$m]['count']++;
            }
            
            // Fotos pro Typ
            $photoCount = $photosByProtocol[$r['id']] ?? 0;
            if (isset($avgPhotosByType[$r['type']])) {
                $avgPhotosByType[$r['type']]['total'] += $photoCount;
                $avgPhotosByType[$r['type']]['count']++;
            }
            
            // Consents
            if (!empty($p['meta']['consents']['privacy'])) $consentsStats['privacy']++;
            if (!empty($p['meta']['consents']['marketing'])) $consentsStats['marketing']++;
            $consentsStats['total']++;
            
            // Raumtypen zählen
            foreach ((array)($p['rooms'] ?? []) as $room) {
                $roomType = (string)($room['type'] ?? 'Unbekannt');
                $roomTypesCount[$roomType] = ($roomTypesCount[$roomType] ?? 0) + 1;
            }

            if ($r['type'] === 'auszug') {
                $hid = (string)$r['object_id'];
                if (!isset($flukByHouse[$hid])) {
                    $flukByHouse[$hid] = [
                        'object_id' => $hid,
                        'postal'    => (string)$r['postal_code'],
                        'city'      => (string)$r['city'],
                        'street'    => (string)$r['street'],
                        'house_no'  => (string)$r['house_no'],
                        'count'     => 0
                    ];
                }
                $flukByHouse[$hid]['count']++;

                $flukByUnit[$uid] = ($flukByUnit[$uid] ?? [
                    'postal'     => (string)$r['postal_code'],
                    'city'       => (string)$r['city'],
                    'street'     => (string)$r['street'],
                    'house_no'   => (string)$r['house_no'],
                    'unit_label' => (string)$r['unit_label'],
                    'count'      => 0
                ]);
                $flukByUnit[$uid]['count']++;
            }
        }

        // ---- Mietdauer & Leerstand (global & je Haus) + Zähler-Δ ----
        $durDays = [];                 // global Mietdauer
        $durByHouse = [];              // object_id => [tage...]
        $leerDaysByHouse = [];         // object_id => [tage...]
        $totalLeerDays = 0.0;          // global Leerstandstage
        $totalMietDays = 0.0;          // global Miettage

        foreach ($byUnit as $uid => $evs) {
            usort($evs, fn($a, $b) => $a['ts'] <=> $b['ts']);
            $einTs = null; $einMeters = null; $lastDur = null;
            $lastAuszugTs = null; $lastHouseId = null;

            foreach ($evs as $ev) {
                if ($ev['type'] === 'einzug') {
                    // Leerstand seit letztem Auszug -> aufsummieren (global & je Haus)
                    if ($lastAuszugTs !== null) {
                        $leer = ($ev['ts'] - $lastAuszugTs) / 86400.0;
                        if ($leer > 0) {
                            $totalLeerDays += $leer;
                            if ($lastHouseId !== null) $leerDaysByHouse[$lastHouseId][] = $leer;
                            if ($lastDur !== null) $totalMietDays += max(0.0, (float)$lastDur);
                        }
                        $lastAuszugTs = null;
                    }
                    $einTs     = $ev['ts'];
                    $einMeters = $ev['meters'];
                    $lastHouseId = $ev['object_id'];
                } elseif ($ev['type'] === 'auszug' && $einTs !== null) {
                    // abgeschlossene Mietdauer
                    $d = max(0, (int)floor(($ev['ts'] - $einTs) / 86400));
                    $durDays[] = $d;
                    $hid = $ev['object_id'];
                    $durByHouse[$hid][] = $d;

                    // Zähler-Delta aus Einzug->Auszug
                    foreach ($mAgg as $k => $_) {
                        $a = $toFloat($einMeters[$k]['val'] ?? null);
                        $b = $toFloat($ev['meters'][$k]['val'] ?? null);
                        if ($a !== null && $b !== null) {
                            $mAgg[$k]['sum'] += ($b - $a);
                            $mAgg[$k]['n']   += 1;
                        }
                    }

                    // Für Leerstand bis zum nächsten Einzug merken
                    $lastDur      = $d;
                    $lastAuszugTs = $ev['ts'];
                    $lastHouseId  = $ev['object_id'];
                    $einTs = null; $einMeters = null;
                } elseif ($ev['type'] === 'auszug') {
                    // Auszug ohne vorherigen Einzug -> ab hier Leerstand bis nächster Einzug
                    $lastAuszugTs = $ev['ts'];
                    $lastHouseId  = $ev['object_id'];
                }
            }
        }

        $avgDur  = $durDays ? round(array_sum($durDays) / count($durDays), 1) : null;
        $den     = $totalLeerDays + $totalMietDays;
        $avgLeer = ($den > 0.0) ? round($totalLeerDays / $den * 100.0, 1) : null;

        $avgMeters = [];
        foreach ($mAgg as $k => $v) {
            $avgMeters[$k] = $v['n'] ? round($v['sum'] / $v['n'], 2) : null;
        }

        // Qualitätsscore (einfacher Anteil erfüllter Kriterien)
        $scores = [];
        foreach ($rows as $r) {
            $p = $payload($r);
            $crit = 0; $hit = 0;
            // address
            $crit++; $addr = (array)($p['address'] ?? []);
            $okAddr = (trim((string)($addr['city'] ?? '')) !== '' && trim((string)($addr['street'] ?? '')) !== '' && trim((string)($addr['house_no'] ?? '')) !== '');
            if ($okAddr) $hit++;
            // rooms
            $crit++; if (!empty($p['rooms'])) $hit++;
            // meters any
            $crit++; $okMeter=false; foreach ((array)($p['meters'] ?? []) as $m) { if (trim((string)($m['val'] ?? '')) !== '') { $okMeter = true; break; } } if ($okMeter) $hit++;
            // keys
            $crit++; if (!empty($p['keys'])) $hit++;
            // photos
            $crit++; if (($photosByProtocol[$r['id']] ?? 0) > 0) $hit++;
            // privacy consent
            $crit++; if (!empty(($p['meta']['consents']['privacy'] ?? false))) $hit++;
            $scores[] = $crit ? round(($hit / $crit) * 100.0, 1) : 0.0;
        }
        $scoreAvg = $scores ? round(array_sum($scores) / count($scores), 1) : null;

        // Fluktuation Haus (mit Quote + Ø Mietdauer/Ø Leerstand Tage) sortiert nach Auszügen
        $flukQuote = [];
        foreach ($flukByHouse as $hid => $h) {
            $we = $unitsByHouse[$hid] ?? 0;
            $q  = ($we > 0) ? round(($h['count'] / $we) * 100.0, 1) : null;
            $avgDurHouse  = !empty($durByHouse[$hid])      ? round(array_sum($durByHouse[$hid])      / count($durByHouse[$hid]), 1) : null;
            $avgLeerHouse = !empty($leerDaysByHouse[$hid]) ? round(array_sum($leerDaysByHouse[$hid]) / count($leerDaysByHouse[$hid]), 1) : null;
            $flukQuote[] = [
                'object_id'    => $hid,
                'postal'       => $h['postal'],
                'city'         => $h['city'],
                'street'       => $h['street'],
                'house_no'     => $h['house_no'],
                'we'           => $we,
                'auszuege'     => $h['count'],
                'quote'        => $q,
                'avg_dur_days' => $avgDurHouse,
                'avg_leer_days'=> $avgLeerHouse
            ];
        }
        usort($flukQuote, fn($a, $b) =>
            ($b['auszuege'] <=> $a['auszuege']) ?: (($b['quote'] ?? -1) <=> ($a['quote'] ?? -1))
        );

        // Fluktuation nach Wohnung sortiert
        $flukByUnitList = array_values($flukByUnit);
        usort($flukByUnitList, fn($a, $b) => ($b['count'] <=> $a['count']));

        // Chart-Datensätze vorbereiten
        $labels = []; $dataEin = []; $dataAus = []; $dataZwischen = []; $dataTotal = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[]  = date('M', mktime(0, 0, 0, $m, 1, $year));
            $dataEin[] = $monthsEin[$m];
            $dataAus[] = $monthsAus[$m];
            $dataZwischen[] = $monthsZwischen[$m];
            $dataTotal[] = $protocolsPerMonth[$m];
        }
        
        // Qualitätsscore pro Monat berechnen
        $qualityData = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($qualityScoreByMonth[$m]['count'] > 0) {
                $qualityData[] = round($qualityScoreByMonth[$m]['total'] / $qualityScoreByMonth[$m]['count'], 1);
            } else {
                $qualityData[] = null;
            }
        }
        
        // Fotos-Durchschnitt berechnen
        foreach ($avgPhotosByType as $type => &$data) {
            if ($data['count'] > 0) {
                $data['avg'] = round($data['total'] / $data['count'], 1);
            } else {
                $data['avg'] = 0;
            }
        }
        
        // Top Raumtypen
        arsort($roomTypesCount);
        $topRoomTypes = array_slice($roomTypesCount, 0, 10, true);

        // ---- Render mit ApexCharts ----
        ob_start(); ?>
        
        <!-- Kompakte Styles für Statistik-Seite -->
        <style>
            /* Statistik Karten kompakter */
            .stat-card {
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
                padding: 1rem;
                height: 100%;
                transition: all 0.2s ease;
            }
            
            .stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            }
            
            .stat-value {
                font-size: 1.75rem;
                font-weight: 700;
                color: #111827;
                line-height: 1;
                margin: 0.5rem 0;
            }
            
            .stat-label {
                font-size: 0.75rem;
                color: #6b7280;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.025em;
            }
            
            .stat-change {
                font-size: 0.7rem;
                padding: 0.15rem 0.4rem;
                border-radius: 0.25rem;
                display: inline-flex;
                align-items: center;
                gap: 0.2rem;
                font-weight: 600;
            }
            
            .stat-change.positive {
                background: rgba(16, 185, 129, 0.1);
                color: #10b981;
            }
            
            .stat-change.negative {
                background: rgba(239, 68, 68, 0.1);
                color: #ef4444;
            }
            
            /* Icon Boxes kleiner */
            .icon-box {
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 0.5rem;
                font-size: 1.25rem;
            }
            
            .icon-box.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
            .icon-box.green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
            .icon-box.yellow { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
            .icon-box.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
            .icon-box.red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
            .icon-box.cyan { background: rgba(6, 182, 212, 0.1); color: #06b6d4; }
            
            /* Tabellen kompakter */
            .table-container {
                max-height: 350px;
                overflow-y: auto;
            }
            
            .table-container::-webkit-scrollbar {
                width: 4px;
            }
            
            .table-container::-webkit-scrollbar-track {
                background: #f3f4f6;
            }
            
            .table-container::-webkit-scrollbar-thumb {
                background: #d1d5db;
                border-radius: 2px;
            }
            
            /* Card Überschriften */
            .card-header {
                background: transparent;
                border-bottom: 1px solid #e5e7eb;
                padding: 0.75rem 1rem;
            }
            
            .card-header h5 {
                font-size: 0.875rem;
                font-weight: 600;
                color: #374151;
                margin: 0;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            /* Tabellen kompakter */
            .table thead th {
                background: #f9fafb;
                color: #6b7280;
                font-weight: 600;
                font-size: 0.7rem;
                text-transform: uppercase;
                letter-spacing: 0.025em;
                padding: 0.5rem;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .table tbody td {
                padding: 0.5rem;
                color: #374151;
                font-size: 0.8rem;
                border-bottom: 1px solid #f3f4f6;
            }
            
            .table tbody tr:hover {
                background: #f9fafb;
            }
            
            /* Progress Bars dünner */
            .progress {
                height: 4px;
                background: #e5e7eb;
                border-radius: 2px;
            }
            
            /* Badges kleiner */
            .badge {
                font-weight: 600;
                padding: 0.25rem 0.4rem;
                font-size: 0.7rem;
                letter-spacing: 0.025em;
            }
            
            .badge.bg-success-subtle {
                background: rgba(16, 185, 129, 0.1);
                color: #10b981;
            }
            
            .badge.bg-warning-subtle {
                background: rgba(245, 158, 11, 0.1);
                color: #f59e0b;
            }
            
            .badge.bg-danger-subtle {
                background: rgba(239, 68, 68, 0.1);
                color: #ef4444;
            }
            
            /* Insights Box kompakter */
            .insight-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.75rem;
                background: white;
                border-radius: 0.375rem;
                border: 1px solid #e5e7eb;
            }
            
            .insight-item:hover {
                border-color: #3b82f6;
            }
        </style>

        <div class="container-fluid">
            <!-- Jahresauswahl oben rechts -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Statistik</h4>
                <form method="get" class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 me-2">Jahr:</label>
                    <select class="form-select form-select-sm" name="year" style="width: 100px;">
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <button class="btn btn-sm btn-primary">Anzeigen</button>
                </form>
            </div>

            <!-- KPI-Karten (Erste Reihe) -->
            <div class="row mb-3">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="flex-grow-1">
                                <div class="stat-label">Einzüge</div>
                                <div class="stat-value"><?= number_format($countEinzug, 0, ',', '.') ?></div>
                                <?php 
                                $prevMonthEin = $monthsEin[(int)date('n') - 1] ?? 0;
                                $change = $monthsEin[(int)date('n')] - $prevMonthEin;
                                if ($change != 0): ?>
                                    <span class="stat-change <?= $change > 0 ? 'positive' : 'negative' ?>">
                                        <i class="bi bi-trending-<?= $change > 0 ? 'up' : 'down' ?>" style="font-size: 0.7rem;"></i> 
                                        <?= abs($change) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="icon-box green">
                                <i class="bi bi-box-arrow-in-right"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="flex-grow-1">
                                <div class="stat-label">Auszüge</div>
                                <div class="stat-value"><?= number_format($countAuszug, 0, ',', '.') ?></div>
                                <?php 
                                $prevMonthAus = $monthsAus[(int)date('n') - 1] ?? 0;
                                $change = $monthsAus[(int)date('n')] - $prevMonthAus;
                                if ($change != 0): ?>
                                    <span class="stat-change <?= $change > 0 ? 'negative' : 'positive' ?>">
                                        <i class="bi bi-trending-<?= $change > 0 ? 'up' : 'down' ?>" style="font-size: 0.7rem;"></i> 
                                        <?= abs($change) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="icon-box red">
                                <i class="bi bi-box-arrow-left"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="flex-grow-1">
                                <div class="stat-label">Zwischen</div>
                                <div class="stat-value"><?= number_format($countZwischen, 0, ',', '.') ?></div>
                                <small class="text-muted">Kontrollen</small>
                            </div>
                            <div class="icon-box blue">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="flex-grow-1">
                                <div class="stat-label">Gesamt</div>
                                <div class="stat-value"><?= number_format($countEinzug + $countAuszug + $countZwischen, 0, ',', '.') ?></div>
                                <div class="progress mt-2">
                                    <?php 
                                    $total = $countEinzug + $countAuszug + $countZwischen;
                                    $einPct = $total > 0 ? ($countEinzug / $total * 100) : 0;
                                    $ausPct = $total > 0 ? ($countAuszug / $total * 100) : 0;
                                    ?>
                                    <div class="progress-bar bg-success" style="width: <?= $einPct ?>%"></div>
                                    <div class="progress-bar bg-danger" style="width: <?= $ausPct ?>%"></div>
                                    <div class="progress-bar bg-info" style="width: <?= 100 - $einPct - $ausPct ?>%"></div>
                                </div>
                            </div>
                            <div class="icon-box purple">
                                <i class="bi bi-archive"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zweite Reihe KPIs -->
            <div class="row mb-3">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="stat-label">Ø Mietdauer</div>
                            <div class="d-flex align-items-baseline gap-2">
                                <div class="stat-value"><?= $avgDur !== null ? number_format($avgDur, 0, ',', '.') : '—' ?></div>
                                <span class="text-muted small">Tage</span>
                            </div>
                            <?php if ($avgDur !== null): ?>
                                <small class="text-muted">≈ <?= round($avgDur / 365, 1) ?> Jahre</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="stat-label">Ø Leerstand</div>
                            <div class="d-flex align-items-baseline gap-2">
                                <div class="stat-value"><?= $avgLeer !== null ? number_format($avgLeer, 1, ',', '.') : '—' ?></div>
                                <span class="text-muted small">%</span>
                            </div>
                            <?php if ($avgLeer !== null): ?>
                                <span class="badge bg-<?= $avgLeer < 5 ? 'success' : ($avgLeer < 10 ? 'warning' : 'danger') ?> mt-1">
                                    <?= $avgLeer < 5 ? 'Sehr gut' : ($avgLeer < 10 ? 'Akzeptabel' : 'Hoch') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="stat-label">Ø Qualität</div>
                            <div class="d-flex align-items-baseline gap-2">
                                <div class="stat-value"><?= $scoreAvg !== null ? number_format($scoreAvg, 0, ',', '.') : '—' ?></div>
                                <span class="text-muted small">%</span>
                            </div>
                            <?php if ($scoreAvg !== null): ?>
                                <div class="progress mt-2">
                                    <div class="progress-bar bg-<?= $scoreAvg >= 80 ? 'success' : ($scoreAvg >= 60 ? 'warning' : 'danger') ?>" 
                                         style="width: <?= $scoreAvg ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="stat-label">Datenschutz</div>
                            <div class="d-flex align-items-baseline gap-2">
                                <?php $consentRate = $consentsStats['total'] > 0 ? round($consentsStats['privacy'] / $consentsStats['total'] * 100) : 0; ?>
                                <div class="stat-value"><?= $consentRate ?></div>
                                <span class="text-muted small">%</span>
                            </div>
                            <small class="text-muted">
                                <?= $consentsStats['privacy'] ?> von <?= $consentsStats['total'] ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row mb-3">
                <!-- Saisonale Verteilung -->
                <div class="col-lg-8 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h5>Saisonale Verteilung</h5>
                        </div>
                        <div class="card-body">
                            <div id="seasonalChart"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Protokoll-Typen Donut -->
                <div class="col-lg-4 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h5>Verteilung</h5>
                        </div>
                        <div class="card-body">
                            <div id="protocolTypesChart"></div>
                            <div class="row mt-3 text-center small">
                                <div class="col-4">
                                    <div class="text-muted">Einzüge</div>
                                    <div class="fw-bold"><?= $countEinzug ?></div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted">Auszüge</div>
                                    <div class="fw-bold"><?= $countAuszug ?></div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted">Zwischen</div>
                                    <div class="fw-bold"><?= $countZwischen ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row mb-3">
                <!-- Qualitätsverlauf -->
                <div class="col-lg-6 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h5>Qualitätsverlauf</h5>
                        </div>
                        <div class="card-body">
                            <div id="qualityChart"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Wochentags-Heatmap -->
                <div class="col-lg-6 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h5>Wochentage</h5>
                        </div>
                        <div class="card-body">
                            <div id="weekdayChart"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zähler-Analyse -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5>Zähler-Analyse (Ø Verbrauch)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted small mb-2">Stromverbrauch</h6>
                            <div id="stromChart"></div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small mb-2">Wasserverbrauch</h6>
                            <div id="wasserChart"></div>
                        </div>
                    </div>
                    <?php if ($avgMeters['gas_we'] !== null || $avgMeters['gas_allg'] !== null): ?>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6 class="text-muted small mb-2">Gasverbrauch</h6>
                            <div id="gasChart"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabellen nebeneinander -->
            <div class="row mb-3">
                <!-- Fluktuation nach Haus -->
                <div class="col-lg-6 mb-3">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Fluktuation nach Haus</h5>
                            <span class="badge bg-primary"><?= count($flukQuote) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-container">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Objekt</th>
                                            <th class="text-center">WE</th>
                                            <th class="text-center">Ausz.</th>
                                            <th class="text-center">Quote</th>
                                            <th class="text-center">Ø Tage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach($flukQuote as $h): ?>
                                        <tr>
                                            <td>
                                                <div class="small fw-semibold"><?= htmlspecialchars((string)$h['street']) ?> <?= htmlspecialchars((string)$h['house_no']) ?></div>
                                                <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars((string)($h['postal']??'')) ?> <?= htmlspecialchars((string)$h['city']) ?></div>
                                            </td>
                                            <td class="text-center"><?= (int)$h['we'] ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $h['auszuege'] > 5 ? 'danger' : ($h['auszuege'] > 2 ? 'warning' : 'success') ?>">
                                                    <?= (int)$h['auszuege'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if($h['quote'] !== null): ?>
                                                    <span class="badge bg-<?= $h['quote'] > 50 ? 'danger' : ($h['quote'] > 30 ? 'warning' : 'success') ?>-subtle">
                                                        <?= htmlspecialchars((string)$h['quote']) ?>%
                                                    </span>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center text-muted" style="font-size: 0.7rem;">
                                                <?= $h['avg_dur_days'] !== null ? htmlspecialchars((string)$h['avg_dur_days']) : '—' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$flukQuote): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">Keine Daten</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fluktuation nach Wohnung -->
                <div class="col-lg-6 mb-3">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Fluktuation nach Wohnung</h5>
                            <span class="badge bg-primary"><?= count($flukByUnitList) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-container">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>WE</th>
                                            <th>Objekt</th>
                                            <th class="text-center">Auszüge</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach($flukByUnitList as $u): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars((string)$u['unit_label']) ?></span>
                                            </td>
                                            <td>
                                                <div class="small"><?= htmlspecialchars((string)$u['street']) ?> <?= htmlspecialchars((string)$u['house_no']) ?></div>
                                                <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars((string)($u['postal']??'')) ?> <?= htmlspecialchars((string)$u['city']) ?></div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $u['count'] > 3 ? 'danger' : ($u['count'] > 1 ? 'warning' : 'success') ?>">
                                                    <?= (int)$u['count'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$flukByUnitList): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">Keine Daten</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weitere Insights -->
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <div class="insight-item">
                                <div class="icon-box cyan">
                                    <i class="bi bi-camera"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Ø Fotos</div>
                                    <div class="fw-bold small">
                                        E: <?= $avgPhotosByType['einzug']['avg'] ?> | 
                                        A: <?= $avgPhotosByType['auszug']['avg'] ?> | 
                                        Z: <?= $avgPhotosByType['zwischen']['avg'] ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-2">
                            <div class="insight-item">
                                <div class="icon-box purple">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Aktivste Zeit</div>
                                    <div class="fw-bold small">
                                        <?php 
                                        $maxHour = array_keys($protokolleByHour, max($protokolleByHour))[0];
                                        echo $maxHour . ':00 - ' . ($maxHour + 1) . ':00 Uhr';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-2">
                            <div class="insight-item">
                                <div class="icon-box green">
                                    <i class="bi bi-calendar-week"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Aktivster Tag</div>
                                    <div class="fw-bold small">
                                        <?php 
                                        $weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
                                        $maxDay = array_keys($protokolleByWeekday, max($protokolleByWeekday))[0];
                                        echo $weekdays[$maxDay];
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- Ende container-fluid -->

        <!-- ApexCharts JS -->
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js"></script>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const commonOptions = {
                chart: {
                    fontFamily: 'system-ui, -apple-system, sans-serif',
                    toolbar: { show: false },
                    zoom: { enabled: false }
                },
                grid: {
                    borderColor: '#e5e7eb',
                    strokeDashArray: 0,
                    xaxis: { lines: { show: false } },
                    yaxis: { lines: { show: true } }
                },
                tooltip: {
                    theme: 'light',
                    style: { fontSize: '11px' }
                }
            };
            
            // 1. Saisonale Verteilung
            new ApexCharts(document.querySelector("#seasonalChart"), {
                ...commonOptions,
                series: [
                    { name: 'Einzüge', data: <?= json_encode($dataEin) ?> },
                    { name: 'Auszüge', data: <?= json_encode($dataAus) ?> },
                    { name: 'Zwischen', data: <?= json_encode($dataZwischen) ?> }
                ],
                chart: { ...commonOptions.chart, type: 'area', height: 250, stacked: true },
                colors: ['#10b981', '#ef4444', '#3b82f6'],
                xaxis: {
                    categories: <?= json_encode($labels) ?>,
                    labels: { style: { colors: '#6b7280', fontSize: '10px' } }
                },
                yaxis: { labels: { style: { colors: '#6b7280', fontSize: '10px' } } },
                fill: { opacity: 0.75, type: 'solid' },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 1 },
                legend: { position: 'top', fontSize: '11px' }
            }).render();
            
            // 2. Protokoll-Typen Donut
            new ApexCharts(document.querySelector("#protocolTypesChart"), {
                ...commonOptions,
                series: [<?= $countEinzug ?>, <?= $countAuszug ?>, <?= $countZwischen ?>],
                chart: { ...commonOptions.chart, type: 'donut', height: 200 },
                labels: ['Einzüge', 'Auszüge', 'Zwischen'],
                colors: ['#10b981', '#ef4444', '#3b82f6'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total',
                                    fontSize: '12px',
                                    fontWeight: 600
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                legend: { show: false }
            }).render();
            
            // 3. Qualitätsverlauf
            new ApexCharts(document.querySelector("#qualityChart"), {
                ...commonOptions,
                series: [{ name: 'Score', data: <?= json_encode($qualityData) ?> }],
                chart: { ...commonOptions.chart, type: 'line', height: 250 },
                colors: ['#8b5cf6'],
                xaxis: {
                    categories: <?= json_encode($labels) ?>,
                    labels: { style: { colors: '#6b7280', fontSize: '10px' } }
                },
                yaxis: {
                    min: 0, max: 100,
                    labels: {
                        formatter: function(val) { return val + '%'; },
                        style: { colors: '#6b7280', fontSize: '10px' }
                    }
                },
                stroke: { curve: 'smooth', width: 2 },
                markers: { size: 3 }
            }).render();
            
            // 4. Wochentags-Chart
            new ApexCharts(document.querySelector("#weekdayChart"), {
                ...commonOptions,
                series: [{ name: 'Protokolle', data: <?= json_encode(array_values($protokolleByWeekday)) ?> }],
                chart: { ...commonOptions.chart, type: 'bar', height: 250 },
                plotOptions: { bar: { borderRadius: 4, columnWidth: '60%', distributed: true } },
                dataLabels: { enabled: true, style: { fontSize: '10px', colors: ['#fff'] } },
                colors: ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#06b6d4'],
                xaxis: {
                    categories: ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
                    labels: { style: { colors: '#6b7280', fontSize: '10px' } }
                },
                yaxis: { labels: { style: { colors: '#6b7280', fontSize: '10px' } } },
                legend: { show: false }
            }).render();
            
            // 5. Zähler-Charts
            const stromData = [
                <?= $avgMeters['strom_we'] !== null ? $avgMeters['strom_we'] : 0 ?>,
                <?= $avgMeters['strom_allg'] !== null ? $avgMeters['strom_allg'] : 0 ?>
            ];
            
            if (stromData.some(v => v > 0)) {
                new ApexCharts(document.querySelector("#stromChart"), {
                    ...commonOptions,
                    series: [{ data: stromData }],
                    chart: { ...commonOptions.chart, type: 'bar', height: 150 },
                    plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '50%', distributed: true } },
                    colors: ['#fbbf24', '#f59e0b'],
                    xaxis: {
                        categories: ['Wohnung', 'Allgemein'],
                        labels: { style: { colors: '#6b7280', fontSize: '10px' } }
                    },
                    yaxis: { labels: { style: { colors: '#6b7280', fontSize: '10px' } } },
                    dataLabels: {
                        enabled: true,
                        formatter: function(val) { return val.toFixed(1) + ' kWh'; },
                        style: { fontSize: '10px' }
                    },
                    legend: { show: false }
                }).render();
            }
            
            const wasserData = [
                <?= $avgMeters['wasser_kueche_kalt'] !== null ? $avgMeters['wasser_kueche_kalt'] : 0 ?>,
                <?= $avgMeters['wasser_kueche_warm'] !== null ? $avgMeters['wasser_kueche_warm'] : 0 ?>,
                <?= $avgMeters['wasser_bad_kalt'] !== null ? $avgMeters['wasser_bad_kalt'] : 0 ?>,
                <?= $avgMeters['wasser_bad_warm'] !== null ? $avgMeters['wasser_bad_warm'] : 0 ?>,
                <?= $avgMeters['wasser_wm'] !== null ? $avgMeters['wasser_wm'] : 0 ?>
            ];
            
            if (wasserData.some(v => v > 0)) {
                new ApexCharts(document.querySelector("#wasserChart"), {
                    ...commonOptions,
                    series: [{ data: wasserData }],
                    chart: { ...commonOptions.chart, type: 'bar', height: 150 },
                    plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '50%', distributed: true } },
                    colors: ['#93c5fd', '#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8'],
                    xaxis: {
                        categories: ['Küche kalt', 'Küche warm', 'Bad kalt', 'Bad warm', 'WM'],
                        labels: { style: { colors: '#6b7280', fontSize: '10px' } }
                    },
                    yaxis: { labels: { style: { colors: '#6b7280', fontSize: '10px' } } },
                    dataLabels: {
                        enabled: true,
                        formatter: function(val) { return val.toFixed(1) + ' m³'; },
                        style: { fontSize: '10px' }
                    },
                    legend: { show: false }
                }).render();
            }
            
            <?php if ($avgMeters['gas_we'] !== null || $avgMeters['gas_allg'] !== null): ?>
            const gasData = [
                <?= $avgMeters['gas_we'] !== null ? $avgMeters['gas_we'] : 0 ?>,
                <?= $avgMeters['gas_allg'] !== null ? $avgMeters['gas_allg'] : 0 ?>
            ];
            
            if (gasData.some(v => v > 0)) {
                new ApexCharts(document.querySelector("#gasChart"), {
                    ...commonOptions,
                    series: [{ data: gasData }],
                    chart: { ...commonOptions.chart, type: 'bar', height: 150 },
                    plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '50%', distributed: true } },
                    colors: ['#fca5a5', '#ef4444'],
                    xaxis: {
                        categories: ['Wohnung', 'Allgemein'],
                        labels: { style: { colors: '#6b7280', fontSize: '10px' } }
                    },
                    yaxis: { labels: { style: { colors: '#6b7280', fontSize: '10px' } } },
                    dataLabels: {
                        enabled: true,
                        formatter: function(val) { return val.toFixed(1) + ' m³'; },
                        style: { fontSize: '10px' }
                    },
                    legend: { show: false }
                }).render();
            }
            <?php endif; ?>
        });
        </script>
        <?php
        View::render('Statistik', ob_get_clean());
    }
}
