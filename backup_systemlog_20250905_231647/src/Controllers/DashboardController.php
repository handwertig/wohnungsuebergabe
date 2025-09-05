<?php
declare(strict_types=1);

namespace App\Controllers;

use App\View;
use App\Database;

class DashboardController 
{
    public function index(): void 
    {
        // Beispiel-Dashboard mit AdminKit-Design
        $content = $this->renderDashboard();
        View::render('Dashboard', $content);
    }

    private function renderDashboard(): string 
    {
        ob_start();
        ?>
        
        <!-- AdminKit-style Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Dashboard</h1>
                <p class="text-muted">Willkommen in der Wohnungsübergabe-Verwaltung</p>
            </div>
            <div>
                <?= View::button('Neues Protokoll', [
                    'type' => 'primary',
                    'icon' => 'plus-circle',
                    'href' => '/protocols/wizard/start'
                ]) ?>
            </div>
        </div>

        <!-- Breadcrumb Navigation -->
        <?= View::breadcrumb([
            ['text' => 'Dashboard']
        ]) ?>

        <!-- Stats Cards Row (AdminKit-style) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded p-3">
                                    <i class="bi bi-file-text text-white"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <h3 class="mb-0">127</h3>
                                <p class="text-muted mb-0">Protokolle</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success rounded p-3">
                                    <i class="bi bi-check-circle text-white"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <h3 class="mb-0">98</h3>
                                <p class="text-muted mb-0">Abgeschlossen</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-warning rounded p-3">
                                    <i class="bi bi-clock text-white"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <h3 class="mb-0">23</h3>
                                <p class="text-muted mb-0">In Bearbeitung</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-info rounded p-3">
                                    <i class="bi bi-building text-white"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <h3 class="mb-0">45</h3>
                                <p class="text-muted mb-0">Objekte</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity (AdminKit-style) -->
        <div class="row">
            <div class="col-lg-8">
                <?= View::card('Letzte Aktivitäten', $this->renderRecentActivity()) ?>
            </div>
            
            <div class="col-lg-4">
                <?= View::card('Schnellaktionen', $this->renderQuickActions()) ?>
            </div>
        </div>

        <!-- Success Alert Example -->
        <?= View::alert('Das AdminKit-Theme wurde erfolgreich implementiert! Minimale Schatten, reduzierte border-radius und system-ui Schriften sind jetzt aktiv.', 'success') ?>

        <?php
        return ob_get_clean();
    }

    private function renderRecentActivity(): string 
    {
        ob_start();
        ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Protokoll</th>
                        <th>Objekt</th>
                        <th>Status</th>
                        <th>Datum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>PRO-2024-001</strong><br>
                            <small class="text-muted">Übergabe Wohnung 4A</small>
                        </td>
                        <td>Musterstraße 123</td>
                        <td><?= View::badge('Abgeschlossen', 'success') ?></td>
                        <td>
                            <small class="text-muted">Heute, 14:30</small>
                        </td>
                        <td>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong>PRO-2024-002</strong><br>
                            <small class="text-muted">Einzug Wohnung 2B</small>
                        </td>
                        <td>Beispielweg 456</td>
                        <td><?= View::badge('In Bearbeitung', 'warning') ?></td>
                        <td>
                            <small class="text-muted">Gestern, 16:15</small>
                        </td>
                        <td>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong>PRO-2024-003</strong><br>
                            <small class="text-muted">Auszug Wohnung 1C</small>
                        </td>
                        <td>Testplatz 789</td>
                        <td><?= View::badge('Entwurf', 'secondary') ?></td>
                        <td>
                            <small class="text-muted">02.09.2024</small>
                        </td>
                        <td>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="text-center mt-3">
            <?= View::button('Alle Protokolle anzeigen', [
                'type' => 'outline-primary',
                'href' => '/protocols'
            ]) ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderQuickActions(): string 
    {
        ob_start();
        ?>
        <div class="d-grid gap-2">
            <?= View::button('Neues Protokoll erstellen', [
                'type' => 'primary',
                'icon' => 'plus-circle',
                'href' => '/protocols/wizard/start',
                'class' => 'w-100'
            ]) ?>
            
            <?= View::button('Objekt hinzufügen', [
                'type' => 'outline-primary',
                'icon' => 'building',
                'href' => '/objects/create',
                'class' => 'w-100'
            ]) ?>
            
            <?= View::button('Statistiken anzeigen', [
                'type' => 'outline-secondary',
                'icon' => 'graph-up',
                'href' => '/stats',
                'class' => 'w-100'
            ]) ?>
            
            <?= View::button('Einstellungen', [
                'type' => 'outline-secondary',
                'icon' => 'gear',
                'href' => '/settings',
                'class' => 'w-100'
            ]) ?>
        </div>

        <hr class="my-4">

        <h6 class="mb-3">System-Status</h6>
        <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-sm">AdminKit Theme</span>
                <?= View::badge('Aktiv', 'success') ?>
            </div>
        </div>
        <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-sm">Minimale Schatten</span>
                <?= View::badge('Implementiert', 'success') ?>
            </div>
        </div>
        <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-sm">System-Schriften</span>
                <?= View::badge('Aktiv', 'success') ?>
            </div>
        </div>
        <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-sm">Border-Radius</span>
                <?= View::badge('6-12px', 'info') ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
