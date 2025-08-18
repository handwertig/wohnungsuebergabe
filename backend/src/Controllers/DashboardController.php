<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Auth;
use App\View;

final class DashboardController {
    public function index(): void {
        Auth::requireAuth();
        $body = '<div class="d-flex justify-content-between align-items-center mb-3">'
              . '<h1 class="h4 mb-0">Dashboard</h1></div>'
              . '<div class="row g-3">'
              . '<div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="fw-bold">Eigentümer</div><a href="/owners" class="stretched-link">verwalten</a></div></div></div>'
              . '<div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="fw-bold">Hausverwaltungen</div><a href="/managers" class="stretched-link">verwalten</a></div></div></div>'
              . '<div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="fw-bold">Objekte</div><a href="/objects" class="stretched-link">verwalten</a></div></div></div>'
              . '<div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="fw-bold">Wohneinheiten</div><a href="/units" class="stretched-link">verwalten</a></div></div></div>'
              . '</div>';
        \App\View::render('Dashboard – Wohnungsübergabe', $body);
    }
}
