<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\OwnersController;
use App\Controllers\ManagersController;
use App\Controllers\ObjectsController;
use App\Controllers\UnitsController;
use App\Auth;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

switch ($path) {
    case '/':
    case '/health':
        (new HomeController())->index(); break;

    case '/login':
        if ($_SERVER['REQUEST_METHOD']==='GET') { (new AuthController())->loginForm(); }
        else { (new AuthController())->login(); }
        break;

    case '/logout':
        (new AuthController())->logout(); break;

    case '/dashboard':
        Auth::requireAuth(); (new DashboardController())->index(); break;

    // Owners
    case '/owners':
        Auth::requireAuth(); (new OwnersController())->index(); break;
    case '/owners/new':
        Auth::requireAuth(); (new OwnersController())->form(); break;
    case '/owners/edit':
        Auth::requireAuth(); (new OwnersController())->form(); break;
    case '/owners/save':
        Auth::requireAuth(); (new OwnersController())->save(); break;

    // Managers
    case '/managers':
        Auth::requireAuth(); (new ManagersController())->index(); break;
    case '/managers/new':
    case '/managers/edit':
        Auth::requireAuth(); (new ManagersController())->form(); break;
    case '/managers/save':
        Auth::requireAuth(); (new ManagersController())->save(); break;

    // Objects
    case '/objects':
        Auth::requireAuth(); (new ObjectsController())->index(); break;
    case '/objects/new':
    case '/objects/edit':
        Auth::requireAuth(); (new ObjectsController())->form(); break;
    case '/objects/save':
        Auth::requireAuth(); (new ObjectsController())->save(); break;

    // Units
    case '/units':
        Auth::requireAuth(); (new UnitsController())->index(); break;
    case '/units/new':
    case '/units/edit':
        Auth::requireAuth(); (new UnitsController())->form(); break;
    case '/units/save':
        Auth::requireAuth(); (new UnitsController())->save(); break;

    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'Not Found','path'=>$path]);
}
