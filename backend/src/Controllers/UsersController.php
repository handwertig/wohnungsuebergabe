<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\UserAuth;
use App\Database;
use App\Validation;
use App\Flash;
use App\View;
use PDO;

final class UsersController 
{
    private function esc(?string $str): string {
        return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function renderBreadcrumb(string $currentPage = '', string $action = ''): string {
        $html = '<nav aria-label="breadcrumb" class="mb-4">';
        $html .= '<ol class="breadcrumb bg-light rounded-3 p-3">';
        $html .= '<li class="breadcrumb-item">';
        $html .= '<a href="/settings" class="text-decoration-none">';
        $html .= '<i class="bi bi-gear me-1"></i>Einstellungen';
        $html .= '</a></li>';
        $html .= '<li class="breadcrumb-item">';
        $html .= '<a href="/settings/users" class="text-decoration-none">';
        $html .= '<i class="bi bi-people me-1"></i>Benutzer';
        $html .= '</a></li>';
        
        if ($currentPage === 'management') {
            $html .= '<li class="breadcrumb-item active" aria-current="page">';
            $html .= '<i class="bi bi-person-gear me-1"></i>Verwaltung';
            $html .= '</li>';
        }
        
        if ($action) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">';
            $html .= '<i class="bi bi-' . ($action === 'new' ? 'person-plus' : 'pencil') . ' me-1"></i>';
            $html .= ($action === 'new' ? 'Neuer Benutzer' : 'Bearbeiten');
            $html .= '</li>';
        }
        
        $html .= '</ol></nav>';
        return $html;
    }

