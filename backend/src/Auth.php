<?php
declare(strict_types=1);

namespace App;

final class Auth
{
    public static function start(): void
    {
        // Wenn bereits aktiv: nichts tun
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Sicherer, existierender Pfad für Sessions
        $path = ini_get('session.save_path');
        if (!$path) {
            $path = '/tmp';
            // Nur ini_set wenn Headers noch nicht gesendet
            if (!headers_sent()) {
                ini_set('session.save_path', $path);
            }
        }

        // Wenn schon Header gesendet (z. B. durch versehentliche Ausgabe),
        // können wir Cookie-Parameter nicht mehr setzen → einfach starten.
        if (headers_sent()) {
            @session_start();
            return;
        }

        // Cookie-Parameter nur setzen, solange noch keine Session aktiv und Header nicht gesendet sind
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => false, // bei HTTPS => true
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function check(): bool
    {
        self::start();
        return isset($_SESSION['user']);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            // Kein Output vor Redirects!
            header('Location: /login');
            exit;
        }
    }

    public static function user(): ?array
    {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    public static function login(array $user): void
    {
        self::start();
        // Neue Session-ID nach Login (Fixation verhindern)
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role']
        ];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && ($u['role'] ?? '') === 'admin';
    }

    public static function isHausverwaltung(): bool
    {
        $u = self::user();
        return $u && ($u['role'] ?? '') === 'hausverwaltung';
    }

    public static function isEigentuemer(): bool
    {
        $u = self::user();
        return $u && ($u['role'] ?? '') === 'eigentuemer';
    }
}
