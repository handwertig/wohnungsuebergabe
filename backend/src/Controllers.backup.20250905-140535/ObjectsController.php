<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Database;
use App\Auth;
use App\View;
use PDO;

final class ObjectsController {
    public function index(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT id,city,postal_code,street,house_no FROM objects ORDER BY city,street,house_no')->fetchAll(PDO::FETCH_ASSOC);
        $html = '<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Objekte</h1><a class="btn btn-sm btn-primary" href="/objects/new">Neu</a></div>';
        $html .= '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Ort</th><th>PLZ</th><th>Straße</th><th>Nr.</th><th></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.htmlspecialchars($r['city']).'</td><td>'.htmlspecialchars($r['postal_code']??'').'</td><td>'.htmlspecialchars($r['street']).'</td><td>'.htmlspecialchars($r['house_no']).'</td>'
                   . '<td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/objects/edit?id='.$r['id'].'">Bearbeiten</a></td></tr>';
        }
        $html .= '</tbody></table></div>';
        View::render('Objekte – Wohnungsübergabe', $html);
    }
    public function form(): void {
        Auth::requireAuth();
        $id = $_GET['id'] ?? null;
        $pdo = Database::pdo();
        $data = ['id'=>'','city'=>'','postal_code'=>'','street'=>'','house_no'=>''];
        if ($id) {
            $st = $pdo->prepare('SELECT * FROM objects WHERE id=?'); $st->execute([$id]); $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) { $data = $row; }
        }
        $html = '<h1 class="h4 mb-3">'.($id?'Objekt bearbeiten':'Objekt anlegen').'</h1>';
        $html .= '<form method="post" action="/objects/save">';
        $html .= '<input type="hidden" name="id" value="'.htmlspecialchars((string)$data['id']).'">';
        $html .= '<div class="row g-3"><div class="col-md-6"><label class="form-label">Ort</label><input required class="form-control" name="city" value="'.htmlspecialchars($data['city']).'"></div>';
        $html .= '<div class="col-md-3"><label class="form-label">PLZ</label><input class="form-control" name="postal_code" value="'.htmlspecialchars((string)$data['postal_code']).'"></div>';
        $html .= '<div class="col-md-6"><label class="form-label">Straße</label><input required class="form-control" name="street" value="'.htmlspecialchars($data['street']).'"></div>';
        $html .= '<div class="col-md-2"><label class="form-label">Haus-Nr.</label><input required class="form-control" name="house_no" value="'.htmlspecialchars($data['house_no']).'"></div></div>';
        $html .= '<div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Speichern</button><a class="btn btn-outline-secondary" href="/objects">Abbrechen</a></div>';
        $html .= '</form>';
        View::render('Objekt – Formular', $html);
    }
    public function save(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = $_POST['id'] ?? '';
        $vals = [$_POST['city'] ?? '', $_POST['postal_code'] ?? null, $_POST['street'] ?? '', $_POST['house_no'] ?? ''];
        if ($id) {
            $stmt = $pdo->prepare('UPDATE objects SET city=?, postal_code=?, street=?, house_no=? WHERE id=?');
            $stmt->execute([$vals[0],$vals[1],$vals[2],$vals[3],$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO objects (id,city,postal_code,street,house_no,created_at) VALUES (UUID(),?,?,?,?,NOW())');
            $stmt->execute($vals);
        }
        header('Location: /objects');
    }
}