    public function index(): void 
    {
        UserAuth::requireAdmin();
        
        $pdo = Database::pdo();
        $stmt = $pdo->query('
            SELECT u.id, u.email, u.role, u.company, u.phone, u.created_at,
                   (SELECT COUNT(*) FROM user_manager_assignments WHERE user_id = u.id) as manager_count,
                   (SELECT COUNT(*) FROM user_owner_assignments WHERE user_id = u.id) as owner_count
            FROM users u 
            ORDER BY u.email
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html = $this->renderBreadcrumb('management');
        
        // Header mit Statistiken
        $html .= '<div class="d-flex align-items-center justify-content-between mb-4">';
        $html .= '<div class="d-flex align-items-center">';
        $html .= '<div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">';
        $html .= '<i class="bi bi-people text-primary fs-4"></i>';
        $html .= '</div>';
        $html .= '<div>';
        $html .= '<h2 class="h4 mb-1 text-dark">Benutzerverwaltung</h2>';
        $html .= '<p class="text-muted mb-0">'.count($rows).' Benutzer im System</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="d-flex gap-2">';
        $html .= '<a class="btn btn-primary" href="/users/new">';
        $html .= '<i class="bi bi-person-plus me-2"></i>Neuer Benutzer';
        $html .= '</a>';
        $html .= '<a class="btn btn-outline-secondary" href="/settings/users">';
        $html .= '<i class="bi bi-arrow-left me-2"></i>Zurück';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Rollen-Übersicht
        $html .= '<div class="row g-3 mb-4">';
        
        $adminCount = count(array_filter($rows, fn($r) => $r['role'] === 'admin'));
        $hausverwaltungCount = count(array_filter($rows, fn($r) => $r['role'] === 'hausverwaltung'));
        $eigentuemereCount = count(array_filter($rows, fn($r) => $r['role'] === 'eigentuemer'));
        
        $html .= '<div class="col-md-4">';
        $html .= '<div class="card border-0 bg-danger bg-opacity-10">';
        $html .= '<div class="card-body text-center">';
        $html .= '<div class="h3 text-danger mb-1">'.$adminCount.'</div>';
        $html .= '<div class="small text-muted">Administratoren</div>';
        $html .= '</div></div></div>';
        
        $html .= '<div class="col-md-4">';
        $html .= '<div class="card border-0 bg-warning bg-opacity-10">';
        $html .= '<div class="card-body text-center">';
        $html .= '<div class="h3 text-warning mb-1">'.$hausverwaltungCount.'</div>';
        $html .= '<div class="small text-muted">Hausverwaltungen</div>';
        $html .= '</div></div></div>';
        
        $html .= '<div class="col-md-4">';
        $html .= '<div class="card border-0 bg-success bg-opacity-10">';
        $html .= '<div class="card-body text-center">';
        $html .= '<div class="h3 text-success mb-1">'.$eigentuemereCount.'</div>';
        $html .= '<div class="small text-muted">Eigentümer</div>';
        $html .= '</div></div></div>';
        
        $html .= '</div>';
        
        // Benutzer-Tabelle
        $html .= '<div class="card border-0 shadow-sm">';
        $html .= '<div class="card-header bg-light border-0">';
        $html .= '<h5 class="mb-0 text-dark"><i class="bi bi-table me-2"></i>Alle Benutzer</h5>';
        $html .= '</div>';
        $html .= '<div class="card-body p-0">';
        
        if (empty($rows)) {
            $html .= '<div class="text-center py-5">';
            $html .= '<i class="bi bi-people text-muted" style="font-size: 3rem;"></i>';
            $html .= '<h5 class="text-muted mt-3">Keine Benutzer vorhanden</h5>';
            $html .= '<p class="text-muted">Erstellen Sie Ihren ersten Benutzer über den Button oben.</p>';
            $html .= '</div>';
        } else {
            $html .= '<div class="table-responsive">';
            $html .= '<table class="table table-hover mb-0">';
            $html .= '<thead class="table-light">';
            $html .= '<tr>';
            $html .= '<th class="border-0"><i class="bi bi-envelope me-1"></i>E-Mail</th>';
            $html .= '<th class="border-0"><i class="bi bi-person-badge me-1"></i>Rolle</th>';
            $html .= '<th class="border-0"><i class="bi bi-building me-1"></i>Firma</th>';
            $html .= '<th class="border-0"><i class="bi bi-link-45deg me-1"></i>Zuweisungen</th>';
            $html .= '<th class="border-0"><i class="bi bi-calendar me-1"></i>Erstellt</th>';
            $html .= '<th class="border-0 text-end"><i class="bi bi-gear me-1"></i>Aktionen</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            foreach ($rows as $r) {
                $html .= '<tr>';
                $html .= '<td class="border-0">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<i class="bi bi-person-circle text-muted me-2"></i>';
                $html .= '<span>'.$this->esc($r['email']).'</span>';
                $html .= '</div>';
                $html .= '</td>';
                
                // Rolle mit Badge
                $roleBadge = $this->getRoleBadge($r['role']);
                $html .= '<td class="border-0">'.$roleBadge.'</td>';
                
                $html .= '<td class="border-0">';
                $html .= !empty($r['company']) ? $this->esc($r['company']) : '<span class="text-muted">-</span>';
                $html .= '</td>';
                
                // Zuweisungen
                $assignments = [];
                if ($r['role'] === 'hausverwaltung' && $r['manager_count'] > 0) {
                    $assignments[] = '<span class="badge bg-warning bg-opacity-20 text-warning">'.$r['manager_count'].' Verwaltung(en)</span>';
                }
                if ($r['role'] === 'eigentuemer' && $r['owner_count'] > 0) {
                    $assignments[] = '<span class="badge bg-success bg-opacity-20 text-success">'.$r['owner_count'].' Eigentümer</span>';
                }
                $html .= '<td class="border-0">';
                $html .= $assignments ? implode(' ', $assignments) : '<span class="text-muted">Keine</span>';
                $html .= '</td>';
                
                $html .= '<td class="border-0">';
                $html .= '<span class="text-muted small">'.$this->esc(date('d.m.Y', strtotime($r['created_at']))).'</span>';
                $html .= '</td>';
                
                $html .= '<td class="border-0 text-end">';
                $html .= '<div class="btn-group btn-group-sm">';
                $html .= '<a class="btn btn-outline-primary" href="/users/edit?id='.$r['id'].'" title="Bearbeiten">';
                $html .= '<i class="bi bi-pencil"></i>';
                $html .= '</a>';
                $html .= '<a class="btn btn-outline-danger" href="/users/delete?id='.$r['id'].'" ';
                $html .= 'onclick="return confirm(\'Benutzer wirklich löschen?\')" title="Löschen">';
                $html .= '<i class="bi bi-trash"></i>';
                $html .= '</a>';
                $html .= '</div>';
                $html .= '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table></div>';
        }
        
        $html .= '</div></div>';
        
        View::render('Benutzerverwaltung', $html);
    }

    public function form(): void 
    {
        UserAuth::requireAdmin();
        
        $pdo = Database::pdo();
        $id = $_GET['id'] ?? null;
        $isEdit = !empty($id);
        $data = [
            'id' => '',
            'email' => '',
            'role' => 'eigentuemer',
            'company' => '',
            'phone' => '',
            'address' => ''
        ];
        
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
            $stmt->execute([$id]); 
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data = [
                    'id' => $row['id'] ?? '',
                    'email' => $row['email'] ?? '',
                    'role' => $row['role'] ?? 'eigentuemer',
                    'company' => $row['company'] ?? '',
                    'phone' => $row['phone'] ?? '',
                    'address' => $row['address'] ?? ''
                ];
            }
        }
        
