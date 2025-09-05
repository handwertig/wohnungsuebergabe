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
        $byUnit      = [];
        $countEinzug = 0; $countAuszug = 0; $countZwischen = 0;

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

            if ($r['type'] === 'auszug') {
                $countAuszug++;
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
            } elseif ($r['type'] === 'einzug') {
                $countEinzug++;
            } elseif ($r['type'] === 'zwischen') {
                $countZwischen++;
            }

            $ct = (int)strtotime((string)$r['created_at']);
            if ((int)date('Y', $ct) === $year) {
                $m = (int)date('n', $ct);
                if ($r['type'] === 'einzug') $monthsEin[$m]++;
                if ($r['type'] === 'auszug') $monthsAus[$m]++;
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

        // Saison-Datensätze
        $labels = []; $dataEin = []; $dataAus = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[]  = date('M', mktime(0, 0, 0, $m, 1, $year));
            $dataEin[] = $monthsEin[$m];
            $dataAus[] = $monthsAus[$m];
        }

        // ---- Render ----
        ob_start(); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h1 class="h4 mb-0">Statistik</h1>
          <form method="get" class="d-flex align-items-center gap-2">
            <label class="form-label mb-0">Jahr</label>
            <input class="form-control" style="width:110px" name="year" type="number" value="<?= (int)$year ?>">
            <button class="btn btn-primary">Anzeigen</button>
          </form>
        </div>

        <!-- Saisonale Spitzen -->
        <div class="card mb-3"><div class="card-body">
          <h2 class="h6 mb-2">Saisonale Spitzen (<?= (int)$year ?>)</h2>
          <canvas id="seasonChart" height="120"></canvas>
        </div></div>

        <!-- KPI-Karten -->
        <div class="row g-3 mb-3">
          <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small mb-1">Einzugsprotokolle</div>
            <div class="display-6"><?= (int)$countEinzug ?></div>
          </div></div></div>
          <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small mb-1">Auszugsprotokolle</div>
            <div class="display-6"><?= (int)$countAuszug ?></div>
          </div></div></div>
          <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small mb-1">Zwischenprotokolle</div>
            <div class="display-6"><?= (int)$countZwischen ?></div>
          </div></div></div>
          <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-muted small">Ø Mietdauer <?= $avgDur!==null? $avgDur.' Tage':'—' ?></div>
            <div class="text-muted small">Ø Leerstandsquote: <?= $avgLeer!==null? $avgLeer.'%':'—' ?></div>
            <div class="text-muted small">Ø Qualitätsscore: <?= $scoreAvg!==null? $scoreAvg.' %':'—' ?></div>
          </div></div></div>
        </div>

        <!-- Fluktuation Haus & Wohnung nebeneinander -->
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="card"><div class="card-body">
              <h2 class="h6 mb-2">Fluktuation nach Haus (Auszüge) & Quote</h2>
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead><tr>
                    <th>PLZ Ort</th><th>Straße/Hausnr.</th>
                    <th class="text-end">WE</th><th class="text-end">Auszüge</th><th class="text-end">Quote %</th>
                    <th class="text-end">Ø Mietdauer (Tage)</th><th class="text-end">Ø Leerstand (Tage)</th>
                  </tr></thead>
                  <tbody>
                  <?php foreach($flukQuote as $h): ?>
                    <tr>
                      <td><?= htmlspecialchars((string)($h['postal']??'')) ?> <?= htmlspecialchars((string)$h['city']) ?></td>
                      <td><?= htmlspecialchars((string)$h['street']) ?> <?= htmlspecialchars((string)$h['house_no']) ?></td>
                      <td class="text-end"><?= (int)$h['we'] ?></td>
                      <td class="text-end"><?= (int)$h['auszuege'] ?></td>
                      <td class="text-end"><?= $h['quote']!==null? htmlspecialchars((string)$h['quote']) : '—' ?></td>
                      <td class="text-end"><?= $h['avg_dur_days']!==null? htmlspecialchars((string)$h['avg_dur_days']) : '—' ?></td>
                      <td class="text-end"><?= $h['avg_leer_days']!==null? htmlspecialchars((string)$h['avg_leer_days']) : '—' ?></td>
                    </tr>
                  <?php endforeach; if (!$flukQuote) echo '<tr><td colspan="7" class="text-muted">Keine Daten.</td></tr>'; ?>
                  </tbody>
                </table>
              </div>
            </div></div>
          </div>

          <div class="col-lg-6">
            <div class="card"><div class="card-body">
              <h2 class="h6 mb-2">Fluktuation nach Wohnung (Auszüge)</h2>
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead><tr><th>PLZ Ort</th><th>Straße/Hausnr.</th><th>WE</th><th class="text-end">Auszüge</th></tr></thead>
                  <tbody>
                  <?php foreach($flukByUnitList as $u): ?>
                    <tr>
                      <td><?= htmlspecialchars((string)($u['postal']??'')) ?> <?= htmlspecialchars((string)$u['city']) ?></td>
                      <td><?= htmlspecialchars((string)$u['street']) ?> <?= htmlspecialchars((string)$u['house_no']) ?></td>
                      <td><?= htmlspecialchars((string)$u['unit_label']) ?></td>
                      <td class="text-end"><?= (int)$u['count'] ?></td>
                    </tr>
                  <?php endforeach; if (!$flukByUnitList) echo '<tr><td colspan="4" class="text-muted">Keine Daten.</td></tr>'; ?>
                  </tbody>
                </table>
              </div>
            </div></div>
          </div>
        </div>

        <!-- Zähler-Analyse ganz unten -->
        <div class="card mt-3"><div class="card-body">
          <h2 class="h6 mb-2">Zähler‑Analyse (Δ Auszug − Einzug, Mittelwerte)</h2>
          <div class="row">
            <?php foreach($meterKeys as $k=>$lbl): ?>
              <div class="col-md-6 col-lg-4">
                <div class="text-muted small mb-1"><?= htmlspecialchars($lbl) ?></div>
                <div class="display-6"><?= $avgMeters[$k]!==null? htmlspecialchars((string)$avgMeters[$k]) : '—' ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="small text-muted mt-2">Nur Paarungen Einzug→Auszug mit beidseitigen Ständen; Δ <em>Auszug − Einzug</em>.</div>
        </div></div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        (function(){
          const ctx = document.getElementById('seasonChart');
          if (!ctx) return;
          new Chart(ctx, {
            type: 'bar',
            data: {
              labels: <?= json_encode($labels ?? []) ?>,
              datasets: [
                { label: 'Einzüge', data: <?= json_encode($dataEin ?? []) ?> },
                { label: 'Auszüge', data: <?= json_encode($dataAus ?? []) ?> }
              ]
            },
            options: {
              responsive: true,
              plugins: { legend: { position: 'bottom' } },
              scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
          });
        })();
        </script>
        <?php
        View::render('Statistik', ob_get_clean());
    }
}
