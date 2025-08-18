<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Database;
use App\Auth;
use PDO;

final class AuthController {
    public function loginForm(): void {
        header('Content-Type: text/html; charset=utf-8');
        $error = isset($_GET['error']);
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Login – Wohnungsübergabe</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '</head><body class="bg-light"><div class="container py-5" style="max-width:420px">';
        echo '<h1 class="h4 mb-4 text-center">Login</h1>';
        if ($error) {
            echo '<div class="alert alert-danger">Login fehlgeschlagen. Bitte Zugangsdaten prüfen.</div>';
        }
        echo '<form method="post" action="/login">';
        echo '<div class="mb-3"><label class="form-label">E-Mail</label><input required name="email" type="email" class="form-control"></div>';
        echo '<div class="mb-3"><label class="form-label">Passwort</label><input required name="password" type="password" class="form-control"></div>';
        echo '<button class="btn btn-primary w-100">Anmelden</button>';
        echo '</form></div></body></html>';
    }

    public function login(): void {
        // Niemals vorher etwas ausgeben!
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
    
        if ($email === '' || $pass === '') {
            header('Location: /login?error=1'); exit;
        }
    
        $pdo = \App\Database::pdo();
        $stmt = $pdo->prepare('SELECT id,email,password_hash,role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch(\PDO::FETCH_ASSOC);
    
        if ($u && password_verify($pass, $u['password_hash'])) {
            \App\Auth::login($u);
            header('Location: /dashboard'); exit;
        }
    
        // Fehl-Login → zurück mit Query-Flag
        header('Location: /login?error=1'); exit;
    }

    public function logout(): void {
        Auth::logout();
        header('Location: /login');
    }
}