        // Hole alle Manager und Owner für Zuweisungen
        $allManagers = UserAuth::getAllManagers();
        $allOwners = UserAuth::getAllOwners();
        
        // Aktuelle Zuweisungen
        $assignedManagerIds = $id ? UserAuth::getAssignedManagerIds($id) : [];
        $assignedOwnerIds = $id ? UserAuth::getAssignedOwnerIds($id) : [];
        
        $html = $this->renderBreadcrumb('management', $isEdit ? 'edit' : 'new');
        
        // Header
        $html .= '<div class="d-flex align-items-center justify-content-between mb-4">';
        $html .= '<div class="d-flex align-items-center">';
        $html .= '<div class="bg-'.($isEdit ? 'warning' : 'success').' bg-opacity-10 rounded-circle p-3 me-3">';
        $html .= '<i class="bi bi-person-'.($isEdit ? 'gear' : 'plus').' text-'.($isEdit ? 'warning' : 'success').' fs-4"></i>';
        $html .= '</div>';
        $html .= '<div>';
        $html .= '<h2 class="h4 mb-1 text-dark">'.($isEdit ? 'Benutzer bearbeiten' : 'Neuer Benutzer').'</h2>';
        $html .= '<p class="text-muted mb-0">'.($isEdit ? 'Benutzer-Daten und Zuweisungen bearbeiten' : 'Neuen Benutzer mit Rollen und Zuweisungen erstellen').'</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="d-flex gap-2">';
        $html .= '<a class="btn btn-outline-secondary" href="/users">';
        $html .= '<i class="bi bi-arrow-left me-2"></i>Zurück zur Liste';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<form method="post" action="/users/save" class="row g-4">';
        $html .= '<input type="hidden" name="id" value="'.$this->esc((string)$data['id']).'">';
        
