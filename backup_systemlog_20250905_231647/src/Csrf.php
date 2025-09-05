<?php
declare(strict_types=1);

namespace App;

/**
 * CSRF (Cross-Site Request Forgery) Protection
 * Generiert und validiert CSRF-Tokens für Formulare
 */
final class Csrf
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY = '_csrf_tokens';
    private const MAX_TOKENS = 10; // Begrenzt die Anzahl gleichzeitiger Tokens

    /**
     * Generiert ein neues CSRF-Token
     */
    public static function generateToken(): string
    {
        Auth::start(); // Session sicherstellen
        
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        
        // Token in Session speichern
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        
        $_SESSION[self::SESSION_KEY][$token] = time();
        
        // Alte Tokens bereinigen (FIFO)
        if (count($_SESSION[self::SESSION_KEY]) > self::MAX_TOKENS) {
            $oldestToken = array_keys($_SESSION[self::SESSION_KEY])[0];
            unset($_SESSION[self::SESSION_KEY][$oldestToken]);
        }
        
        return $token;
    }

    /**
     * Validiert ein CSRF-Token
     */
    public static function validateToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        Auth::start();
        
        if (!isset($_SESSION[self::SESSION_KEY][$token])) {
            return false;
        }
        
        // Token-Alter prüfen (max. 1 Stunde)
        $tokenTime = $_SESSION[self::SESSION_KEY][$token];
        if (time() - $tokenTime > 3600) {
            unset($_SESSION[self::SESSION_KEY][$token]);
            return false;
        }
        
        // Token nach Verwendung entfernen (One-Time-Use)
        unset($_SESSION[self::SESSION_KEY][$token]);
        return true;
    }

    /**
     * Generiert HTML für CSRF-Token Hidden-Field
     */
    public static function tokenField(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Middleware: Validiert CSRF-Token für POST-Requests
     */
    public static function requireValidToken(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['_csrf_token'] ?? '';
            
            if (!self::validateToken($token)) {
                Flash::add('error', 'Sicherheitsfehler: Ungültiger CSRF-Token. Bitte versuchen Sie es erneut.');
                
                // Redirect zur Referrer-Seite oder Login
                $referer = $_SERVER['HTTP_REFERER'] ?? '/login';
                header('Location: ' . $referer);
                exit;
            }
        }
    }

    /**
     * Bereinigt abgelaufene Tokens (Maintenance)
     */
    public static function cleanup(): void
    {
        Auth::start();
        
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return;
        }
        
        $now = time();
        foreach ($_SESSION[self::SESSION_KEY] as $token => $time) {
            if ($now - $time > 3600) { // 1 Stunde
                unset($_SESSION[self::SESSION_KEY][$token]);
            }
        }
    }
}
