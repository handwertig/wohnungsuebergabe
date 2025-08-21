<?php
declare(strict_types=1);

namespace App;

use Dompdf\Dompdf;
use PDO;

final class PdfService
{
    public static function renderAndSave(string $protocolId, int $versionNo): string
    {
        $pdo = Database::pdo();

        // Protokoll + Version + Payload laden
        $st = $pdo->prepare("SELECT p.id, p.type, p.tenant_name, pv.version_no, pv.data, u.label AS unit_label, o.city,o.street,o.house_no
                             FROM protocol_versions pv
                             JOIN protocols p ON p.id=pv.protocol_id
                             JOIN units u ON u.id=p.unit_id
                             JOIN objects o ON o.id=u.object_id
                             WHERE pv.protocol_id=? AND pv.version_no=? LIMIT 1");
        $st->execute([$protocolId, $versionNo]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new \RuntimeException('Protokoll-Version nicht gefunden.');

        $payload = json_decode((string)$row['data'], true) ?: [];
        $rooms   = $payload['rooms'] ?? [];
        $meters  = $payload['meters'] ?? [];
        $keys    = $payload['keys'] ?? [];
        $meta    = $payload['meta'] ?? [];

        // Rechtstexte (aktuelle Versionen). Optional: später Snapshot-IDs aus payload lesen.
        $lt = self::latestLegalTexts();

        // HTML aufbauen (einfaches CI, später Template/Theme)
        $title = sprintf('%s, %s %s – %s (v%d)',
            $row['city'], $row['street'], $row['house_no'], $row['unit_label'], (int)$row['version_no']);

        ob_start(); ?>
        <!doctype html>
        <html lang="de">
        <head>
          <meta charset="utf-8">
          <style>
            body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 12px; color: #111; }
            h1,h2,h3 { margin: 0 0 6px; }
            .muted { color: #666; }
            .section { margin: 14px 0; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 6px; }
            th { background: #f4f4f4; text-align: left; }
            .small { font-size: 11px; }
          </style>
        </head>
        <body>
          <h1>Übergabeprotokoll</h1>
          <div class="muted"><?= htmlspecialchars($title) ?></div>

          <div class="section">
            <h2>Protokoll</h2>
            <table>
              <tr><th>Art</th><td><?= htmlspecialchars(self::labelType((string)$row['type'])) ?></td></tr>
              <tr><th>Mieter</th><td><?= htmlspecialchars((string)$row['tenant_name']) ?></td></tr>
              <tr><th>Zeitstempel</th><td><?= htmlspecialchars((string)($payload['timestamp'] ?? '')) ?></td></tr>
            </table>
          </div>

          <div class="section">
            <h2>Adresse</h2>
            <table>
              <tr><th>Ort</th><td><?= htmlspecialchars((string)($payload['address']['city'] ?? '')) ?></td></tr>
              <tr><th>Straße</th><td><?= htmlspecialchars((string)($payload['address']['street'] ?? '')) ?> <?= htmlspecialchars((string)($payload['address']['house_no'] ?? '')) ?></td></tr>
              <tr><th>PLZ</th><td><?= htmlspecialchars((string)($payload['address']['postal_code'] ?? '')) ?></td></tr>
              <tr><th>Einheit</th><td><?= htmlspecialchars((string)($payload['address']['unit_label'] ?? (string)$row['unit_label'])) ?></td></tr>
            </table>
          </div>

          <?php if (!empty($rooms)): ?>
          <div class="section">
            <h2>Räume</h2>
            <table>
              <thead><tr><th>Raum</th><th>IST‑Zustand</th><th>Geruch</th><th>WMZ Nr.</th><th>WMZ Stand</th><th>Abnahme</th></tr></thead>
              <tbody>
              <?php foreach ($rooms as $r): ?>
                <tr>
                  <td><?= htmlspecialchars((string)($r['name'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($r['state'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($r['smell'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($r['wmz_no'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($r['wmz_val'] ?? '')) ?></td>
                  <td><?= !empty($r['accepted']) ? 'ja' : 'nein' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <div class="small muted">Hinweis: Raumfotos werden separat gespeichert und können auf Wunsch dem PDF angehängt werden.</div>
          </div>
          <?php endif; ?>

          <div class="section">
            <h2>Zählerstände</h2>
            <table>
              <tbody>
                <?php foreach (self::meterLabels() as $k=>$label): $m=$meters[$k] ?? ['no'=>'','val'=>'']; ?>
                <tr><th><?= $label ?> (Nr.)</th><td><?= htmlspecialchars((string)$m['no']) ?></td></tr>
                <tr><th><?= $label ?> (Stand)</th><td><?= htmlspecialchars((string)$m['val']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="section">
            <h2>Schlüssel</h2>
            <table>
              <thead><tr><th>Bezeichnung</th><th>Anzahl</th><th>Nr.</th></tr></thead>
              <tbody>
              <?php foreach (($keys ?? []) as $k): ?>
                <tr><td><?= htmlspecialchars((string)($k['label'] ?? '')) ?></td><td><?= (int)($k['qty'] ?? 0) ?></td><td><?= htmlspecialchars((string)($k['no'] ?? '')) ?></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="section">
            <h2>Weitere Angaben</h2>
            <table>
              <tr><th>Bank</th><td><?= htmlspecialchars((string)($meta['bank']['bank'] ?? '')) ?></td></tr>
              <tr><th>IBAN</th><td><?= htmlspecialchars((string)($meta['bank']['iban'] ?? '')) ?></td></tr>
              <tr><th>Kontoinhaber</th><td><?= htmlspecialchars((string)($meta['bank']['holder'] ?? '')) ?></td></tr>
              <tr><th>Kontakt Mieter</th><td><?= htmlspecialchars((string)($meta['tenant_contact']['email'] ?? '')) ?> | <?= htmlspecialchars((string)($meta['tenant_contact']['phone'] ?? '')) ?></td></tr>
              <tr><th>Neue Meldeadresse</th><td><?= htmlspecialchars((string)($meta['tenant_new_addr']['street'] ?? '')) ?> <?= htmlspecialchars((string)($meta['tenant_new_addr']['house_no'] ?? '')) ?>, <?= htmlspecialchars((string)($meta['tenant_new_addr']['postal_code'] ?? '')) ?> <?= htmlspecialchars((string)($meta['tenant_new_addr']['city'] ?? '')) ?></td></tr>
              <tr><th>Versand</th><td><?= !empty($meta['owner_send'])?'Eigentümer ':' ' ?><?= !empty($meta['manager_send'])?'Hausverwaltung':'' ?></td></tr>
              <tr><th>Dritte Person</th><td><?= htmlspecialchars((string)($meta['third_attendee'] ?? '')) ?></td></tr>
              <tr><th>Bemerkungen</th><td><?= nl2br(htmlspecialchars((string)($meta['notes'] ?? ''))) ?></td></tr>
            </table>
          </div>

          <div class="section">
            <h2>Rechtstexte</h2>
            <?php foreach ($lt as $name=>$t): ?>
              <h3 class="small"><?= htmlspecialchars($t['title']) ?> (v<?= (int)$t['version'] ?>)</h3>
              <div class="small"><?= $t['content'] ?></div>
              <hr>
            <?php endforeach; ?>
          </div>

          <div class="section">
            <h2>Unterschriften</h2>
            <table>
              <tr><th>Mieter</th><td>______________________________</td></tr>
              <tr><th>Eigentümer</th><td>______________________________</td></tr>
              <?php if (!empty($meta['third_attendee'])): ?>
              <tr><th><?= htmlspecialchars((string)$meta['third_attendee']) ?></th><td>______________________________</td></tr>
              <?php endif; ?>
            </table>
          </div>

          <div class="muted small">Erzeugt am <?= date('Y-m-d H:i') ?> • Protokoll-ID <?= htmlspecialchars((string)$row['id']) ?> • Version v<?= (int)$row['version_no'] ?></div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        // Dompdf
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // speichern
        $base = realpath(__DIR__.'/../storage/pdfs') ?: (__DIR__.'/../storage/pdfs');
        if (!is_dir($base)) @mkdir($base, 0775, true);
        $dir = $base . '/' . $protocolId;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = $dir . '/v'.$versionNo.'.pdf';
        file_put_contents($path, $dompdf->output());

        // pdf_path auf Version schreiben
        $upd = $pdo->prepare("UPDATE protocol_versions SET pdf_path=? WHERE protocol_id=? AND version_no=?");
        $upd->execute([$path, $protocolId, $versionNo]);

        return $path;
    }

    private static function latestLegalTexts(): array
    {
        $pdo = Database::pdo();
        $out = [];
        foreach (['datenschutz','entsorgung','marketing'] as $name) {
            $st = $pdo->prepare("SELECT name, version, title, content FROM legal_texts WHERE name=? ORDER BY version DESC LIMIT 1");
            $st->execute([$name]);
            $out[$name] = $st->fetch(PDO::FETCH_ASSOC) ?: ['name'=>$name,'version'=>0,'title'=>strtoupper($name),'content'=>''];
        }
        return $out;
    }

    private static function meterLabels(): array
    {
        return [
            'strom_we'=>'Strom (Wohneinheit)',
            'strom_allg'=>'Strom (Haus allgemein)',
            'gas_we'=>'Gas (Wohneinheit)',
            'gas_allg'=>'Gas (Haus allgemein)',
            'wasser_kueche_kalt'=>'Kaltwasser Küche (blau)',
            'wasser_kueche_warm'=>'Warmwasser Küche (rot)',
            'wasser_bad_kalt'=>'Kaltwasser Bad (blau)',
            'wasser_bad_warm'=>'Warmwasser Bad (rot)',
            'wasser_wm'=>'Wasserzähler Waschmaschine (blau)',
        ];
    }

    private static function labelType(string $t): string
    {
        return $t==='einzug' ? 'Einzugsprotokoll' : ($t==='auszug' ? 'Auszugsprotokoll' : 'Zwischenprotokoll');
    }
}