        // Grunddaten
        $html .= '<div class="col-12">';
        $html .= '<div class="card border-0 shadow-sm">';
        $html .= '<div class="card-header bg-light border-0">';
        $html .= '<h5 class="mb-0 text-dark"><i class="bi bi-person me-2"></i>Grunddaten</h5>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        $html .= '<div class="row g-3">';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label text-dark fw-medium"><i class="bi bi-envelope me-2 text-muted"></i>E-Mail *</label>';
        $html .= '<input required type="email" class="form-control border-0 bg-light" name="email" value="'.$this->esc($data['email']).'" placeholder="benutzer@example.com">';
        $html .= '</div>';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label text-dark fw-medium"><i class="bi bi-person-badge me-2 text-muted"></i>Rolle *</label>';
        $html .= '<select class="form-select border-0 bg-light" name="role" onchange="toggleAssignments()">';
        $html .= '<option value="admin"'.($data['role']==='admin'?' selected':'').'>Administrator</option>';
        $html .= '<option value="hausverwaltung"'.($data['role']==='hausverwaltung'?' selected':'').'>Hausverwaltung</option>';
        $html .= '<option value="eigentuemer"'.($data['role']==='eigentuemer'?' selected':'').'>Eigentümer</option>';
        $html .= '</select>';
        $html .= '</div>';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label text-dark fw-medium"><i class="bi bi-key me-2 text-muted"></i>Passwort '.($id ? '(optional)' : '*').'</label>';
        $html .= '<input class="form-control border-0 bg-light" name="password" type="password" '.($id ? '' : 'required').' placeholder="'.($id ? 'Leer lassen für keine Änderung' : 'Mindestens 8 Zeichen').'">';
        $html .= '</div>';
        
        $html .= '</div></div></div></div>';
        
        // Profilangaben
        $html .= '<div class="col-12">';
        $html .= '<div class="card border-0 shadow-sm">';
        $html .= '<div class="card-header bg-light border-0">';
        $html .= '<h5 class="mb-0 text-dark"><i class="bi bi-person-lines-fill me-2"></i>Profilangaben</h5>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        $html .= '<div class="row g-3">';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label text-dark fw-medium"><i class="bi bi-building me-2 text-muted"></i>Firma</label>';
        $html .= '<input class="form-control border-0 bg-light" name="company" value="'.$this->esc($data['company']).'" placeholder="Firmenname (optional)">';
        $html .= '</div>';
        
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label text-dark fw-medium"><i class="bi bi-telephone me-2 text-muted"></i>Telefon</label>';
        $html .= '<input class="form-control border-0 bg-light" name="phone" value="'.$this->esc($data['phone']).'" placeholder="+49 xxx xxxxxxx">';
        $html .= '</div>';
        
        $html .= '<div class="col-12">';
        $html .= '<label class="form-label text-dark fw-medium"><i class="bi bi-geo-alt me-2 text-muted"></i>Adresse</label>';
        $html .= '<textarea class="form-control border-0 bg-light" rows="3" name="address" placeholder="Straße, PLZ Ort">'.$this->esc($data['address']).'</textarea>';
        $html .= '</div>';
        
        $html .= '</div></div></div></div>';
        
        // Hausverwaltungs-Zuweisungen
        if (!empty($allManagers)) {
            $html .= '<div class="col-12" id="manager-assignments">';
            $html .= '<div class="card border-0 shadow-sm">';
            $html .= '<div class="card-header bg-warning bg-opacity-10 border-0">';
            $html .= '<h5 class="mb-0 text-dark"><i class="bi bi-briefcase me-2"></i>Hausverwaltungs-Zuweisungen</h5>';
            $html .= '</div>';
            $html .= '<div class="card-body">';
            $html .= '<p class="text-muted mb-3">Wählen Sie die Hausverwaltungen aus, die dieser Benutzer verwalten darf:</p>';
            
            $html .= '<div class="row g-2">';
            foreach ($allManagers as $manager) {
                $checked = in_array($manager['id'], $assignedManagerIds) ? ' checked' : '';
                $html .= '<div class="col-md-6">';
                $html .= '<div class="form-check p-3 border rounded bg-light">';
                $html .= '<input class="form-check-input" type="checkbox" name="manager_ids[]" value="'.$this->esc($manager['id']).'" id="mgr_'.$this->esc($manager['id']).'"'.$checked.'>';
                $html .= '<label class="form-check-label fw-medium" for="mgr_'.$this->esc($manager['id']).'">';
                $html .= $this->esc($manager['name']);
                if ($manager['company']) {
                    $html .= '<br><small class="text-muted">' . $this->esc($manager['company']) . '</small>';
                }
                $html .= '</label>';
                $html .= '</div></div>';
            }
            $html .= '</div>';
            
            $html .= '</div></div></div>';
        }
        
