<?php
declare(strict_types=1);

namespace App;

/**
 * View-Helper für HTML-Rendering
 * Verbesserte Version mit professioneller Container-Struktur
 */
final class View
{
    /**
     * Rendert eine Seite mit Layout
     */
    public static function render(string $title, string $content): void
    {
        // Sichere Settings-Abfrage mit Fallbacks
        $settings = [];
        $logo = '';
        $customCss = '';
        
        try {
            if (class_exists('App\Settings')) {
                $settings = Settings::getAll();
                $logo = Settings::get('pdf_logo_path', '');
                $customCss = Settings::get('custom_css', '');
            }
        } catch (\Throwable $e) {
            $settings = [
                'pdf_logo_path' => '',
                'custom_css' => '',
                'brand_primary' => '#222357',
                'brand_secondary' => '#e22278',
            ];
            $logo = '';
            $customCss = '';
        }
        
        // CSRF-Token für neue Formulare vorbereiten
        $csrfToken = '';
        try {
            if (class_exists('App\Csrf')) {
                $csrfToken = Csrf::generateToken();
            }
        } catch (\Throwable $e) {
            $csrfToken = bin2hex(random_bytes(16));
        }
        
        // Auth-Status sicher prüfen
        $isAuthenticated = false;
        try {
            if (class_exists('App\Auth')) {
                $isAuthenticated = Auth::check();
            }
        } catch (\Throwable $e) {
            $isAuthenticated = false;
        }
        
        ob_start(); ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> – Wohnungsübergabe</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Poppins Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Enhanced Styles -->
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($settings['brand_primary'] ?? '#222357') ?>;
            --secondary-color: <?= htmlspecialchars($settings['brand_secondary'] ?? '#e22278') ?>;
            --navbar-height: 60px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        /* === NAVIGATION === */
        .navbar {
            min-height: var(--navbar-height);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-brand .logo {
            margin-right: 8px;
        }
        
        /* === MAIN CONTENT AREA === */
        .main-wrapper {
            min-height: calc(100vh - var(--navbar-height));
            padding-top: 0;
        }
        
        .content-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        /* Spezielle Container für verschiedene Seitentypen */
        .content-narrow {
            max-width: 800px;
        }
        
        .content-wide {
            max-width: 1600px;
        }
        
        .content-full {
            max-width: none;
            padding: 1rem;
        }
        
        /* === AUTH PAGES === */
        .auth-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .auth-card {
            max-width: 420px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: none;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }
        
        /* === BUTTONS === */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #1a1d4a;
            border-color: #1a1d4a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 35, 87, 0.3);
        }
        
        /* === CARDS === */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: box-shadow 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), #1a1d4a);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        /* === FORMS === */
        .form-control {
            border-radius: 8px;
            border: 1px solid #e1e5e9;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(34, 35, 87, 0.15);
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        /* === TABLES === */
        .table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        
        .table thead th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            font-weight: 600;
            color: #495057;
            padding: 1rem;
        }
        
        .table tbody td {
            border: none;
            padding: 1rem;
            vertical-align: middle;
        }
        
        .table tbody tr {
            border-bottom: 1px solid #f1f3f4;
        }
        
        .table tbody tr:last-child {
            border-bottom: none;
        }
        
        .table tbody tr:hover {
            background-color: rgba(34, 35, 87, 0.04);
        }
        
