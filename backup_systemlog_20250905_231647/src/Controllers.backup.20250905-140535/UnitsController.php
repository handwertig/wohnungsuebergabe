<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Database;
use App\Auth;
use App\View;
use PDO;

final class UnitsController {
    public function index(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT u.id,u.label,u.floor,o.city,o.street,o.house_no FROM units u JOIN objects o ON o.id=u.object_id ORDER BY o.city,o.street,o.house_no,u.label')->fetchAll(PDO::FETCH_ASSOC);
        $html = '<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Wohneinheiten</h1><a class="btn btn-sm btn-primary" href="/units/new">Neu</a></div>';
        $html .= '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Objekt</th><th>WE</th><th>Etage</th><th></th></tr></thead><tbody>';
        forEach ($rows as $r) {
            $obj = $r['city'].', '.$r['street'].' '.$r['house_no'];
            $html .= '<tr><td>'.htmlspecialchars($obj).'</td><td>'.htmlspecialchars($r['label']).'</td><td>'.htmlspecialchars((string)$r['floor']).'</td>'
                   . '<td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/units/edit?id='.$r['id'].'">Bearbeiten</a></td></tr>';
        }
        $html .= '</tbody></table></div>';
        View::render('Wohneinheiten – Wohnungsübergabe', $html);
    }
    public function form(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = $_GET['id'] ?? null;
        $data = ['id'=>'','object_id'=>'','label'=>'','floor'=>''];
        if ($id) {
            $st = $pdo->prepare('SELECT * FROM units WHERE id=?'); $st->execute([$id]); $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) { $data = $row; }
        }
        $objects = $pdo->query('SELECT id,city,street,house_no FROM objects ORDER BY city,street,house_no')->fetchAll(PDO::FETCH_ASSOC);
        $html = '<h1 class="h4 mb-3">'.($id?'Wohneinheit bearbeiten':'Wohneinheit anlegen').'</h1>';
        $html .= '<form method="post" action="/units/save">';
        $html .= '<input type="hidden" name="id" value="'.htmlspecialchars((string)$data['id']).'">';
        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-6"><label class="form-label">Objekt</label><select required class="form-select" name="object_id"><option value="">Bitte wählen…</option>';
        foreach ($objects as $o) {
            $sel = $data['object_id'] === $o['id'] ? ' selected' : '';
            $html .= '<option value="'.$o['id'].'"'.$sel.'>'.htmlspecialchars($o['city'].', '.$o['street'].' '.$o['house_no']).'</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="col-md-3"><label class="form-label">WE-Bezeichnung</label><input required class="form-control" name="label" value="'.htmlspecialchars($data['label']).'"></div>';
        $html .= '<div class="col-md-3"><label class="form-label">Etage (optional)</label><input class="form-control" name="floor" value="'.htmlspecialchars((string)$data['floor']).'"></div>';
        $html .= '</div><div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Speichern</button><a class="btn btn-outline-secondary" href="/units">Abbrechen</a></div>';
        $html .= '</form>';
        View::render('Wohneinheiten – Formular', $html);
    }
    public function save(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = $_POST['id'] ?? '';
        $vals = [$_POST['object_id'] ?? '', $_POST['label'] ?? '', $_POST['floor'] ?? null];
        if ($id) {
            $stmt = $pdo->prepare('UPDATE units SET object_id=?, label=?, floor=? WHERE id=?');
            $stmt->execute([$vals[0],$vals[1],$vals[2],$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO units (id,object_id,label,floor,created_at) VALUES (UUID(),?,?,?,NOW())');
            $stmt->execute($vals);
        }
        header('Location: /units');
    }
}
