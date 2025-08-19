<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth;
use App\View;

final class SettingsController {
    public function index(): void {
        Auth::requireAuth();
        $isAdmin = \App\Auth::isAdmin();

        $html = '<h1 class="h4 mb-3">Einstellungen</h1>';
        $html .= '<div class="row g-3">';
        $html .= '<div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body">';
        $html .= '<div class="fw-bold mb-2">Stammdaten</div>';
        $html .= '<div class="d-grid gap-2">';
        $html .= '<a class="btn btn-outline-secondary" href="/owners">Eigentümer</a>';
        $html .= '<a class="btn btn-outline-secondary" href="/managers">Hausverwaltungen</a>';
        $html .= '<a class="btn btn-outline-secondary" href="/objects">Objekte</a>';
        $html .= '<a class="btn btn-outline-secondary" href="/units">Wohneinheiten</a>';
        $html .= '</div></div></div></div>';

        $html .= '<div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body">';
        $html .= '<div class="fw-bold mb-2">System</div>';
        $html .= '<p class="text-muted small mb-2">SMTP, DocuSign, Rechtstexte & Branding folgen im nächsten Schritt.</p>';
        $html .= '</div></div></div>';

        if ($isAdmin) {
            $html .= '<div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body">';
            $html .= '<div class="fw-bold mb-2">Benutzer & Rollen</div>';
            $html .= '<a class="btn btn-outline-secondary" href="/users">Benutzer verwalten</a>';
            $html .= '</div></div></div>';
        }

        $html .= '</div>'; // row
        View::render('Einstellungen – Wohnungsübergabe', $html);
    }
}
