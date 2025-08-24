<?php
declare(strict_types=1);

namespace App;

use Dompdf\Dompdf;
use PDO;

final class PdfService
{
    public static function renderAndSave(string $protocolId, int $versionNo, bool $withPhotos=true): string
    {
        $pdo = Database::pdo();
        $st=$pdo->prepare("SELECT p.id,p.type,p.tenant_name,p.payload,pv.version_no,pv.created_at AS v_created_at,u.label AS unit_label,o.city,o.street,o.house_no FROM protocol_versions pv JOIN protocols p ON p.id=pv.protocol_id JOIN units u ON u.id=p.unit_id JOIN objects o ON o.id=u.object_id WHERE pv.protocol_id=? AND pv.version_no=? LIMIT 1");
        $st->execute([$protocolId,$versionNo]); $row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new \RuntimeException('Protokoll-Version nicht gefunden.');

        $payload=json_decode((string)$row['payload'],true) ?: [];
        $addr=(array)($payload['address']??[]); $rooms=(array)($payload['rooms']??[]); $meters=(array)($payload['meters']??[]);
        $keys=(array)($payload['keys']??[]); $meta=(array)($payload['meta']??[]); $cons=(array)($meta['consents']??[]); $ts=(string)($payload['timestamp']??'');

        $getLatest=function($name)use($pdo){ $s=$pdo->prepare("SELECT title,content,version FROM legal_texts WHERE name=? ORDER BY version DESC LIMIT 1"); $s->execute([$name]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r?:['title'=>'','content'=>'','version'=>0]; };
        $rtD=$getLatest('datenschutz'); $rtE=$getLatest('entsorgung'); $rtM=$getLatest('marketing'); $rtK=$getLatest('kaution_hinweis');

        // Logo (Settings oder Fallback)
        $logoTag=''; $logoPath=(string)Settings::get('pdf_logo_path',''); $fb=__DIR__.'/../public/images/logo.svg';
        if($logoPath && is_file($logoPath)){ $ext=strtolower((string)pathinfo($logoPath,PATHINFO_EXTENSION)); $mime=($ext==='svg')?'image/svg+xml':(($ext==='png')?'image/png':(($ext==='jpg'||$ext==='jpeg')?'image/jpeg':'application/octet-stream')); $data=base64_encode((string)file_get_contents($logoPath)); $logoTag='<img src="data:'.$mime.';base64,'.$data.'" style="height:28px;width:auto">'; }
        elseif(is_file($fb)){ $data=base64_encode((string)file_get_contents($fb)); $logoTag='<img src="data:image/svg+xml;base64,'.$data.'" style="height:28px;width:auto">'; }

        $type=(string)$row['type'];
        $typeLabel = ($type==='einzug') ? 'Einzugsprotokoll' : (($type==='auszug') ? 'Auszugsprotokoll' : 'Zwischenabnahme');
        $titleLine=$row['city'].', '.$row['street'].' '.$row['house_no'].' – '.$row['unit_label']; $created=(string)$row['v_created_at']; $protId=(string)$row['id'];

        $meterLabels=['strom_we'=>'Strom (Wohneinheit)','strom_allg'=>'Strom (Haus allgemein)','gas_we'=>'Gas (Wohneinheit)','gas_allg'=>'Gas (Haus allgemein)','wasser_kueche_kalt'=>'Wasser Küche (kalt)','wasser_kueche_warm'=>'Wasser Küche (warm)','wasser_bad_kalt'=>'Wasser Bad (kalt)','wasser_bad_warm'=>'Wasser Bad (warm)','wasser_wm'=>'Wasser Waschmaschine (blau)'];

        ob_start(); ?>
<!doctype html><html lang="de"><head><meta charset="utf-8">
<style>
  @page { margin: 20mm 15mm 18mm 15mm; }
  body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color:#111; font-size: 12px; }
  h1 { font-size: 20px; margin: 0 0 4px; } h2 { font-size: 14px; margin: 14px 0 6px; }
  .muted { color:#555; } .subline { font-style: italic; margin-bottom: 10px; }
  table { width:100%; border-collapse: collapse; } th,td{ border:1px solid #CCC; padding:6px; vertical-align:top } th{ background:#F2F2F2; text-align:left }
  .grid{ display:flex; flex-wrap:wrap; gap:6px } .photo{ width:31%; height:120px; border:1px solid #DDD; background:#fafafa; overflow:hidden; display:flex; align-items:center; justify-content:center }
  .photo img{ width:100%; height:100%; object-fit:cover }
  .small{ font-size:11px } .mt-2{ margin-top:10px } .mb-2{ margin-bottom:10px }
</style></head><body>

<table style="width:100%; border:none; margin-bottom:10px"><tr>
  <td style="width:120px; border:none; vertical-align:middle"><?= $logoTag ?></td>
  <td style="border:none; vertical-align:middle"><h1><?= htmlspecialchars($typeLabel) ?></h1><div class="subline">Ohne Anerkennung einer rechtlichen Präjudiz.</div><div class="muted"><?= htmlspecialchars($titleLine) ?></div></td>
</tr></table>

<h2>Zusammenfassung</h2>
<table>
  <tr><th>Objekt / WE</th><td><?= htmlspecialchars($titleLine) ?></td></tr>
  <tr><th>Mieter</th><td><?= htmlspecialchars((string)$row['tenant_name']) ?></td></tr>
  <tr><th>Zeitstempel</th><td><?= htmlspecialchars($ts) ?></td></tr>
  <tr><th>Version erstellt</th><td><?= htmlspecialchars($created) ?></td></tr>
</table>

<h2>Adresse</h2>
<table>
  <tr><th>Ort</th><td><?= htmlspecialchars((string)($addr['city'] ?? '')) ?></td></tr>
  <tr><th>Straße</th><td><?= htmlspecialchars((string)($addr['street'] ?? '')) ?> <?= htmlspecialchars((string)($addr['house_no'] ?? '')) ?></td></tr>
  <tr><th>PLZ</th><td><?= htmlspecialchars((string)($addr['postal_code'] ?? '')) ?></td></tr>
  <tr><th>Einheit</th><td><?= htmlspecialchars((string)($addr['unit_label'] ?? (string)$row['unit_label'])) ?></td></tr>
  <tr><th>Etage</th><td><?= htmlspecialchars((string)($addr['floor'] ?? '')) ?></td></tr>
</table>

<?php if (!empty($rooms)): ?>
<h2>Räume</h2>
<table><thead><tr><th>Raum</th><th>IST‑Zustand</th><th>Geruch</th><th>WMZ‑Nr.</th><th>WMZ‑Stand</th><th>Abnahme</th></tr></thead><tbody>
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
</tbody></table>
<?php endif; ?>

<h2>Zählerstände</h2>
<table><tbody>
<?php foreach ($meterLabels as $k=>$lbl): $m=$meters[$k] ?? ['no'=>'','val'=>'']; ?>
<tr><th><?= htmlspecialchars($lbl) ?> – Nummer</th><td><?= htmlspecialchars((string)$m['no']) ?></td></tr>
<tr><th><?= htmlspecialchars($lbl) ?> – Stand</th><td><?= htmlspecialchars((string)$m['val']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>

<h2>Schlüssel</h2>
<table><thead><tr><th>Bezeichnung</th><th>Anzahl</th><th>Nr.</th></tr></thead><tbody>
<?php foreach ($keys as $k): ?>
<tr><td><?= htmlspecialchars((string)($k['label'] ?? '')) ?></td><td><?= (int)($k['qty'] ?? 0) ?></td><td><?= htmlspecialchars((string)($k['no'] ?? '')) ?></td></tr>
<?php endforeach; if (!$keys) echo '<tr><td colspan="3" class="muted">—</td></tr>'; ?>
</tbody></table>

<h2>Weitere Angaben</h2>
<div class="small" style="margin-bottom:6px"><em>Die Angabe der Bankverbindung dient zur Rückzahlung einer Mietkaution. Siehe hierzu die Angaben im Mietvertrag und Hinweise im Protokoll.</em></div>
<?php $bank=(array)($meta['bank']??[]); $tc=(array)($meta['tenant_contact']??[]); $na=(array)($meta['tenant_new_addr']??[]); ?>
<table>
  <tr><th>Bank</th><td><?= htmlspecialchars((string)($bank['bank'] ?? '')) ?></td></tr>
  <tr><th>IBAN</th><td><?= htmlspecialchars((string)($bank['iban'] ?? '')) ?></td></tr>
  <tr><th>Kontoinhaber</th><td><?= htmlspecialchars((string)($bank['holder'] ?? '')) ?></td></tr>
  <tr><th>Mieter – E‑Mail</th><td><?= htmlspecialchars((string)($tc['email'] ?? '')) ?></td></tr>
  <tr><th>Mieter – Telefon</th><td><?= htmlspecialchars((string)($tc['phone'] ?? '')) ?></td></tr>
  <tr><th>Neue Meldeadresse</th><td><?= htmlspecialchars((string)($na['street'] ?? '')) ?> <?= htmlspecialchars((string)($na['house_no'] ?? '')) ?>, <?= htmlspecialchars((string)($na['postal_code'] ?? '')) ?> <?= htmlspecialchars((string)($na['city'] ?? '')) ?></td></tr>
</table>

<h2>Rechtstexte</h2>
<table><thead><tr><th>Titel</th><th>Version</th><th>Inhalt</th><th>Zustimmung</th></tr></thead><tbody>
<tr><td><?= htmlspecialchars((string)$rtD['title']) ?></td><td><?= (int)($rtD['version'] ?? 0) ?></td><td><?= $rtD['content'] ?></td><td><?= !empty($cons['privacy']) ? 'Mieter hat zugestimmt' : 'Mieter hat nicht zugestimmt' ?></td></tr>
<tr><td><?= htmlspecialchars((string)$rtE['title']) ?></td><td><?= (int)($rtE['version'] ?? 0) ?></td><td><?= $rtE['content'] ?></td><td><?= !empty($cons['disposal']) ? 'Mieter hat zugestimmt' : 'Mieter hat nicht zugestimmt' ?></td></tr>
<tr><td><?= htmlspecialchars((string)$rtM['title']) ?></td><td><?= (int)($rtM['version'] ?? 0) ?></td><td><?= $rtM['content'] ?></td><td><?= !empty($cons['marketing']) ? 'Mieter hat zugestimmt' : 'Mieter hat nicht zugestimmt' ?></td></tr>
<tr><td><?= htmlspecialchars((string)$rtK['title']) ?></td><td><?= (int)($rtK['version'] ?? 0) ?></td><td><?= $rtK['content'] ?></td><td>—</td></tr>
</tbody></table>

<script type="text/php">
if (isset($pdf)) {
  $font = $fontMetrics->get_font("DejaVu Sans", "normal"); $size = 9;
  $pdf->page_text(40, 810, date("Y-m-d H:i"), $font, $size, array(0,0,0));
  $pdf->page_text(270, 810, "Protokoll-ID: <?php echo $protId; ?>", $font, $size, array(0,0,0));
  $pdf->page_text(500, 810, "Seite {PAGE_NUM} von {PAGE_COUNT}", $font, $size, array(0,0,0));
}
</script>
</body></html>
<?php
        $html = ob_get_clean();
        $dompdf = new Dompdf(); $dompdf->loadHtml($html,'UTF-8'); $dompdf->setPaper('A4','portrait'); $dompdf->render();
        $base = realpath(__DIR__.'/../storage/pdfs') ?: (__DIR__.'/../storage/pdfs'); if(!is_dir($base)) @mkdir($base,0775,true);
        $dir=$base.'/'.$protocolId; if(!is_dir($dir)) @mkdir($dir,0775,true);
        $path=$dir.'/v'.$versionNo.'.pdf'; file_put_contents($path,$dompdf->output());
        $pdo->prepare("UPDATE protocol_versions SET pdf_path=? WHERE protocol_id=? AND version_no=?")->execute([$path,$protocolId,$versionNo]);
        return $path;
    }

    public static function getOrRender(string $protocolId, int $versionNo, bool $withPhotos=true): string
    {
        $pdo = Database::pdo();
        $st=$pdo->prepare("SELECT signed_pdf_path,pdf_path FROM protocol_versions WHERE protocol_id=? AND version_no=?");
        $st->execute([$protocolId,$versionNo]); $row=$st->fetch(PDO::FETCH_ASSOC) ?: [];
        if(!empty($row['signed_pdf_path']) && is_file((string)$row['signed_pdf_path'])) return (string)$row['signed_pdf_path'];
        if(!empty($row['pdf_path']) && is_file((string)$row['pdf_path'])) return (string)$row['pdf_path'];
        return self::renderAndSave($protocolId,$versionNo,$withPhotos);
    }
}