        // Eigentümer-Zuweisungen
        if (!empty($allOwners)) {
            $html .= '<div class="col-12" id="owner-assignments">';
            $html .= '<div class="card border-0 shadow-sm">';
            $html .= '<div class="card-header bg-success bg-opacity-10 border-0">';
            $html .= '<h5 class="mb-0 text-dark"><i class="bi bi-person-check me-2"></i>Eigentümer-Zuweisungen</h5>';
            $html .= '</div>';
            $html .= '<div class="card-body">';
            $html .= '<p class="text-muted mb-3">Wählen Sie die Eigentümer aus, die dieser Benutzer einsehen darf:</p>';
            
            $html .= '<div class="row g-2">';
            foreach ($allOwners as $owner) {
                $checked = in_array($owner['id'], $assignedOwnerIds) ? ' checked' : '';
                $html .= '<div class="col-md-6">';
                $html .= '<div class="form-check p-3 border rounded bg-light">';
                $html .= '<input class="form-check-input" type="checkbox" name="owner_ids[]" value="'.$this->esc($owner['id']).'" id="own_'.$this->esc($owner['id']).'"'.$checked.'>';
                $html .= '<label class="form-check-label fw-medium" for="own_'.$this->esc($owner['id']).'">';
                $html .= $this->esc($owner['name']);
                if ($owner['company']) {
                    $html .= '<br><small class="text-muted">' . $this->esc($owner['company']) . '</small>';
                }
                $html .= '</label>';
                $html .= '</div></div>';
            }
            $html .= '</div>';
            
            $html .= '</div></div></div>';
        }
        
        // Speichern/Abbrechen
        $html .= '<div class="col-12">';
        $html .= '<div class="d-flex gap-2">';
        $html .= '<button class="btn btn-success px-4"><i class="bi bi-check-circle me-2"></i>Speichern</button>';
        $html .= '<a class="btn btn-outline-secondary px-4" href="/users"><i class="bi bi-x-circle me-2"></i>Abbrechen</a>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</form>';
        
        // JavaScript für Rollenabhängige Anzeige
        $html .= '<script>';
        $html .= 'function toggleAssignments() {';
        $html .= '  const role = document.querySelector(\'select[name="role"]\').value;';
        $html .= '  const managerDiv = document.getElementById("manager-assignments");';
        $html .= '  const ownerDiv = document.getElementById("owner-assignments");';
        $html .= '  ';
        $html .= '  if (managerDiv) {';
        $html .= '    managerDiv.style.display = (role === "hausverwaltung") ? "block" : "none";';
        $html .= '  }';
        $html .= '  if (ownerDiv) {';
        $html .= '    ownerDiv.style.display = (role === "eigentuemer") ? "block" : "none";';
        $html .= '  }';
        $html .= '}';
        $html .= 'document.addEventListener("DOMContentLoaded", toggleAssignments);';
        $html .= '</script>';
        
