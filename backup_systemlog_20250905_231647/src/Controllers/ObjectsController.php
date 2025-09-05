<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Database;
use App\Auth;
use App\View;
use PDO;

final class ObjectsController {
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
            $html .= '<a href="/objects" class="text-decoration-none">';
            $html .= '<i class="bi bi-building me-1"></i>Objekte';
            $html .= '</a></li>';
            $html .= '<li class="breadcrumb-item active" aria-current="page">';
            $html .= '<i class="bi bi-' . ($action === 'new' ? 'plus-circle' : 'pencil') . ' me-1"></i>';
            $html .= ($action === 'new' ? 'Neues Objekt' : 'Bearbeiten');
        } else {
            $html .= '<span class="text-dark">';
            $html .= '<i class="bi bi-building me-1"></i>Objekte';
            $html .= '</span>';
        }
        $html .= '</li>';
        $html .= '</ol></nav>';
        return $html;
    }
    
    public function index(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT id,city,postal_code,street,house_no FROM objects ORDER BY city,street,house_no')->fetchAll(PDO::FETCH_ASSOC);
        
        $html = $this->renderBreadcrumb();
        
        $html .= '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h1 class="h4 mb-0">Objekte</h1>';
        $html .= '<a class="btn btn-sm btn-primary" href="/objects/new">Neu</a>';
        $html .= '</div>';
        
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-striped">';
        $html .= '<thead><tr><th>Ort</th><th>PLZ</th><th>Straße</th><th>Nr.</th><th class="text-end">Aktion</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td>'.$this->esc($r['city']).'</td>';
            $html .= '<td>'.$this->esc($r['postal_code']??'').'</td>';
            $html .= '<td>'.$this->esc($r['street']).'</td>';
            $html .= '<td>'.$this->esc($r['house_no']).'</td>';
            $html .= '<td class="text-end">';
            $html .= '<a class="btn btn-sm btn-outline-secondary me-2" href="/objects/edit?id='.$r['id'].'">Bearbeiten</a>';
            $html .= '<a class="btn btn-sm btn-outline-danger" href="/objects/delete?id='.$r['id'].'" onclick="return confirm(\'Wirklich löschen?\')">Löschen</a>';
            $html .= '</td>';
            $html .= '</tr>';
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
            $st = $pdo->prepare('SELECT * FROM objects WHERE id=?');
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $data = $row;
            }
        }
        
        $html = $this->renderBreadcrumb($id ? 'edit' : 'new');
        
        $html .= '<h1 class="h4 mb-3">'.($id ? 'Objekt bearbeiten' : 'Objekt anlegen').'</h1>';
        $html .= '<form method="post" action="/objects/save">';
        $html .= '<input type="hidden" name="id" value="'.$this->esc((string)$data['id']).'">';
        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Ort *</label>';
        $html .= '<input required class="form-control" name="city" value="'.$this->esc($data['city']).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-3">';
        $html .= '<label class="form-label">PLZ</label>';
        $html .= '<input class="form-control" name="postal_code" value="'.$this->esc((string)$data['postal_code']).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Straße *</label>';
        $html .= '<input required class="form-control" name="street" value="'.$this->esc($data['street']).'">';
        $html .= '</div>';
        $html .= '<div class="col-md-2">';
        $html .= '<label class="form-label">Haus-Nr. *</label>';
        $html .= '<input required class="form-control" name="house_no" value="'.$this->esc($data['house_no']).'">';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="mt-3 d-flex gap-2">';
        $html .= '<button class="btn btn-primary">Speichern</button>';
        $html .= '<a class="btn btn-outline-secondary" href="/objects">Abbrechen</a>';
        $html .= '</div>';
        $html .= '</form>';
        
        View::render('Objekt – Formular', $html);
    }
    
    public function save(): void {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $id = $_POST['id'] ?? '';
        $vals = [
            trim((string)($_POST['city'] ?? '')),
            trim((string)($_POST['postal_code'] ?? '')) ?: null,
            trim((string)($_POST['street'] ?? '')),
            trim((string)($_POST['house_no'] ?? ''))
        ];
        
        if ($id) {
            $stmt = $pdo->prepare('UPDATE objects SET city=?, postal_code=?, street=?, house_no=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$vals[0],$vals[1],$vals[2],$vals[3],$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO objects (id,city,postal_code,street,house_no,created_at) VALUES (UUID(),?,?,?,?,NOW())');
            $stmt->execute($vals);
        }
        
        header('Location: /objects');
        exit;
    }
    
    public function delete(): void {
        Auth::requireAuth();
        $id = $_GET['id'] ?? '';
        
        if ($id) {
            $pdo = Database::pdo();
            $st = $pdo->prepare('UPDATE objects SET deleted_at=NOW() WHERE id=?');
            $st->execute([$id]);
        }
        
        header('Location: /objects');
        exit;
    }
}
