<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Database;
use App\Auth;
use App\View;
use PDO;

final class ManagersController {
    public function index(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT id,name,company,address,email,phone FROM managers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $html = '<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Hausverwaltungen</h1><a class="btn btn-sm btn-primary" href="/managers/new">Neu</a></div>';
        $html .= '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Name</th><th>Firma</th><th>E-Mail</th><th>Telefon</th><th></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.htmlspecialchars($r['name']).'</td><td>'.htmlspecialchars($r['company']??'').'</td><td>'.htmlspecialchars($r['email']??'').'</td><td>'.htmlspecialchars($r['phone']??'').'</td>'
                   . '<td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/managers/edit?id='.$r['id'].'">Bearbeiten</a></td></tr>';
        }
        $html .= '</tbody></table></div>';
        View::render('Hausverwaltungen – Wohnungsübergabe', $html);
    }
    public function form(): void {
        Auth::requireAuth();
        $id = $_GET['id'] ?? null;
        $pdo = Database::pdo();
        $data = ['id'=>'','name'=>'','company'=>'','address'=>'','email'=>'','phone'=>''];
        if ($id) {
            $st = $pdo->prepare('SELECT * FROM managers WHERE id=?'); $st->execute([$id]); $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) { $data = $row; }
        }
        $html = '<h1 class="h4 mb-3">'.($id?'Hausverwaltung bearbeiten':'Hausverwaltung anlegen').'</h1>';
        $html .= '<form method="post" action="/managers/save">';
        $html .= '<input type="hidden" name="id" value="'.htmlspecialchars((string)$data['id']).'">';
        $html .= '<div class="row g-3"><div class="col-md-6"><label class="form-label">Name</label><input required class="form-control" name="name" value="'.htmlspecialchars($data['name']).'"></div>';
        $html .= '<div class="col-md-6"><label class="form-label">Firma</label><input class="form-control" name="company" value="'.htmlspecialchars((string)$data['company']).'"></div>';
        $html .= '<div class="col-12"><label class="form-label">Adresse</label><input class="form-control" name="address" value="'.htmlspecialchars((string)$data['address']).'"></div>';
        $html .= '<div class="col-md-6"><label class="form-label">E-Mail</label><input type="email" class="form-control" name="email" value="'.htmlspecialchars((string)$data['email']).'"></div>';
        $html .= '<div class="col-md-6"><label class="form-label">Telefon</label><input class="form-control" name="phone" value="'.htmlspecialchars((string)$data['phone']).'"></div></div>';
        $html .= '<div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Speichern</button><a class="btn btn-outline-secondary" href="/managers">Abbrechen</a></div>';
        $html .= '</form>';
        View::render('Hausverwaltung – Formular', $html);
    }
    public function save(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = $_POST['id'] ?? '';
        $vals = [$_POST['name'] ?? '', $_POST['company'] ?? null, $_POST['address'] ?? null, $_POST['email'] ?? null, $_POST['phone'] ?? null];
        if ($id) {
            $stmt = $pdo->prepare('UPDATE managers SET name=?, company=?, address=?, email=?, phone=? WHERE id=?');
            $stmt->execute([$vals[0],$vals[1],$vals[2],$vals[3],$vals[4],$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO managers (id,name,company,address,email,phone,created_at) VALUES (UUID(),?,?,?,?,?,NOW())');
            $stmt->execute($vals);
        }
        header('Location: /managers');
    }
}
