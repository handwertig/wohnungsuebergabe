<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Validation;
use App\Flash;
use App\View;
use App\AuditLogger;
use PDO;

final class OwnersController 
{
    public function index(): void 
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT id,name,company,email,phone FROM owners WHERE deleted_at IS NULL ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        
        $html = '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h1 class="h4 mb-0">Eigentümer</h1>';
        $html .= '<a class="btn btn-sm btn-primary" href="/owners/new">Neu</a>';
        $html .= '</div>';
        
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-striped">';
        $html .= '<thead><tr><th>Name</th><th>Firma</th><th>E-Mail</th><th>Telefon</th><th class="text-end">Aktion</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td>'.htmlspecialchars($r['name']).'</td>';
            $html .= '<td>'.htmlspecialchars($r['company'] ?? '').'</td>';
            $html .= '<td>'.htmlspecialchars($r['email'] ?? '').'</td>';
            $html .= '<td>'.htmlspecialchars($r['phone'] ?? '').'</td>';
            $html .= '<td class="text-end">';
            $html .= '<a class="btn btn-sm btn-outline-secondary me-2" href="/owners/edit?id='.$r['id'].'">Bearbeiten</a>';
            $html .= '<a class="btn btn-sm btn-outline-danger" href="/owners/delete?id='.$r['id'].'" onclick="return confirm(\'Wirklich löschen?\')">Löschen</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table></div>';
        View::render('Eigentümer – Wohnungsübergabe', $html);
    }

    public function form(): void 
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = $_GET['id'] ?? null;
        $data = ['id'=>'','name'=>'','company'=>'','address'=>'','email'=>'','phone'=>''];
        
        if ($id) {
            $st = $pdo->prepare('SELECT * FROM owners WHERE id=? AND deleted_at IS NULL');
            $st->execute([$id]); 
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $data = $row;
            }
        }
        
        $html = '<h1 class="h4 mb-3">'.($id ? 'Eigentümer bearbeiten' : 'Eigentümer anlegen').'</h1>';
        $html .= '<form method="post" action="/owners/save">';
        $html .= '<input type="hidden" name="id" value="'.htmlspecialchars((string)$data['id']).'">';
        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Name *</label>';
        $html .= '<input required class="form-control" name="name" value="'.htmlspecialchars($data['name']).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Firma</label>';
        $html .= '<input class="form-control" name="company" value="'.htmlspecialchars((string)$data['company']).'">';
        $html .= '</div>';
        $html .= '<div class="col-12">';
        $html .= '<label class="form-label">Adresse</label>';
        $html .= '<input class="form-control" name="address" value="'.htmlspecialchars((string)$data['address']).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">E-Mail</label>';
        $html .= '<input type="email" class="form-control" name="email" value="'.htmlspecialchars((string)$data['email']).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Telefon</label>';
        $html .= '<input class="form-control" name="phone" value="'.htmlspecialchars((string)$data['phone']).'">';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="mt-3 d-flex gap-2">';
        $html .= '<button class="btn btn-primary">Speichern</button>';
        $html .= '<a class="btn btn-outline-secondary" href="/owners">Abbrechen</a>';
        $html .= '</div>';
        $html .= '</form>';
        
        View::render('Eigentümer – Formular', $html);
    }

    public function save(): void 
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = $_POST['id'] ?? '';
        
        $vals = [
            'name'    => trim((string)($_POST['name'] ?? '')),
            'company' => trim((string)($_POST['company'] ?? '')),
            'address' => trim((string)($_POST['address'] ?? '')),
            'email'   => trim((string)($_POST['email'] ?? '')),
            'phone'   => trim((string)($_POST['phone'] ?? '')),
        ];
        
        $errors = Validation::required($vals, ['name']);
        if ($vals['email'] && !filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ungültige E-Mail-Adresse';
        }

        if ($errors) {
            Flash::add('error', 'Bitte Eingaben prüfen.');
            $_SESSION['_form'] = $vals;
            header('Location: '.($id ? '/owners/edit?id='.$id : '/owners/new')); 
            exit;
        }

        if ($id) {
            $stmt = $pdo->prepare('UPDATE owners SET name=?, company=?, address=?, email=?, phone=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([
                $vals['name'],
                $vals['company'] ?: null,
                $vals['address'] ?: null,
                $vals['email'] ?: null,
                $vals['phone'] ?: null,
                $id
            ]);
            if (class_exists('App\AuditLogger')) {
                AuditLogger::log('owners', $id, 'update', $vals);
            }
            Flash::add('success','Eigentümer aktualisiert.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO owners (id,name,company,address,email,phone,created_at) VALUES (UUID(),?,?,?,?,?,NOW())');
            $stmt->execute([
                $vals['name'],
                $vals['company'] ?: null,
                $vals['address'] ?: null,
                $vals['email'] ?: null,
                $vals['phone'] ?: null
            ]);
            if (class_exists('App\AuditLogger')) {
                AuditLogger::log('owners', 'UUID()', 'create', $vals);
            }
            Flash::add('success','Eigentümer angelegt.');
        }
        
        header('Location: /owners'); 
        exit;
    }

    public function delete(): void 
    {
        Auth::requireAuth();
        $id = $_GET['id'] ?? '';
        
        if ($id) {
            $pdo = Database::pdo();
            $st = $pdo->prepare('UPDATE owners SET deleted_at=NOW() WHERE id=?');
            $st->execute([$id]);
            if (class_exists('App\AuditLogger')) {
                AuditLogger::log('owners', $id, 'delete', []);
            }
            Flash::add('success','Eigentümer gelöscht.');
        }
        
        header('Location: /owners'); 
        exit;
    }
}
