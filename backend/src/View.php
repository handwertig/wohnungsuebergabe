<?php
declare(strict_types=1);
namespace App;

final class View {
    public static function render(string $title, string $bodyHtml): void {
        header('Content-Type: text/html; charset=utf-8');
        $flashes = Flash::pull();
        $isLogged = Auth::user() !== null;
        $layout = getenv('NAV_LAYOUT') ?: ''; // '' = Sidebar, 'top' = Navbar oben
        $isTop = ($layout === 'top');

        echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>'.htmlspecialchars($title).'</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">';
        echo '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">';
        echo '<link href="/assets/flat-theme.css" rel="stylesheet">';
        echo '</head><body>';

        if ($isTop) {
            // ----- Top-Navbar -----
            echo '<nav class="navbar navbar-expand-lg border-bottom bg-white">';
            echo '<div class="container-fluid">';
            echo '<a class="navbar-brand d-flex align-items-center gap-2" href="/protocols"><span class="logo" style="width:18px;height:18px;background:#222357;display:inline-block"></span><span>Wohnungsübergabe</span></a>';
            echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"><span class="navbar-toggler-icon"></span></button>';
            echo '<div class="collapse navbar-collapse" id="navMain">';
            echo '<ul class="navbar-nav me-auto">';
            echo '<li class="nav-item"><a class="nav-link'.(self::active('/protocols')?' active':'').'" href="/protocols"><i class="bi bi-files me-1"></i>Protokolle</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="/protocols/wizard/start"><i class="bi bi-plus-circle me-1"></i>Neues Protokoll</a></li>';
            echo '<li class="nav-item"><a class="nav-link'.(self::active('/settings')?' active':'').'" href="/settings"><i class="bi bi-gear me-1"></i>Einstellungen</a></li>';
            echo '</ul>';
            echo '<div class="d-flex ms-auto gap-2 align-items-center">';
            echo '<button class="btn btn-ghost" id="kt-theme-toggle" title="Theme umschalten"><i class="bi bi-moon-stars"></i></button>';
            if ($isLogged) echo '<a class="btn btn-outline-danger" href="/logout"><i class="bi bi-box-arrow-right"></i> Logout</a>';
            echo '</div></div></div></nav>';
            echo '<main class="container py-3">';
            foreach ($flashes as $f) {
                $cls = ['success'=>'success','error'=>'danger','info'=>'info','warning'=>'warning'][$f['type']] ?? 'secondary';
                echo '<div class="alert alert-'.$cls.'">'.htmlspecialchars($f['message']).'</div>';
            }
            echo $bodyHtml;
            echo '</main>';
        } else {
            // ----- Sidebar + Topbar (mobil einklappbar) -----
            echo '<div class="kt-app">';
            echo '<aside class="kt-aside d-print-none" id="kt-aside">';
            echo '  <div class="kt-brand"><span class="logo"></span><span>Wohnungsübergabe</span></div>';
            echo '  <ul class="kt-nav">';
            echo '    <li><a href="/protocols" class="'.(self::active('/protocols')?'active':'').'"><i class="bi bi-files"></i><span>Protokolle</span></a></li>';
            echo '    <li><a href="/protocols/wizard/start"><i class="bi bi-plus-circle"></i><span>Neues Protokoll</span></a></li>';
            echo '    <li><a href="/settings" class="'.(self::active('/settings')?'active':'').'"><i class="bi bi-gear"></i><span>Einstellungen</span></a></li>';
            echo '  </ul>';
            echo '</aside>';
            echo '<div class="kt-aside-backdrop d-lg-none" id="kt-aside-backdrop"></div>';

            echo '<header class="kt-topbar d-print-none">';
            echo '  <button class="btn btn-ghost d-lg-none" id="kt-aside-toggle" aria-label="Menü"><i class="bi bi-list"></i></button>';
            echo '  <div class="ms-2 fw-semibold">'.htmlspecialchars($title).'</div>';
            echo '  <div class="kt-actions ms-auto">';
            echo '    <button class="btn btn-ghost" id="kt-theme-toggle" title="Theme umschalten"><i class="bi bi-moon-stars"></i></button>';
            if ($isLogged) echo '    <a class="btn btn-outline-danger" href="/logout"><i class="bi bi-box-arrow-right"></i> Logout</a>';
            echo '  </div>';
            echo '</header>';

            echo '<main class="kt-main">';
            foreach ($flashes as $f) {
                $cls = ['success'=>'success','error'=>'danger','info'=>'info','warning'=>'warning'][$f['type']] ?? 'secondary';
                echo '<div class="alert alert-'.$cls.'">'.htmlspecialchars($f['message']).'</div>';
            }
            echo $bodyHtml;
            echo '</main>';
            echo '</div>';
        }

        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo '<script src="/assets/theme-toggle.js"></script>';
        echo '<script src="/assets/ui-shell.js"></script>';
        echo '</body></html>';
    }

    private static function active(string $path): bool {
        $cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return str_starts_with($cur, $path);
    }
}
