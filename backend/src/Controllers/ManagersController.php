<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Database;
use App\Auth;
use App\View;
use PDO;

final class ManagersController {
    private function esc(?string $str): string {
        return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function renderBreadcrumb(string $action = ''): string {
        $html = '<nav aria-label="breadcrumb" class="mb-4">';
        $html .= '<ol class="breadcrumb bg-light rounded-3 p-3">';
        $html .= '<li class="breadcrumb-item">';
        $html .= '<a href="/settings" class="text-decoration-none">';
        $html .= '<i class="bi bi-gear me-1"></i>Einstellungen';
        $html .= '</a></li>';
        $html .= '<li class="breadcrumb-item">';
        $html .= '<a href="/settings" class="text-decoration-none">';
        $html .= '<i class="bi bi-database me-1"></i>Stammdaten';
        $html .= '</a></li>';
        $html .= '<li class="breadcrumb-item">';
        if ($action) {
            $html .= '<a href="/managers" class="text-decoration-none">';
            $html .= '<i class="bi bi-briefcase me-1"></i>Hausverwaltungen';
            $html .= '</a></li>';
            $html .= '<li class="breadcrumb-item active" aria-current="page">';
            $html .= '<i class="bi bi-' . ($action === 'new' ? 'plus-circle' : 'pencil') . ' me-1"></i>';
            $html .= ($action === 'new' ? 'Neue Hausverwaltung' : 'Bearbeiten');
        } else {
            $html .= '<span class="text-dark">';
            $html .= '<i class="bi bi-briefcase me-1"></i>Hausverwaltungen';
            $html .= '</span>';
        }
        $html .= '</li>';
        $html .= '</ol></nav>';
        return $html;
    }
    
    public function index(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT id,name,company,address,email,phone FROM managers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        
        $html = $this->renderBreadcrumb();
        
        $html .= '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h1 class="h4 mb-0">Hausverwaltungen</h1>';
        $html .= '<a class="btn btn-sm btn-primary" href="/managers/new">Neu</a>';
        $html .= '</div>';
        
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-striped">';
        $html .= '<thead><tr><th>Name</th><th>Firma</th><th>E-Mail</th><th>Telefon</th><th class="text-end">Aktion</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td>'.$this->esc($r['name']).'</td>';
            $html .= '<td>'.$this->esc($r['company']??'').'</td>';
            $html .= '<td>'.$this->esc($r['email']??'').'</td>';
            $html .= '<td>'.$this->esc($r['phone']??'').'</td>';
            $html .= '<td class="text-end">';
            $html .= '<a class="btn btn-sm btn-outline-secondary me-2" href="/managers/edit?id='.$r['id'].'">Bearbeiten</a>';
            $html .= '<a class="btn btn-sm btn-outline-danger" href="/managers/delete?id='.$r['id'].'" onclick="return confirm(\'Wirklich löschen?\')">Löschen</a>';
            $html .= '</td>';
            $html .= '</tr>';
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
            $st = $pdo->prepare('SELECT * FROM managers WHERE id=?');
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $data = $row;
            }
        }
        
        $html = $this->renderBreadcrumb($id ? 'edit' : 'new');
        
        $html .= '<h1 class="h4 mb-3">'.($id ? 'Hausverwaltung bearbeiten' : 'Hausverwaltung anlegen').'</h1>';
        $html .= '<form method="post" action="/managers/save">';
        $html .= '<input type="hidden" name="id" value="'.$this->esc((string)$data['id']).'">';
        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Name *</label>';
        $html .= '<input required class="form-control" name="name" value="'.$this->esc($data['name']).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Firma</label>';
        $html .= '<input class="form-control" name="company" value="'.$this->esc((string)$data['company']).'">';
        $html .= '</div>';
        $html .= '<div class="col-12">';
        $html .= '<label class="form-label">Adresse</label>';
        $html .= '<input class="form-control" name="address" value="'.$this->esc((string)$data['address']).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">E-Mail</label>';
        $html .= '<input type="email" class="form-control" name="email" value="'.$this->esc((string)$data['email']).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Telefon</label>';
        $html .= '<input class="form-control" name="phone" value="'.$this->esc((string)$data['phone']).'">';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="mt-3 d-flex gap-2">';
        $html .= '<button class="btn btn-primary">Speichern</button>';
        $html .= '<a class="btn btn-outline-secondary" href="/managers">Abbrechen</a>';
        $html .= '</div>';
        $html .= '</form>';
        
        View::render('Hausverwaltung – Formular', $html);
    }
    
    public function save(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = $_POST['id'] ?? '';
        $vals = [
            trim((string)($_POST['name'] ?? '')),
            trim((string)($_POST['company'] ?? '')) ?: null,
            trim((string)($_POST['address'] ?? '')) ?: null,
            trim((string)($_POST['email'] ?? '')) ?: null,
            trim((string)($_POST['phone'] ?? '')) ?: null
        ];
        
        if ($id) {
            $stmt = $pdo->prepare('UPDATE managers SET name=?, company=?, address=?, email=?, phone=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$vals[0],$vals[1],$vals[2],$vals[3],$vals[4],$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO managers (id,name,company,address,email,phone,created_at) VALUES (UUID(),?,?,?,?,?,NOW())');
            $stmt->execute($vals);
        }
        
        header('Location: /managers');
        exit;
    }
    
    public function delete(): void {
        Auth::requireAuth();
        $id = $_GET['id'] ?? '';
        
        if ($id) {
            $pdo = Database::pdo();
            $st = $pdo->prepare('UPDATE managers SET deleted_at=NOW() WHERE id=?');
            $st->execute([$id]);
        }
        
        header('Location: /managers');
        exit;
    }
}
