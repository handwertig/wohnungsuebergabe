<?php
declare(strict_types=1);

namespace App\Controllers;

use App\View;
use App\Flash;
use App\Auth;
use App\Database;
use App\Csrf;
use App\SystemLogger;
use PDO;

final class AuthController
{
    /** GET: /login – AdminKit Demo Login Design */
    public function loginForm(): void
    {
        $flashes = Flash::pull();
        
        // Logo aus Einstellungen laden
        $logo = '';
        try {
            if (class_exists('App\Settings')) {
                $logo = \App\Settings::get('pdf_logo_path', '');
            }
        } catch (\Throwable $e) {
            $logo = '';
        }
        
        ob_start(); ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Anmelden – Wohnungsübergabe</title>
            
            <!-- Bootstrap CSS -->
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        </head>
        <body>
        
        <div class="d-flex flex-column min-vh-100">
            <div class="container-fluid d-flex flex-column">
                <div class="row vh-100">
                    <div class="col-sm-10 col-md-6 col-lg-5 col-xl-4 col-xxl-4 mx-auto d-table h-100">
                        <div class="d-table-cell align-middle">
                            
                            <div class="text-center mt-4">
                                <?php if ($logo && is_file($logo)): ?>
                                    <div class="mb-3">
                                        <img src="/logo" alt="Logo" style="height: 64px; max-width: 200px; object-fit: contain;">
                                    </div>
                                <?php else: ?>
                                    <div class="mb-3">
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <div style="width: 48px; height: 48px; background: #3b82f6; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.5rem;">W</div>
                                            <h3 class="mb-0 text-dark">Wohnungsübergabe</h3>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <h1 class="h2">Willkommen!</h1>
                                <p class="lead">Melden Sie sich in Ihrem Konto an, um fortzufahren</p>
                            </div>
                            
                            <div class="card">
                                <div class="card-body">
                                    <div class="m-sm-5">
                                        
                                        <?php foreach ($flashes as $f):
                                            $cls = ['success'=>'success','error'=>'danger','info'=>'info','warning'=>'warning'][$f['type']] ?? 'secondary'; ?>
                                            <div class="alert alert-<?= $cls ?> alert-dismissible">
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                <?= htmlspecialchars($f['message']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <form method="post" action="/login" novalidate>
                                            <?= Csrf::tokenField() ?>
                                            
                                            <div class="mb-4">
                                                <label class="form-label">E-Mail-Adresse</label>
                                                <input class="form-control form-control-lg" type="email" name="email" placeholder="Geben Sie Ihre E-Mail-Adresse ein" required autocomplete="username" />
                                            </div>
                                            
                                            <div class="mb-4">
                                                <label class="form-label">Passwort</label>
                                                <input class="form-control form-control-lg" type="password" name="password" placeholder="Geben Sie Ihr Passwort ein" required autocomplete="current-password" />
                                            </div>
                                            
                                            <div class="d-flex align-items-center mb-4">
                                                <div class="form-check align-items-center">
                                                    <input id="customControlInline" type="checkbox" class="form-check-input" value="remember-me" name="remember" checked>
                                                    <label class="form-check-label text-small" for="customControlInline">Angemeldet bleiben</label>
                                                </div>
                                                <div class="ms-auto">
                                                    <a href="/password/forgot" class="text-small">Passwort vergessen?</a>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-lg btn-primary">Anmelden</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mb-3 mt-4">
                                <p class="text-muted">
                                    Haben Sie noch kein Konto? <a href="/setup" class="text-decoration-none">Hier registrieren</a>
                                </p>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .vh-100 {
                min-height: 100vh;
            }
            
            .card {
                border: 0;
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
                border-radius: 0.5rem;
            }
            
            .btn-lg {
                border-radius: 0.375rem;
                padding: 0.75rem 1.5rem;
                font-size: 1.125rem;
            }
            
            .form-control-lg {
                border-radius: 0.375rem;
                padding: 0.75rem 1rem;
                font-size: 1.125rem;
            }
            
            .text-small {
                font-size: 0.875rem;
            }
            
            body {
                background-color: #f8f9fa;
            }
        </style>
        
        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        echo $html;
    }

    /** POST: /login - mit CSRF-Validation */
    public function login(): void
    {
        // CSRF-Token validieren
        Csrf::requireValidToken();
        
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        
        if ($email === '' || $pass === '') {
            Flash::add('error', 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.');
            header('Location: /login?error=1'); 
            exit;
        }
        
        $pdo = Database::pdo();
        $st  = $pdo->prepare('SELECT id, email, password_hash, role FROM users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u || empty($u['password_hash']) || !password_verify($pass, (string)$u['password_hash'])) {
            // Login fehlgeschlagen - SystemLogger
            SystemLogger::log('login', "Login fehlgeschlagen für '$email'", null, null, ['success' => false]);
            
            Flash::add('error', 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.');
            header('Location: /login?error=1'); 
            exit;
        }

        // Session starten und User setzen
        Auth::start();
        $_SESSION['user'] = [
            'id' => $u['id'],
            'email' => $u['email'],
            'role' => $u['role']
        ];
        session_regenerate_id(true);
        
        // Login erfolgreich - SystemLogger
        SystemLogger::logLogin($email);
        
        Flash::add('success', 'Erfolgreich angemeldet.');
        header('Location: /protocols');
        exit;
    }

    /** GET: /logout */
    public function logout(): void
    {
        Auth::start();
        
        // User für Logging ermitteln (vor Session-Zerstörung)
        $user = Auth::user();
        $userEmail = $user['email'] ?? 'unknown';
        
        // CSRF-Tokens bereinigen
        Csrf::cleanup();
        
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
        
        // Logout protokollieren
        SystemLogger::logLogout($userEmail);
        
        Flash::add('info', 'Sie wurden abgemeldet.');
        header('Location: /login');
        exit;
    }
}