        View::render('Benutzer – Formular', $html);
    }

    public function save(): void 
    {
        UserAuth::requireAdmin();
        
        $pdo = Database::pdo();
        $id = $_POST['id'] ?? '';
        $email = trim((string)($_POST['email'] ?? ''));
        $role = in_array($_POST['role'] ?? '', ['admin', 'hausverwaltung', 'eigentuemer']) 
                ? $_POST['role'] : 'eigentuemer';
        $company = trim((string)($_POST['company'] ?? '')) ?: null;
        $phone = trim((string)($_POST['phone'] ?? '')) ?: null;
        $address = trim((string)($_POST['address'] ?? '')) ?: null;
        $password = (string)($_POST['password'] ?? '');
        
        $managerIds = $_POST['manager_ids'] ?? [];
        $ownerIds = $_POST['owner_ids'] ?? [];
        
        // Validierung
        $errors = Validation::required(['email' => $email, 'role' => $role], ['email', 'role']);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ungültige E-Mail-Adresse';
        }

        if ($errors) { 
            Flash::add('error', 'Bitte Eingaben prüfen.'); 
            header('Location: ' . ($id ? '/users/edit?id='.$id : '/users/new')); 
            exit; 
        }

        try {
            $pdo->beginTransaction();
            
            if ($id) {
                // Benutzer aktualisieren
                if ($password !== '') {
                    $stmt = $pdo->prepare('
                        UPDATE users 
                        SET email=?, role=?, company=?, phone=?, address=?, password_hash=?, updated_at=NOW() 
                        WHERE id=?
                    ');
                    $stmt->execute([$email, $role, $company, $phone, $address, password_hash($password, PASSWORD_BCRYPT), $id]);
                } else {
                    $stmt = $pdo->prepare('
                        UPDATE users 
                        SET email=?, role=?, company=?, phone=?, address=?, updated_at=NOW() 
                        WHERE id=?
                    ');
                    $stmt->execute([$email, $role, $company, $phone, $address, $id]);
                }
                
                Flash::add('success', 'Benutzer aktualisiert.');
            } else {
                // Neuen Benutzer erstellen
                $stmt = $pdo->prepare('
                    INSERT INTO users (id, email, password_hash, role, company, phone, address, created_at) 
                    VALUES (UUID(), ?, ?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([$email, password_hash($password, PASSWORD_BCRYPT), $role, $company, $phone, $address]);
                
                // Neue ID holen
                $id = $pdo->lastInsertId();
                if (!$id) {
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? ORDER BY created_at DESC LIMIT 1');
                    $stmt->execute([$email]);
                    $id = $stmt->fetchColumn();
                }
                
                Flash::add('success', 'Benutzer angelegt.');
            }
            
            // Zuweisungen aktualisieren
            if ($role === 'hausverwaltung') {
                UserAuth::setManagerAssignments($id, $managerIds);
                UserAuth::setOwnerAssignments($id, []); // Lösche Owner-Zuweisungen
            } elseif ($role === 'eigentuemer') {
                UserAuth::setOwnerAssignments($id, $ownerIds);
                UserAuth::setManagerAssignments($id, []); // Lösche Manager-Zuweisungen
            } else {
                // Admin: Lösche alle Zuweisungen
                UserAuth::setManagerAssignments($id, []);
                UserAuth::setOwnerAssignments($id, []);
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            Flash::add('error', 'Fehler beim Speichern: ' . $e->getMessage());
            header('Location: ' . ($id ? '/users/edit?id='.$id : '/users/new'));
            exit;
        }
        
        header('Location: /users'); 
        exit;
    }

    public function delete(): void 
    {
        UserAuth::requireAdmin();
        
        $id = $_GET['id'] ?? '';
        
        if ($id) {
            $pdo = Database::pdo();
            
            // Prüfe ob es der letzte Admin ist
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = "admin"');
            $stmt->execute();
            $adminCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $userRole = $stmt->fetchColumn();
            
            if ($userRole === 'admin' && $adminCount <= 1) {
                Flash::add('error', 'Der letzte Administrator kann nicht gelöscht werden.');
                header('Location: /users');
                exit;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Zuweisungen werden durch CASCADE automatisch gelöscht
                $stmt = $pdo->prepare('DELETE FROM users WHERE id=?');
                $stmt->execute([$id]);
                
                $pdo->commit();
                Flash::add('success', 'Benutzer gelöscht.');
            } catch (Exception $e) {
                $pdo->rollBack();
                Flash::add('error', 'Fehler beim Löschen: ' . $e->getMessage());
            }
        }
        
        header('Location: /users'); 
        exit;
    }
    
    private function getRoleBadge(string $role): string 
    {
        switch ($role) {
            case 'admin':
                return '<span class="badge bg-danger">Administrator</span>';
            case 'hausverwaltung':
                return '<span class="badge bg-warning text-dark">Hausverwaltung</span>';
            case 'eigentuemer':
                return '<span class="badge bg-success">Eigentümer</span>';
            default:
                return '<span class="badge bg-secondary">' . $this->esc($role) . '</span>';
        }
    }
}
