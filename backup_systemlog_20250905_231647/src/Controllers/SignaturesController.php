<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Flash;
use App\View;
use PDO;

final class SignaturesController
{
    // Übersicht & Eingabe: Signer-Liste + On-Device-Capture
    public function index(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $protocolId = (string)($_GET['protocol_id'] ?? '');

        // Protokoll prüfen
        $p = $pdo->prepare("SELECT p.id, u.label AS unit_label, o.city,o.street,o.house_no
                            FROM protocols p
                            JOIN units u ON u.id=p.unit_id
                            JOIN objects o ON o.id=u.object_id
                            WHERE p.id=? LIMIT 1");
        $p->execute([$protocolId]);
        $proto = $p->fetch(PDO::FETCH_ASSOC);
        if (!$proto) { Flash::add('error','Protokoll nicht gefunden.'); header('Location: /protocols'); return; }

        // Signer laden
        $s = $pdo->prepare("SELECT * FROM protocol_signers WHERE protocol_id=? ORDER BY order_no, role");
        $s->execute([$protocolId]);
        $signers = $s->fetchAll(PDO::FETCH_ASSOC);

        // existierende Unterschriften
        $sig = $pdo->prepare("SELECT signer_id, img_path, signed_at FROM protocol_signatures WHERE protocol_id=? ORDER BY signed_at DESC");
        $sig->execute([$protocolId]);
        $bySigner = [];
        foreach ($sig->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bySigner[$row['signer_id']] = $row;
        }

        // Rendering
        $title = $proto['city'].', '.$proto['street'].' '.$proto['house_no'].' – '.$proto['unit_label'];
        ob_start(); ?>
        <h1 class="h5 mb-2">Unterschriften</h1>
        <div class="text-muted mb-3"><?= htmlspecialchars($title) ?></div>

        <form id="sign-form" method="post" action="/signatures/save">
          <input type="hidden" name="protocol_id" value="<?= htmlspecialchars($protocolId) ?>">
          <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Rolle</th><th>Name</th><th>E-Mail</th><th>Pflicht</th><th>Unterschrift</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($signers as $sg):
                $exists = $bySigner[$sg['id']] ?? null;
                $badge = $sg['role']==='mieter' ? 'primary' : ($sg['role']==='eigentuemer'?'info':'secondary'); ?>
              <tr>
                <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($sg['role']) ?></span></td>
                <td><input class="form-control" name="signers[<?= htmlspecialchars($sg['id']) ?>][name]" value="<?= htmlspecialchars($sg['name']) ?>"></td>
                <td><input class="form-control" name="signers[<?= htmlspecialchars($sg['id']) ?>][email]" value="<?= htmlspecialchars((string)($sg['email'] ?? '')) ?>"></td>
                <td><input class="form-check-input" type="checkbox" name="signers[<?= htmlspecialchars($sg['id']) ?>][required]" <?= $sg['required']?'checked':''; ?>></td>
                <td style="min-width:340px;">
                  <?php if ($exists && $exists['img_path']): 
                        $url = $this->publicUrl((string)$exists['img_path']); ?>
                    <img src="<?= htmlspecialchars($url) ?>" alt="Signatur" style="height:60px;border:1px solid #ddd;border-radius:6px;background:#fff;">
                    <div class="small text-muted">signiert: <?= htmlspecialchars((string)$exists['signed_at']) ?></div>
                  <?php else: ?>
                    <div class="border rounded bg-white" style="height:160px;max-width:320px;">
                      <canvas data-sign-pad data-target-hidden="sig-<?= htmlspecialchars($sg['id']) ?>" style="width:100%;height:160px;"></canvas>
                      <input type="hidden" id="sig-<?= htmlspecialchars($sg['id']) ?>" name="sig_data[<?= htmlspecialchars($sg['id']) ?>]">
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-danger" href="/signatures/remove?protocol_id=<?= htmlspecialchars($protocolId) ?>&signer_id=<?= htmlspecialchars($sg['id']) ?>" onclick="return confirm('Unterschrift entfernen?')">entfernen</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>

          <div class="d-flex justify-content-between">
            <a class="btn btn-outline-secondary" href="/protocols/edit?id=<?= htmlspecialchars($protocolId) ?>">Zurück zum Protokoll</a>
            <div class="d-flex gap-2">
              <a class="btn btn-outline-primary" href="/signatures/manage?protocol_id=<?= htmlspecialchars($protocolId) ?>">Signer verwalten</a>
              <button class="btn btn-success">Unterschriften speichern</button>
            </div>
          </div>
        </form>

        <script src="/assets/sign_pad.js"></script>
        <?php
        View::render('Unterschriften', ob_get_clean());
    }

    // Speichert: aktualisierte Signer-Daten + neue on-device Unterschriften
    public function save(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $protocolId = (string)($_POST['protocol_id'] ?? '');
        if ($protocolId === '') { Flash::add('error','protocol_id fehlt.'); header('Location: /protocols'); return; }

        // 1) Stammdaten der Signer aktualisieren
        foreach ((array)($_POST['signers'] ?? []) as $sid => $row) {
            $name = trim((string)($row['name'] ?? ''));
            $email = trim((string)($row['email'] ?? ''));
            $req = isset($row['required']) ? 1 : 0;
            $up = $pdo->prepare("UPDATE protocol_signers SET name=?, email=?, required=?, updated_at=NOW() WHERE id=? AND protocol_id=?");
            $up->execute([$name, ($email!==''?$email:null), $req, $sid, $protocolId]);
        }

        // 2) Neue Signaturen (Base64 → PNG) speichern
        $uploadBase = realpath(__DIR__.'/../../storage/uploads') ?: (__DIR__.'/../../storage/uploads');
        if (!is_dir($uploadBase)) @mkdir($uploadBase, 0775, true);
        $targetDir = $uploadBase.'/'.$protocolId.'/signatures';
        if (!is_dir($targetDir)) @mkdir($targetDir, 0775, true);

        foreach ((array)($_POST['sig_data'] ?? []) as $sid => $dataUrl) {
            if (!is_string($dataUrl) || strpos($dataUrl, 'data:image/png;base64,') !== 0) continue;
            $raw = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')));
            if ($raw === false || strlen($raw) < 200) continue; // leere "Zeichnung" ignorieren

            $fname = date('Ymd_His').'-'.$sid.'.png';
            $path  = $targetDir.'/'.$fname;
            file_put_contents($path, $raw);

            // alte Signatur entfernen (optional) … hier überschreiben wir einfach mit neuer Zeile
            $ins = $pdo->prepare("INSERT INTO protocol_signatures (id, protocol_id, signer_id, type, img_path, signed_at, created_at) VALUES (UUID(),?,?,?,?,NOW(),NOW())");
            $ins->execute([$protocolId, $sid, 'on_device', $path]);

            $pdo->prepare("UPDATE protocol_signers SET status='signed', updated_at=NOW() WHERE id=?")->execute([$sid]);
        }

        Flash::add('success','Unterschriften gespeichert.');
        header('Location: /signatures?protocol_id='.$protocolId); exit;
    }

    // Verwaltung: Signer hinzufügen/entfernen (Rollen + Reihenfolge)
    public function manage(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $protocolId = (string)($_GET['protocol_id'] ?? '');
        if ($protocolId === '') { Flash::add('error','protocol_id fehlt.'); header('Location: /protocols'); return; }

        // Liste laden
        $s = $pdo->prepare("SELECT * FROM protocol_signers WHERE protocol_id=? ORDER BY order_no, role");
        $s->execute([$protocolId]);
        $signers = $s->fetchAll(PDO::FETCH_ASSOC);

        ob_start(); ?>
        <h1 class="h5 mb-3">Signer verwalten</h1>
        <form method="post" action="/signatures/manage">
          <input type="hidden" name="protocol_id" value="<?= htmlspecialchars($protocolId) ?>">
          <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Rolle</th><th>Name</th><th>E-Mail</th><th>Reihenfolge</th><th>Pflicht</th><th></th></tr></thead>
            <tbody id="sg-wrap">
            <?php foreach ($signers as $sg): ?>
              <tr>
                <td>
                  <select class="form-select" name="signers[<?= htmlspecialchars($sg['id']) ?>][role]">
                    <?php foreach (['mieter','eigentuemer','anwesend'] as $role): ?>
                    <option value="<?= $role ?>"<?= $sg['role']===$role?' selected':''; ?>><?= $role ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input class="form-control" name="signers[<?= htmlspecialchars($sg['id']) ?>][name]" value="<?= htmlspecialchars($sg['name']) ?>"></td>
                <td><input class="form-control" name="signers[<?= htmlspecialchars($sg['id']) ?>][email]" value="<?= htmlspecialchars((string)$sg['email']) ?>"></td>
                <td style="max-width:110px;"><input class="form-control" type="number" name="signers[<?= htmlspecialchars($sg['id']) ?>][order_no]" value="<?= (int)$sg['order_no'] ?>"></td>
                <td><input class="form-check-input" type="checkbox" name="signers[<?= htmlspecialchars($sg['id']) ?>][required]" <?= $sg['required']?'checked':''; ?>></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-danger" href="/signatures/delete?protocol_id=<?= htmlspecialchars($protocolId) ?>&signer_id=<?= htmlspecialchars($sg['id']) ?>" onclick="return confirm('Signer löschen?')">löschen</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
          <div class="d-flex justify-content-between">
            <a class="btn btn-outline-secondary" href="/signatures?protocol_id=<?= htmlspecialchars($protocolId) ?>">Zurück</a>
            <div class="d-flex gap-2">
              <a class="btn btn-outline-primary" href="/signatures/add?protocol_id=<?= htmlspecialchars($protocolId) ?>">+ Signer</a>
              <button class="btn btn-success">Speichern</button>
            </div>
          </div>
        </form>
        <?php View::render('Signer verwalten', ob_get_clean());
    }

    public function manageSave(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $protocolId = (string)($_POST['protocol_id'] ?? '');
        foreach ((array)($_POST['signers'] ?? []) as $sid => $row) {
            $role = in_array(($row['role'] ?? ''), ['mieter','eigentuemer','anwesend'], true) ? $row['role'] : 'anwesend';
            $name = trim((string)($row['name'] ?? ''));
            $email= trim((string)($row['email'] ?? ''));
            $ord  = (int)($row['order_no'] ?? 1);
            $req  = isset($row['required']) ? 1 : 0;
            $pdo->prepare("UPDATE protocol_signers SET role=?, name=?, email=?, order_no=?, required=?, updated_at=NOW() WHERE id=? AND protocol_id=?")
                ->execute([$role, $name, ($email!==''?$email:null), $ord, $req, $sid, $protocolId]);
        }
        Flash::add('success','Signer aktualisiert.');
        header('Location: /signatures/manage?protocol_id='.$protocolId); exit;
    }

    public function add(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $protocolId = (string)($_GET['protocol_id'] ?? '');
        $sid = $pdo->query("SELECT UUID()")->fetchColumn();
        $ord = 1 + (int)$pdo->query("SELECT COALESCE(MAX(order_no),0) FROM protocol_signers WHERE protocol_id=".$pdo->quote($protocolId))->fetchColumn();
        $pdo->prepare("INSERT INTO protocol_signers (id,protocol_id,role,name,order_no,required,created_at) VALUES (?,?,?,?,?,1,NOW())")
            ->execute([$sid, $protocolId, 'anwesend', 'Neuer Mitzeichner', $ord]);
        header('Location: /signatures/manage?protocol_id='.$protocolId); exit;
    }

    public function delete(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $protocolId = (string)($_GET['protocol_id'] ?? '');
        $signerId   = (string)($_GET['signer_id'] ?? '');
        $pdo->prepare("DELETE FROM protocol_signers WHERE id=? AND protocol_id=?")->execute([$signerId, $protocolId]);
        Flash::add('success','Signer entfernt.');
        header('Location: /signatures/manage?protocol_id='.$protocolId); exit;
    }

    public function remove(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $protocolId = (string)($_GET['protocol_id'] ?? '');
        $signerId   = (string)($_GET['signer_id'] ?? '');
        $pdo->prepare("DELETE FROM protocol_signatures WHERE signer_id=? AND protocol_id=?")->execute([$signerId, $protocolId]);
        $pdo->prepare("UPDATE protocol_signers SET status='pending', updated_at=NOW() WHERE id=?")->execute([$signerId]);
        Flash::add('success','Unterschrift entfernt.');
        header('Location: /signatures?protocol_id='.$protocolId); exit;
    }

    private function publicUrl(string $absPath): string
    {
        $base = realpath(__DIR__.'/../../storage/uploads');
        $real = realpath($absPath) ?: $absPath;
        if ($base && str_starts_with($real, $base)) {
            $rel = ltrim(substr($real, strlen($base)), '/');
            return '/uploads/'.$rel;
        }
        return '';
    }
}
