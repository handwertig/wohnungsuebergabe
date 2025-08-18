<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Load .env if exists
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    Dotenv\Dotenv::createImmutable(dirname($envPath))->load();
}

// Simple router
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

use App\Controllers\HomeController;

if ($path === '/' || $path === '/health') {
    (new HomeController())->index();
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Not Found']);