        /* === ALERTS === */
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f1aeb5);
            color: #721c24;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #a2d2ff);
            color: #0c5460;
        }
        
        /* === STICKY ACTIONS === */
        .kt-sticky-actions {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid #dee2e6;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1050;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
        }
        
        /* === CSRF PROTECTION === */
        .csrf-protected::after {
            content: "🔐";
            font-size: 12px;
            margin-left: 5px;
            opacity: 0.7;
            title: "CSRF-geschützt";
        }
        
        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .content-container {
                padding: 1rem 0.5rem;
            }
            
            .kt-sticky-actions {
                padding: 0.75rem 1rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .kt-sticky-actions > div {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .navbar-nav {
                background: rgba(34, 35, 87, 0.95);
                padding: 1rem;
                margin-top: 0.5rem;
                border-radius: 8px;
            }
        }
        
        /* === UTILITIES === */
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--primary-color), #1a1d4a);
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .border-primary {
            border-color: var(--primary-color) !important;
        }
        
        /* === PAGE SPECIFIC === */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        
        .page-header h1 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .stats-card {
            background: linear-gradient(135deg, white, #f8f9fa);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            color: #6c757d;
            font-weight: 500;
        }
    </style>
    
    <!-- Custom CSS -->
    <?php if ($customCss): ?>
    <style><?= $customCss ?></style>
    <?php endif; ?>
</head>
<body>
    <!-- CSRF-Token für JavaScript -->
    <?php if ($csrfToken): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <?php endif; ?>
    
    <?php if ($isAuthenticated): ?>
    <!-- Navigation für angemeldete Benutzer -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-gradient-primary">
        <div class="container-fluid">
            <?php if ($logo && is_file($logo)): ?>
                <img src="/uploads/logo.<?= pathinfo($logo, PATHINFO_EXTENSION) ?>" 
                     alt="Logo" class="navbar-brand logo" style="height: 40px;">
            <?php else: ?>
                <span class="navbar-brand">
                    <span class="logo" style="width:24px;height:24px;background:white;display:inline-block;border-radius:4px;opacity:0.9;"></span>
                    Wohnungsübergabe
                </span>
            <?php endif; ?>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="/protocols">
                        <i class="bi bi-file-text me-1"></i> Protokolle
                    </a>
                    <a class="nav-link" href="/protocols/wizard/start">
                        <i class="bi bi-plus-circle me-1"></i> Neues Protokoll
                    </a>
                    <a class="nav-link" href="/stats">
                        <i class="bi bi-graph-up me-1"></i> Statistiken
                    </a>
                    <a class="nav-link" href="/settings">
                        <i class="bi bi-gear me-1"></i> Einstellungen
                    </a>
                    <a class="nav-link" href="/logout">
                        <i class="bi bi-box-arrow-right me-1"></i> Abmelden
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content mit Container -->
    <div class="main-wrapper">
        <div class="content-container">
            <?php self::displayFlash(); ?>
            <?= $content ?>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Layout für nicht-angemeldete Benutzer (Login-Seite) -->
    <div class="main-wrapper">
        <?php self::displayFlash(); ?>
        <?= $content ?>
    </div>
    <?php endif; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- CSRF-Helper JavaScript -->
    <?php if ($csrfToken): ?>
    <script>
        // CSRF-Token für AJAX-Requests verfügbar machen
        window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        // CSRF-Token zu allen AJAX-Requests hinzufügen
        document.addEventListener('DOMContentLoaded', function() {
            // jQuery falls verfügbar
            if (window.jQuery) {
                jQuery.ajaxSetup({
                    beforeSend: function(xhr, settings) {
                        if (settings.type === 'POST' && !this.crossDomain && window.csrfToken) {
                            xhr.setRequestHeader('X-CSRF-Token', window.csrfToken);
                        }
                    }
                });
            }
            
            // Alle Formulare als CSRF-geschützt markieren
            document.querySelectorAll('form').forEach(function(form) {
                if (form.querySelector('input[name="_csrf_token"]')) {
                    form.classList.add('csrf-protected');
                }
            });
            
            // Smooth scrolling für Anker-Links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
        <?php
        $html = ob_get_clean();
        
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    /**
     * Sichere Flash-Message Anzeige
     */
    private static function displayFlash(): void
    {
        try {
            if (class_exists('App\Flash')) {
                Flash::display();
            }
        } catch (\Throwable $e) {
            // Fallback: Einfache Fehler-Anzeige
            if (isset($_SESSION['_simple_flash'])) {
                foreach ($_SESSION['_simple_flash'] as $msg) {
                    echo '<div class="alert alert-info">' . htmlspecialchars($msg) . '</div>';
                }
                unset($_SESSION['_simple_flash']);
            }
        }
    }

    /**
     * Generiert CSRF-Token Hidden-Field (robust)
     */
    public static function csrfField(): string
    {
        try {
            if (class_exists('App\Csrf')) {
                return Csrf::tokenField();
            }
        } catch (\Throwable $e) {
            // Fallback
        }
        
        // Einfacher Fallback-Token
        $token = bin2hex(random_bytes(16));
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Fallback für Flash-Messages
     */
    public static function addSimpleFlash(string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['_simple_flash'])) {
            $_SESSION['_simple_flash'] = [];
        }
        
        $_SESSION['_simple_flash'][] = $message;
    }

    /**
     * Rendert Content mit spezifischem Container-Typ
     */
    public static function renderWithContainer(string $title, string $content, string $containerType = 'default'): void
    {
        $containerClass = match($containerType) {
            'narrow' => 'content-container content-narrow',
            'wide' => 'content-container content-wide', 
            'full' => 'content-container content-full',
            default => 'content-container'
        };
        
        $wrappedContent = '<div class="' . $containerClass . '">' . $content . '</div>';
        self::render($title, $wrappedContent);
    }
}
