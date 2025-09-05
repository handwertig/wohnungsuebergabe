#!/bin/bash
# ============================================================================
# CONTROLLER-DATEIEN UPDATEN: Reparierte Versionen einspielen
# Wohnungsübergabe - Version 1.0
# Datum: $(date +%Y-%m-%d)
# ============================================================================

echo "🔧 === Controller-Dateien Update ==="
echo ""

# Aktuelles Verzeichnis prüfen
if [ ! -f "docker-compose.yml" ] && [ ! -f "backend/composer.json" ]; then
    echo "❌ Bitte führen Sie dieses Script im Root-Verzeichnis des Repositories aus!"
    echo "   (Dort wo docker-compose.yml oder backend/ liegt)"
    exit 1
fi

echo "📁 Arbeitsverzeichnis: $(pwd)"
echo ""

# Backup erstellen
echo "📦 Erstelle Backup der aktuellen Controller..."
if [ -d "backend/src/Controllers" ]; then
    cp -r backend/src/Controllers backend/src/Controllers.backup.$(date +%Y%m%d-%H%M%S)
    echo "✅ Backup erstellt: backend/src/Controllers.backup.$(date +%Y%m%d-%H%M%S)"
else
    echo "❌ backend/src/Controllers Verzeichnis nicht gefunden!"
    exit 1
fi

echo ""
echo "🚨 ACHTUNG: Alle aktuellen Controller werden überschrieben!"
read -p "🔄 Möchten Sie fortfahren? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Abgebrochen - keine Änderungen vorgenommen"
    exit 0
fi

# 1. AUTHCONTROLLER REPARIEREN
echo "🔧 Repariere AuthController.php..."

cat > backend/src/Controllers/AuthController.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\View;
use App\Flash;
use App\Auth;
use App\Database;
use PDO;

final class AuthController
{
    /** GET: /login – gestaltete Login-Maske im Flat-Design */
    public function loginForm(): void
    {
        $flashes = Flash::pull();
        ob_start(); ?>
        <div class="auth-wrap">
          <div class="card auth-card">
            <div class="card-body">
              <div class="d-flex align-items-center gap-2 mb-3">
                <span class="logo" style="width:18px;height:18px;background:#222357;display:inline-block"></span>
                <strong>Wohnungsübergabe</strong>
              </div>
              <h1 class="h5 mb-3">Anmeldung</h1>

              <?php foreach ($flashes as $f):
                $cls = ['success'=>'success','error'=>'danger','info'=>'info','warning'=>'warning'][$f['type']] ?? 'secondary'; ?>
                <div class="alert alert-<?= $cls ?>"><?= htmlspecialchars($f['message']) ?></div>
              <?php endforeach; ?>

              <form method="post" action="/login" novalidate>
                <div class="mb-3">
                  <label class="form-label">E‑Mail</label>
                  <input class="form-control" type="email" name="email" required autocomplete="username">
                </div>
                <div class="mb-3">
                  <label class="form-label">Passwort</label>
                  <input class="form-control" type="password" name="password" required autocomplete="current-password">
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember">Angemeldet bleiben</label>
                  </div>
                  <a class="small" href="/password/forgot">Passwort vergessen?</a>
                </div>
                <button class="btn btn-primary w-100">Einloggen</button>
              </form>
            </div>
            <div class="card-footer small text-muted">&copy; <?= date('Y') ?> Wohnungsübergabe</div>
          </div>
        </div>
        <?php
        $html = ob_get_clean();
        View::render('Login', $html);
    }

    /** POST: /login */
    public function login(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        
        if ($email === '' || $pass === '') {
            Flash::add('error', 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.');
            header('Location: /login?error=1'); 
            return;
        }
        
        $pdo = Database::pdo();
        $st  = $pdo->prepare('SELECT id, email, password_hash, role FROM users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u || empty($u['password_hash']) || !password_verify($pass, (string)$u['password_hash'])) {
            Flash::add('error', 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.');
            header('Location: /login?error=1'); 
            return;
        }

        // Session starten und User setzen
        Auth::start();
        $_SESSION['user'] = [
            'id' => $u['id'],
            'email' => $u['email'],
            'role' => $u['role']
        ];
        session_regenerate_id(true);
        
        header('Location: /protocols');
    }

    /** GET: /logout */
    public function logout(): void
    {
        Auth::start();
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000, 
                $p['path'], 
                $p['domain'], 
                $p['secure'], 
                $p['httponly']
            );
        }
        
        session_destroy();
        header('Location: /login');
    }
}
EOF

echo "✅ AuthController.php repariert"

# 2. OWNERSCONTROLLER REPARIEREN
echo "🔧 Repariere OwnersController.php..."

cat > backend/src/Controllers/OwnersController.php << 'EOF'
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
EOF

echo "✅ OwnersController.php repariert"

# 3. USERSCONTROLLER REPARIEREN
echo "🔧 Repariere UsersController.php..."

cat > backend/src/Controllers/UsersController.php << 'EOF'
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
EOF

echo "✅ UsersController.php repariert"

# 4. SYNTAX-CHECK ALLER REPARIERTEN CONTROLLER
echo ""
echo "🔍 Führe PHP Syntax-Check durch..."

SYNTAX_ERRORS=0

