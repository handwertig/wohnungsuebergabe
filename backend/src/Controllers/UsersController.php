<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Validation;
use App\Flash;
use App\View;
use PDO;

final class UsersController 
{
    private function requireAdmin(): void 
    {
        Auth::requireAuth();
        $user = Auth::user();
        if (($user['role'] ?? '') !== 'admin') {
            Flash::add('error', 'Zugriff verweigert.');
            header('Location: /protocols'); 
            exit;
        }
    }

    public function index(): void 
    {
        $this->requireAdmin();
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT id,email,role,created_at FROM users ORDER BY email')->fetchAll(PDO::FETCH_ASSOC);
        
        $html = '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h1 class="h4 mb-0">Benutzer</h1>';
        $html .= '<a class="btn btn-sm btn-primary" href="/users/new">Neu</a>';
        $html .= '</div>';
        
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-striped">';
        $html .= '<thead><tr><th>E-Mail</th><th>Rolle</th><th>Erstellt</th><th class="text-end">Aktion</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td>'.htmlspecialchars($r['email']).'</td>';
            $html .= '<td>'.htmlspecialchars($r['role']).'</td>';
            $html .= '<td>'.htmlspecialchars($r['created_at']).'</td>';
            $html .= '<td class="text-end">';
            $html .= '<a class="btn btn-sm btn-outline-secondary me-2" href="/users/edit?id='.$r['id'].'">Bearbeiten</a>';
            $html .= '<a class="btn btn-sm btn-outline-danger" href="/users/delete?id='.$r['id'].'" onclick="return confirm(\'Wirklich löschen?\')">Löschen</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table></div>';
        View::render('Benutzer – Wohnungsübergabe', $html);
    }

    public function form(): void 
    {
        $this->requireAdmin();
        $pdo = Database::pdo();
        $id = $_GET['id'] ?? null;
        $data = ['id'=>'','email'=>'','role'=>'staff'];
        
        if ($id) {
            $st = $pdo->prepare('SELECT * FROM users WHERE id=?');
            $st->execute([$id]); 
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $data = $row;
            }
        }
        
        $html = '<h1 class="h4 mb-3">'.($id ? 'Benutzer bearbeiten' : 'Benutzer anlegen').'</h1>';
        $html .= '<form method="post" action="/users/save">';
        $html .= '<input type="hidden" name="id" value="'.htmlspecialchars((string)$data['id']).'">';
        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">E-Mail *</label>';
        $html .= '<input required type="email" class="form-control" name="email" value="'.htmlspecialchars($data['email']).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Rolle *</label>';
        $html .= '<select class="form-select" name="role">';
        $html .= '<option value="staff"'.($data['role']==='staff'?' selected':'').'>Mitarbeiter</option>';
        $html .= '<option value="admin"'.($data['role']==='admin'?' selected':'').'>Admin</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Neues Passwort '.($id ? '(optional)' : '*').'</label>';
        $html .= '<input class="form-control" name="password" type="password" '.($id ? '' : 'required').'>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="mt-3 d-flex gap-2">';
        $html .= '<button class="btn btn-primary">Speichern</button>';
        $html .= '<a class="btn btn-outline-secondary" href="/users">Abbrechen</a>';
        $html .= '</div>';
        $html .= '</form>';
        
        View::render('Benutzer – Formular', $html);
    }

    public function save(): void 
    {
        $this->requireAdmin();
        $pdo = Database::pdo();
        $id = $_POST['id'] ?? '';
        $email = trim((string)($_POST['email'] ?? ''));
        $role  = $_POST['role'] === 'admin' ? 'admin' : 'staff';
        $pass  = (string)($_POST['password'] ?? '');
        
        $errors = Validation::required(['email'=>$email,'role'=>$role], ['email','role']);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ungültige E-Mail-Adresse';
        }

        if ($errors) { 
            Flash::add('error','Bitte Eingaben prüfen.'); 
            header('Location: '.($id ? '/users/edit?id='.$id : '/users/new')); 
            exit; 
        }

        if ($id) {
            if ($pass !== '') {
                $stmt = $pdo->prepare('UPDATE users SET email=?, role=?, password_hash=? WHERE id=?');
                $stmt->execute([$email, $role, password_hash($pass, PASSWORD_BCRYPT), $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET email=?, role=? WHERE id=?');
                $stmt->execute([$email, $role, $id]);
            }
            Flash::add('success','Benutzer aktualisiert.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (id,email,password_hash,role,created_at) VALUES (UUID(),?,?,?,NOW())');
            $stmt->execute([$email, password_hash($pass, PASSWORD_BCRYPT), $role]);
            Flash::add('success','Benutzer angelegt.');
        }
        
        header('Location: /users'); 
        exit;
    }

    public function delete(): void 
    {
        $this->requireAdmin();
        $id = $_GET['id'] ?? '';
        
        if ($id) {
            $pdo = Database::pdo();
            $st = $pdo->prepare('DELETE FROM users WHERE id=?');
            $st->execute([$id]);
            Flash::add('success','Benutzer gelöscht.');
        }
        
        header('Location: /users'); 
        exit;
    }
}
