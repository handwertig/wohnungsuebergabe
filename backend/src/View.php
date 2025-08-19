<?php
declare(strict_types=1);
namespace App;

final class View {
    public static function render(string $title, string $bodyHtml): void {
        header('Content-Type: text/html; charset=utf-8');
        $flashes = Flash::pull();
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>'.htmlspecialchars($title).'</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '</head><body>';
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-primary"><div class="container-fluid">';
        echo '<a class="navbar-brand" href="/protocols">Wohnungsübergabe</a>';
        echo '<div class="collapse navbar-collapse"><ul class="navbar-nav me-auto mb-2 mb-lg-0">';
        echo '<li class="nav-item"><a class="nav-link" href="/protocols">Protokolle</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/protocols/wizard/start">Neues Protokoll</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/settings">Einstellungen</a></li>';
        echo '</ul><span class="navbar-text"><a href="/logout" class="text-white">Logout</a></span>';
        echo '</div></div></nav>';
        echo '<main class="container py-4">';
        foreach ($flashes as $f) {
            $cls = ['success'=>'success','error'=>'danger','info'=>'info','warning'=>'warning'][$f['type']] ?? 'secondary';
            echo '<div class="alert alert-'.$cls.'">'.$f['message'].'</div>';
        }
        echo $bodyHtml.'</main>';
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo '</body></html>';
    }
}
