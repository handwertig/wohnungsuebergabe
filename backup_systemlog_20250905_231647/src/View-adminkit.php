<?php
declare(strict_types=1);

namespace App;

/**
 * View-Helper für HTML-Rendering
 * AdminKit-inspirierte Version mit minimalen Schatten und clean Design
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
                'brand_primary' => '#3b82f6',  // AdminKit blue
                'brand_secondary' => '#6366f1', // AdminKit indigo
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
    
    <!-- System Fonts (AdminKit style) -->
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($settings['brand_primary'] ?? '#3b82f6') ?>;
            --secondary-color: <?= htmlspecialchars($settings['brand_secondary'] ?? '#6366f1') ?>;
        }
    </style>
    
    <!-- AdminKit Theme -->
    <link href="/assets/adminkit-theme.css" rel="stylesheet">
    
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
    <!-- AdminKit Layout für angemeldete Benutzer -->
    <div class="kt-app">
        <!-- Sidebar -->
        <aside class="kt-aside">
            <!-- Brand -->
            <div class="kt-brand">
                <?php if ($logo && is_file($logo)): ?>
                    <img src="/uploads/logo.<?= pathinfo($logo, PATHINFO_EXTENSION) ?>" 
                         alt="Logo" class="logo" style="width: 32px; height: 32px; border-radius: var(--adminkit-border-radius);">
                <?php else: ?>
                    <div class="logo">W</div>
                <?php endif; ?>
                <span>Wohnungsübergabe</span>
            </div>
            
            <!-- Navigation -->
            <nav class="kt-nav">
                <a href="/protocols" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/protocols') && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/wizard') ? 'active' : '' ?>">
                    <i class="bi bi-file-text icon"></i>
                    <span>Protokolle</span>
                </a>
                <a href="/protocols/wizard/start" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/wizard') ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle icon"></i>
                    <span>Neues Protokoll</span>
                </a>
                <a href="/stats" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/stats') ? 'active' : '' ?>">
                    <i class="bi bi-graph-up icon"></i>
                    <span>Statistiken</span>
                </a>
                <a href="/settings" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/settings') ? 'active' : '' ?>">
                    <i class="bi bi-gear icon"></i>
                    <span>Einstellungen</span>
                </a>
            </nav>
        </aside>
        
        <!-- Topbar -->
        <header class="kt-topbar">
            <!-- Mobile menu toggle -->
            <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                <i class="bi bi-list"></i>
            </button>
            
            <!-- Page title area -->
            <div class="flex-grow-1">
                <h1 class="h5 mb-0 fw-600"><?= htmlspecialchars($title) ?></h1>
            </div>
            
            <!-- Actions -->
            <div class="kt-actions">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/settings"><i class="bi bi-gear me-2"></i>Einstellungen</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="kt-main">
            <?php self::displayFlash(); ?>
            <?= $content ?>
        </main>
    </div>
    
    <!-- Mobile Sidebar Backdrop -->
    <div class="kt-aside-backdrop"></div>
    
    <?php else: ?>
    <!-- Auth Layout für nicht-angemeldete Benutzer -->
    <div class="auth-wrap">
        <div class="auth-card card">
            <div class="card-body">
                <?php if ($logo && is_file($logo)): ?>
                    <div class="text-center mb-4">
                        <img src="/uploads/logo.<?= pathinfo($logo, PATHINFO_EXTENSION) ?>" 
                             alt="Logo" style="height: 48px;">
                    </div>
                <?php else: ?>
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center gap-2">
                            <div style="width: 32px; height: 32px; background: var(--accent); border-radius: var(--adminkit-border-radius); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">W</div>
                            <h4 class="mb-0">Wohnungsübergabe</h4>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php self::displayFlash(); ?>
                <?= $content ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AdminKit Theme JS -->
    <script src="/assets/adminkit-theme.js"></script>
    
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
            'narrow' => 'container-sm',
            'wide' => 'container-xl', 
            'full' => 'container-fluid',
            default => 'container'
        };
        
        $wrappedContent = '<div class="' . $containerClass . '">' . $content . '</div>';
        self::render($title, $wrappedContent);
    }

    /**
     * Rendert eine Seite mit AdminKit Sidebar Layout
     */
    public static function renderWithSidebar(string $title, string $content, array $sidebarItems = []): void
    {
        // Für zukünftige Verwendung - erweiterte Sidebar-Funktionalität
        self::render($title, $content);
    }

    /**
     * Hilfsfunktion für AdminKit Card-Wrapper
     */
    public static function card(string $title, string $content, array $options = []): string
    {
        $headerClass = $options['header_class'] ?? '';
        $bodyClass = $options['body_class'] ?? '';
        $cardClass = $options['card_class'] ?? '';
        
        return '<div class="card ' . htmlspecialchars($cardClass) . '">' .
               '<div class="card-header ' . htmlspecialchars($headerClass) . '">' .
               '<h5 class="card-title mb-0">' . htmlspecialchars($title) . '</h5>' .
               '</div>' .
               '<div class="card-body ' . htmlspecialchars($bodyClass) . '">' .
               $content .
               '</div>' .
               '</div>';
    }

    /**
     * Hilfsfunktion für AdminKit Button
     */
    public static function button(string $text, array $options = []): string
    {
        $type = $options['type'] ?? 'primary';
        $size = $options['size'] ?? '';
        $href = $options['href'] ?? null;
        $onclick = $options['onclick'] ?? null;
        $icon = $options['icon'] ?? null;
        $class = $options['class'] ?? '';
        
        $btnClass = "btn btn-{$type}";
        if ($size) $btnClass .= " btn-{$size}";
        if ($class) $btnClass .= " {$class}";
        
        $iconHtml = $icon ? "<i class=\"bi bi-{$icon} me-1\"></i>" : '';
        $content = $iconHtml . htmlspecialchars($text);
        
        if ($href) {
            return "<a href=\"{$href}\" class=\"{$btnClass}\">{$content}</a>";
        } else {
            $onclickAttr = $onclick ? " onclick=\"{$onclick}\"" : '';
            return "<button type=\"button\" class=\"{$btnClass}\"{$onclickAttr}>{$content}</button>";
        }
    }

    /**
     * Hilfsfunktion für AdminKit Alert
     */
    public static function alert(string $message, string $type = 'info', bool $dismissible = true): string
    {
        $dismissClass = $dismissible ? ' alert-dismissible fade show' : '';
        $dismissButton = $dismissible ? 
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' : '';
        
        return "<div class=\"alert alert-{$type}{$dismissClass}\" role=\"alert\">" .
               htmlspecialchars($message) .
               $dismissButton .
               "</div>";
    }

    /**
     * Hilfsfunktion für AdminKit Badge
     */
    public static function badge(string $text, string $type = 'secondary'): string
    {
        return "<span class=\"badge bg-{$type}\">" . htmlspecialchars($text) . "</span>";
    }

    /**
     * Hilfsfunktion für AdminKit Breadcrumb
     */
    public static function breadcrumb(array $items): string
    {
        $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        
        foreach ($items as $index => $item) {
            $isLast = $index === count($items) - 1;
            
            if ($isLast) {
                $html .= '<li class="breadcrumb-item active">' . htmlspecialchars($item['text']) . '</li>';
            } else {
                $html .= '<li class="breadcrumb-item">';
                if (isset($item['href'])) {
                    $html .= '<a href="' . htmlspecialchars($item['href']) . '">' . htmlspecialchars($item['text']) . '</a>';
                } else {
                    $html .= htmlspecialchars($item['text']);
                }
                $html .= '</li>';
            }
        }
        
        $html .= '</ol></nav>';
        return $html;
    }
}
