<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Flash;
use App\Validation;
use App\View;
use App\AuditLogger;
use PDO;

final class ProtocolsController
{
    public function index(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $rows = $pdo->query("
            SELECT p.id, p.type, p.tenant_name, u.label AS unit_label, o.city, o.street, o.house_no, p.created_at
            FROM protocols p
            JOIN units u ON u.id = p.unit_id
            JOIN objects o ON o.id = u.object_id
            WHERE p.deleted_at IS NULL
            ORDER BY p.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Protokolle</h1><a class="btn btn-sm btn-primary" href="/protocols/new">Neu</a></div>';
        $html .= '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Typ</th><th>Mieter</th><th>Objekt / WE</th><th>Erstellt</th><th class="text-end">Aktion</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $obj = $r['city'].', '.$r['street'].' '.$r['house_no'].' – '.$r['unit_label'];
            $html .= '<tr><td>'.htmlspecialchars($r['type']).'</td><td>'.htmlspecialchars($r['tenant_name']).'</td><td>'.htmlspecialchars($obj).'</td><td>'.htmlspecialchars($r['created_at']).'</td>'
                   . '<td class="text-end"><a class="btn btn-sm btn-outline-secondary me-2" href="/protocols/edit?id='.$r['id'].'">Bearbeiten</a>'
                   . '<a class="btn btn-sm btn-outline-danger" href="/protocols/delete?id='.$r['id'].'" onclick="return confirm(\'Wirklich löschen?\')">Löschen</a></td></tr>';
        }
        $html .= '</tbody></table></div>';
        View::render('Protokolle – Wohnungsübergabe', $html);
    }

    public function form(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id  = $_GET['id'] ?? null;
        $data = ['id'=>'','unit_id'=>'','type'=>'einzug','tenant_name'=>'','payload'=>'{}'];
        if ($id) {
            $st = $pdo->prepare('SELECT * FROM protocols WHERE id=? AND deleted_at IS NULL'); $st->execute([$id]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) $data = $row;
        }
        $units = $pdo->query('SELECT u.id,u.label,o.city,o.street,o.house_no FROM units u JOIN objects o ON o.id=u.object_id ORDER BY o.city,o.street,o.house_no,u.label')->fetchAll(PDO::FETCH_ASSOC);

        $html = '<h1 class="h4 mb-3">'.($id?'Protokoll bearbeiten':'Protokoll anlegen').'</h1>';
        $html .= '<form method="post" action="/protocols/save">';
        $html .= '<input type="hidden" name="id" value="'.htmlspecialchars((string)$data['id']).'">';
        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-6"><label class="form-label">Wohneinheit *</label><select class="form-select" name="unit_id" required><option value="">Bitte wählen…</option>';
        foreach ($units as $u) {
            $sel = $data['unit_id'] === $u['id'] ? ' selected' : '';
            $html .= '<option value="'.$u['id'].'"'.$sel.'>'.htmlspecialchars($u['city'].', '.$u['street'].' '.$u['house_no'].' – '.$u['label']).'</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="col-md-3"><label class="form-label">Typ *</label><select class="form-select" name="type" required>';
        foreach (['einzug','auszug','zwischen'] as $t) {
            $html .= '<option value="'.$t.'"'.($data['type']===$t?' selected':'').'>'.$t.'</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="col-md-6"><label class="form-label">Mietername *</label><input class="form-control" name="tenant_name" required value="'.htmlspecialchars($data['tenant_name']).'"></div>';
        $html .= '</div>';

        $html .= '<div class="mt-3"><label class="form-label">Daten (JSON, MVP)</label>';
        $html .= '<textarea class="form-control" rows="10" name="payload">'.htmlspecialchars((string)$data['payload']).'</textarea>';
        $html .= '<div class="form-text">Hier landen die Formularinhalte (Räume, Zähler, Schlüssel, …) als JSON. Wizard folgt.</div></div>';

        $html .= '<div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Speichern (Version anlegen)</button><a class="btn btn-outline-secondary" href="/protocols">Abbrechen</a></div>';
        $html .= '</form>';

        if ($id) {
            $vs = $pdo->prepare('SELECT version_no, created_at, created_by FROM protocol_versions WHERE protocol_id=? ORDER BY version_no DESC');
            $vs->execute([$id]); $vers = $vs->fetchAll(PDO::FETCH_ASSOC);
            $html .= '<hr><h2 class="h5">Versionen</h2><ul class="list-group">';
            foreach ($vers as $v) {
                $html .= '<li class="list-group-item d-flex justify-content-between"><span>v'.(int)$v['version_no'].' · '.$v['created_at'].'</span><span>von '.htmlspecialchars((string)$v['created_by']).'</span></li>';
            }
            $html .= '</ul>';
        }

        View::render('Protokoll – Formular', $html);
    }

    public function save(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id  = $_POST['id'] ?? '';
        $vals = [
            'unit_id'     => (string)($_POST['unit_id'] ?? ''),
            'type'        => (string)($_POST['type'] ?? 'einzug'),
            'tenant_name' => trim((string)($_POST['tenant_name'] ?? '')),
            'payload'     => (string)($_POST['payload'] ?? '{}'),
        ];

        $errs = Validation::required($vals, ['unit_id','type','tenant_name']);
        if ($errs) { Flash::add('error','Bitte Pflichtfelder ausfüllen.'); header('Location: '.($id?'/protocols/edit?id='.$id:'/protocols/new')); exit; }

        if ($id) {
            $st = $pdo->prepare('UPDATE protocols SET unit_id=?, type=?, tenant_name=?, payload=?, updated_at=NOW() WHERE id=?');
            $st->execute([$vals['unit_id'],$vals['type'],$vals['tenant_name'],$vals['payload'],$id]);
            AuditLogger::log('protocols',$id,'update',$vals);
            $this->createVersion($id, $vals['payload']);
            Flash::add('success','Protokoll aktualisiert und versioniert.');
        } else {
            $st = $pdo->prepare('INSERT INTO protocols (id,unit_id,type,tenant_name,payload,created_at) VALUES (UUID(),?,?,?,?,NOW())');
            $st->execute([$vals['unit_id'],$vals['type'],$vals['tenant_name'],$vals['payload']]);
            $pid = $pdo->query("SELECT id FROM protocols ORDER BY created_at DESC LIMIT 1")->fetchColumn();
            AuditLogger::log('protocols',(string)$pid,'create',$vals);
            $this->createVersion((string)$pid, $vals['payload']);
            Flash::add('success','Protokoll angelegt und versioniert.');
        }
        header('Location: /protocols'); exit;
    }

    public function delete(): void {
        Auth::requireAuth();
        $id = $_GET['id'] ?? '';
        if ($id) {
            $pdo = Database::pdo();
            $pdo->prepare('UPDATE protocols SET deleted_at=NOW() WHERE id=?')->execute([$id]);
            AuditLogger::log('protocols',$id,'soft_delete');
            Flash::add('success','Protokoll gelöscht (Soft-Delete).');
        }
        header('Location: /protocols'); exit;
    }

    private function createVersion(string $protocolId, string $payload): void
    {
        $pdo = Database::pdo();
        $next = (int)$pdo->query("SELECT COALESCE(MAX(version_no),0)+1 FROM protocol_versions WHERE protocol_id=".$pdo->quote($protocolId))->fetchColumn();
        $user = Auth::user();
        $st = $pdo->prepare('INSERT INTO protocol_versions (id,protocol_id,version_no,data,created_by,created_at) VALUES (UUID(),?,?,?,?,NOW())');
        $st->execute([$protocolId,$next,$payload,$user['email'] ?? 'system']);
    }
}
