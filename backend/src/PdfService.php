<?php
declare(strict_types=1);

namespace App;

use Dompdf\Dompdf;
use PDO;

final class PdfService
{
    /**
     * Rendert Protokoll-Version als PDF, speichert sie, trägt Pfad in protocol_versions.pdf_path ein,
     * und gibt den absoluten Pfad zurück.
     *
     * @param string $protocolId
     * @param int    $versionNo   1..N
     * @param bool   $withPhotos  true = Foto-Anhang pro Raum hinzufügen
     */
    public static function renderAndSave(string $protocolId, int $versionNo, bool $withPhotos = true): string
    {
        $pdo = Database::pdo();

        // Protokoll + Version + Payload
        $st = $pdo->prepare("SELECT p.id, p.type, p.tenant_name, pv.version_no, pv.data, pv.created_at AS v_created_at,
                                    u.label AS unit_label, o.city,o.street,o.house_no
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
        $addr    = $payload['address'] ?? [];
        $typLabel = self::labelType((string)$row['type']);

        // Fotos (wenn gewünscht)
        $photos = [];
        if ($withPhotos) {
            $pf = $pdo->prepare("SELECT room_key, original_name, COALESCE(thumb_path, path) AS img FROM protocol_files WHERE protocol_id=? AND section='room_photo' ORDER BY created_at ASC");
            $pf->execute([$protocolId]); $photos = $pf->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // HTML & CSS
        $title = sprintf('%s, %s %s – %s (v%d)', $row['city'], $row['street'], $row['house_no'], $row['unit_label'], (int)$row['version_no']);

        ob_start(); ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 20mm 15mm 18mm 15mm; }
  body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color:#111; font-size: 12px; }
  h1,h2,h3 { margin: 0 0 8px; }
  h1 { font-size: 22px; letter-spacing:.2px; }
  h2 { font-size: 14px; margin-top:14px; }
  .muted { color:#666; }
  .mb-2 { margin-bottom:8px; } .mb-3 { margin-bottom:12px; } .mb-4 { margin-bottom:16px; }
  .mt-2 { margin-top:8px; } .mt-3 { margin-top:12px; } .mt-4 { margin-top:16px; }
  .section { margin: 14px 0; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #CCC; padding: 6px; vertical-align: top; }
  th { background: #F2F2F2; text-align:left; }
  .small { font-size: 11px; }
  .chip { display:inline-block; padding:4px 8px; border:1px solid #222357; color:#222357; font-weight:600; }
  .grid { display:flex; flex-wrap:wrap; gap:6px; }
  .photo { width: 31%; border:1px solid #DDD; height: 130px; background:#fafafa; display:flex; align-items:center; justify-content:center; overflow:hidden; }
  .photo img { width:100%; height:100%; object-fit:cover; }
  .divider { height:1px; background:#DDD; margin:12px 0; }
</style>
</head>
<body>

<!-- Deckblatt -->
<table style="border:1px solid #222357; border-collapse:collapse; margin-bottom:10px">
  <tr>
    <td style="width:60px; border-right:1px solid #222357; background:#222357;">
      <!-- minimalistisches Logo -->
      <div style="width:60px;height:60px; display:flex; align-items:center; justify-content:center;">
        <div style="width:28px;height:28px;background:#e22278"></div>
      </div>
    </td>
    <td style="padding:10px">
      <h1>Übergabeprotokoll</h1>
      <div class="muted"><?= htmlspecialchars($title) ?></div>
      <div class="mt-2">
        <span class="chip"><?= htmlspecialchars($typLabel) ?></span>
        <span class="chip">Version v<?= (int)$row['version_no'] ?></span>
      </div>
    </td>
  </tr>
</table>

<div class="section">
  <h2>Zusammenfassung</h2>
  <table>
    <tr><th>Objekt / WE</th><td><?= htmlspecialchars($row['city'].', '.$row['street'].' '.$row['house_no'].' – '.$row['unit_label']) ?></td></tr>
    <tr><th>Mieter</th><td><?= htmlspecialchars((string)$row['tenant_name']) ?></td></tr>
    <tr><th>Zeitstempel</th><td><?= htmlspecialchars((string)($payload['timestamp'] ?? '')) ?></td></tr>
    <tr><th>Erstellt</th><td><?= htmlspecialchars((string)$row['v_created_at']) ?></td></tr>
  </table>
</div>

<div class="section">
  <h2>Adresse</h2>
  <table>
    <tr><th>Ort</th><td><?= htmlspecialchars((string)($addr['city'] ?? '')) ?></td></tr>
    <tr><th>Straße</th><td><?= htmlspecialchars((string)($addr['street'] ?? '')) ?> <?= htmlspecialchars((string)($addr['house_no'] ?? '')) ?></td></tr>
    <tr><th>PLZ</th><td><?= htmlspecialchars((string)($addr['postal_code'] ?? '')) ?></td></tr>
    <tr><th>Einheit</th><td><?= htmlspecialchars((string)($addr['unit_label'] ?? (string)$row['unit_label'])) ?></td></tr>
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
        <td><?= nl2br(htmlspecialchars((string)($r['state'] ?? ''))) ?></td>
        <td><?= htmlspecialchars((string)($r['smell'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)($r['wmz_no'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)($r['wmz_val'] ?? '')) ?></td>
        <td><?= !empty($r['accepted']) ? 'ja' : 'nein' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
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
    <tr><th>Kontakt Mieter</th><td><?= htmlspecialchars((string)($meta['tenant_contact']['email'] ?? '')) ?><?php $p=(string)($meta['tenant_contact']['phone'] ?? ''); if($p!=='') echo ' | '.htmlspecialchars($p); ?></td></tr>
    <tr><th>Neue Meldeadresse</th><td><?= htmlspecialchars((string)($meta['tenant_new_addr']['street'] ?? '')) ?> <?= htmlspecialchars((string)($meta['tenant_new_addr']['house_no'] ?? '')) ?>, <?= htmlspecialchars((string)($meta['tenant_new_addr']['postal_code'] ?? '')) ?> <?= htmlspecialchars((string)($meta['tenant_new_addr']['city'] ?? '')) ?></td></tr>
    <tr><th>Einwilligungen</th><td>
      Datenschutz: <?= !empty(($meta['consents']['privacy'] ?? false)) ? '✓' : '×' ?> ·
      Marketing: <?= !empty(($meta['consents']['marketing'] ?? false)) ? '✓' : '×' ?> ·
      Entsorgung: <?= !empty(($meta['consents']['disposal'] ?? false)) ? '✓' : '×' ?>
    </td></tr>
    <tr><th>Dritte Person</th><td><?= htmlspecialchars((string)($meta['third_attendee'] ?? '')) ?></td></tr>
    <tr><th>Bemerkungen</th><td><?= nl2br(htmlspecialchars((string)($meta['notes'] ?? ''))) ?></td></tr>
  </table>
</div>

<?php if ($withPhotos && !empty($photos)): ?>
<div class="section">
  <h2>Foto‑Anhang</h2>
  <div class="grid">
    <?php foreach ($photos as $p): $url = self::publicUrl((string)$p['img']); ?>
      <?php if ($url!==''): ?>
      <div class="photo"><img src="<?= htmlspecialchars($url) ?>" alt=""></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Fußzeile mit Seitennummern -->
<script type="text/php">
if (isset($pdf)) {
  $font = $fontMetrics->get_font("DejaVu Sans", "normal");
  $pdf->page_text(520, 810, "Seite {PAGE_NUM} / {PAGE_COUNT}", $font, 9, array(0,0,0));
  $pdf->page_text(40, 810, date("Y-m-d H:i"), $font, 9, array(0,0,0));
}
</script>
</body>
</html>
<?php
        $html = ob_get_clean();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Ablage
        $base = realpath(__DIR__.'/../storage/pdfs') ?: (__DIR__.'/../storage/pdfs');
        if (!is_dir($base)) @mkdir($base, 0775, true);
        $dir = $base . '/' . $protocolId;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = $dir . '/v'.$versionNo.'.pdf';
        file_put_contents($path, $dompdf->output());

        // In DB eintragen
        $upd = $pdo->prepare("UPDATE protocol_versions SET pdf_path=? WHERE protocol_id=? AND version_no=?");
        $upd->execute([$path, $protocolId, $versionNo]);

        return $path;
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

    private static function publicUrl(string $absPath): string
    {
        $base = realpath(__DIR__.'/../storage/uploads');
        $real = realpath($absPath) ?: $absPath;
        if ($base && $real && str_starts_with($real, $base)) {
            return '/uploads/'.ltrim(substr($real, strlen($base)),'/');
        }
        return '';
    }
}
