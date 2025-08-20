<?php
declare(strict_types=1);
namespace App;

final class View {
    public static function render(string $title, string $bodyHtml): void {
        header('Content-Type: text/html; charset=utf-8');
        $flashes = Flash::pull();
        $isLogged = Auth::user() !== null;

        echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>'.htmlspecialchars($title).'</title>';
        // Bootstrap + Icons + Fonts + Theme
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">';
        echo '<link href="/assets/flat-theme.css" rel="stylesheet">';
        echo '</head><body>';

        echo '<div class="kt-app">';
        // Aside
        echo '<aside class="kt-aside d-none d-lg-block d-print-none">';
        echo '<div class="kt-brand"><span class="logo"></span><span>Wohnungsübergabe</span></div>';
        echo '<ul class="kt-nav">';
        echo '<li><a href="/protocols" class="'.(self::isActive('/protocols')?'active':'').'"><i class="bi bi-files"></i><span>Protokolle</span></a></li>';
        echo '<li><a href="/protocols/wizard/start"><i class="bi bi-plus-circle"></i><span>Neues Protokoll</span></a></li>';
        echo '<li><a href="/settings" class="'.(self::isActive('/settings')?'active':'').'"><i class="bi bi-gear"></i><span>Einstellungen</span></a></li>';
        echo '</ul>';
        echo '</aside>';

        // Topbar
        echo '<header class="kt-topbar d-print-none">';
        echo '<button class="btn btn-ghost d-lg-none" id="kt-aside-toggle"><i class="bi bi-list"></i></button>';
        echo '<div class="ms-2 fw-semibold">'.$title.'</div>';
        echo '<div class="kt-actions">';
        echo '<button class="btn btn-ghost" id="kt-theme-toggle" title="Theme umschalten"><i class="bi bi-moon-stars"></i></button>';
        if ($isLogged) echo '<a class="btn btn-outline-danger" href="/logout"><i class="bi bi-box-arrow-right"></i> Logout</a>';
        echo '</div>';
        echo '</header>';

        // Main
        echo '<main class="kt-main">';
        foreach ($flashes as $f) {
            $cls = ['success'=>'success','error'=>'danger','info'=>'info','warning'=>'warning'][$f['type']] ?? 'secondary';
            echo '<div class="alert alert-'.$cls.' shadow-sm">'.htmlspecialchars($f['message']).'</div>';
        }
        echo $bodyHtml;
        echo '</main>';

        echo '</div>'; // .kt-app

        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo '<script src="/assets/keen-theme.js"></script>';
        echo '<script src="/assets/theme-toggle.js"></script>
</body></html>';
    }

    private static function isActive(string $path): bool {
        $cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return str_starts_with($cur, $path);
    }
}
