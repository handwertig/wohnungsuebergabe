<?php
declare(strict_types=1);

namespace App\Controllers;

class HomeController {
    public function index(): void {
        header('Content-Type: application/json');
        echo json_encode([
            'app' => 'wohnungsuebergabe',
            'status' => 'ok',
            'time' => date(DATE_ATOM),
        ]);
    }
}