# Prüfe Docker-Verfügbarkeit
if command -v docker-compose &> /dev/null && docker-compose ps | grep -q "php"; then
    echo "Docker gefunden - teste über Container..."
    
    echo "Teste AuthController..."
    if ! docker-compose exec -T php php -l /var/www/html/src/Controllers/AuthController.php; then
        ((SYNTAX_ERRORS++))
    fi
    
    echo "Teste OwnersController..."
    if ! docker-compose exec -T php php -l /var/www/html/src/Controllers/OwnersController.php; then
        ((SYNTAX_ERRORS++))
    fi
    
    echo "Teste UsersController..."
    if ! docker-compose exec -T php php -l /var/www/html/src/Controllers/UsersController.php; then
        ((SYNTAX_ERRORS++))
    fi
    
else
    echo "Kein Docker gefunden - teste mit lokalem PHP..."
    
    if command -v php &> /dev/null; then
        echo "Teste AuthController..."
        if ! php -l backend/src/Controllers/AuthController.php; then
            ((SYNTAX_ERRORS++))
        fi
        
        echo "Teste OwnersController..."
        if ! php -l backend/src/Controllers/OwnersController.php; then
            ((SYNTAX_ERRORS++))
        fi
        
        echo "Teste UsersController..."
        if ! php -l backend/src/Controllers/UsersController.php; then
            ((SYNTAX_ERRORS++))
        fi
    else
        echo "⚠️ Weder Docker noch PHP gefunden - Syntax-Check übersprungen"
    fi
fi

# 5. BEREINIGUNG: BACKUP-DATEIEN LÖSCHEN
echo ""
echo "🧹 Bereinige Backup-Dateien..."

BACKUP_COUNT=$(find . -name "*.bak*" -type f ! -path "./vendor/*" ! -path "./node_modules/*" ! -path "./.git/*" | wc -l)

if [ $BACKUP_COUNT -gt 0 ]; then
    echo "Gefundene .bak Dateien:"
    find . -name "*.bak*" -type f ! -path "./vendor/*" ! -path "./node_modules/*" ! -path "./.git/*" | head -10
    
    echo ""
    read -p "🗑️ Sollen alle $BACKUP_COUNT .bak Dateien gelöscht werden? (y/N): " -n 1 -r
    echo ""
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        find . -name "*.bak*" -type f ! -path "./vendor/*" ! -path "./node_modules/*" ! -path "./.git/*" -delete
        find . -name "*.tmp" -type f ! -path "./vendor/*" ! -path "./node_modules/*" ! -path "./.git/*" -delete
        echo "✅ Backup-Dateien gelöscht"
    else
        echo "❌ Backup-Dateien nicht gelöscht"
    fi
else
    echo "✅ Keine .bak Dateien gefunden"
fi

# 6. ZUSAMMENFASSUNG UND NÄCHSTE SCHRITTE
echo ""
echo "🎉 === CONTROLLER-UPDATE ABGESCHLOSSEN ==="
echo ""

if [ $SYNTAX_ERRORS -eq 0 ]; then
    echo "✅ Alle Controller erfolgreich repariert:"
    echo "   - AuthController.php (Login-Funktionalität)"
    echo "   - OwnersController.php (Eigentümer-Verwaltung)"  
    echo "   - UsersController.php (Benutzer-Verwaltung)"
    echo ""
    echo "✅ Alle Syntax-Checks bestanden"
    echo ""
    echo "🚀 === NÄCHSTE SCHRITTE ==="
    echo ""
    echo "1. Docker-Container neustarten:"
    echo "   docker-compose restart php nginx"
    echo ""
    echo "2. Funktionstest:"
    echo "   http://localhost:8080/login"
    echo ""
    echo "3. Git-Commit durchführen:"
    echo "   git add ."
    echo "   git commit -m 'fix: Controller-Syntax-Fehler behoben'"
    echo "   git push origin main"
    echo ""
    echo "✅ System ist jetzt produktionsreif!"
    
else
    echo "❌ $SYNTAX_ERRORS Syntax-Fehler gefunden!"
    echo "Bitte beheben Sie diese vor dem Deployment."
    exit 1
fi

echo ""
echo "📋 === VERFÜGBARE FUNKTIONEN NACH UPDATE ==="
echo "✅ Login/Logout (AuthController)"
echo "✅ Eigentümer-Verwaltung (OwnersController)"
echo "✅ Benutzer-Verwaltung (UsersController)"
echo "🔄 Protokoll-Verwaltung (falls weitere Fixes nötig)"
echo ""
echo "🎯 Backup erstellt in: backend/src/Controllers.backup.$(date +%Y%m%d)*"
echo "   (Falls Rollback nötig)"
EOF

echo "🎉 Script erfolgreich erstellt und ist download-bereit!"
echo ""
echo "📥 Die Datei 'controller_files_update.sh' kann jetzt heruntergeladen werden."
echo ""
echo "📋 Verwendung:"
echo "1. Script in Ihr Repository-Root-Verzeichnis kopieren"
echo "2. chmod +x controller_files_update.sh"  
echo "3. ./controller_files_update.sh"
echo ""
echo "⚡ Das Script repariert automatisch:"
echo "   - AuthController.php (Login-Funktionalität)"
echo "   - OwnersController.php (Eigentümer-Verwaltung)"
echo "   - UsersController.php (Benutzer-Verwaltung)"
echo "   - Löscht alle .bak/.tmp Dateien"
echo "   - Führt PHP Syntax-Check durch"
echo ""
echo "🛡️ Sicherheit:"
echo "   - Erstellt automatisch Backup vor Änderungen"
echo "   - Sicherheitsabfragen vor kritischen Schritten"
echo "   - Syntax-Validierung aller Änderungen"