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
            header('Location: /login?error=1'); return;
        }
        $pdo = Database::pdo();
        $st  = $pdo->prepare('SELECT id, email, password_hash, role FROM users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u || empty($u['password_hash']) || !password_verify($pass, (string)$u['password_hash'])) {
            Flash::add('error', 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.');
            header('Location: /login?error=1'); return;
        }

        Auth::start();
        $_SESSION['user'] = ['id'=>$u['id'],'email'=>$u['email'],'role'=>$u['role']];
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
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: /login');
    }
}
