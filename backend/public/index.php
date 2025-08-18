<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) { Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load(); }

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\OwnersController;
use App\Controllers\ManagersController;
use App\Controllers\ObjectsController;
use App\Controllers\UnitsController;
use App\Controllers\UsersController;
use App\Controllers\PasswordController;
use App\Controllers\ProtocolsController;
use App\Auth;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

switch ($path) {
    case '/':
    case '/health': (new HomeController())->index(); break;

    case '/login':
        if ($_SERVER['REQUEST_METHOD']==='GET') { (new AuthController())->loginForm(); }
        else { (new AuthController())->login(); }
        break;
    case '/logout': (new AuthController())->logout(); break;
    case '/dashboard': Auth::requireAuth(); (new DashboardController())->index(); break;

    case '/owners': Auth::requireAuth(); (new OwnersController())->index(); break;
    case '/owners/new': Auth::requireAuth(); (new OwnersController())->form(); break;
    case '/owners/edit': Auth::requireAuth(); (new OwnersController())->form(); break;
    case '/owners/save': Auth::requireAuth(); (new OwnersController())->save(); break;
    case '/owners/delete': Auth::requireAuth(); (new OwnersController())->delete(); break;

    case '/managers': Auth::requireAuth(); (new ManagersController())->index(); break;
    case '/managers/new':
    case '/managers/edit': Auth::requireAuth(); (new ManagersController())->form(); break;
    case '/managers/save': Auth::requireAuth(); (new ManagersController())->save(); break;
    case '/managers/delete': Auth::requireAuth(); (new ManagersController())->delete(); break;

    case '/objects': Auth::requireAuth(); (new ObjectsController())->index(); break;
    case '/objects/new':
    case '/objects/edit': Auth::requireAuth(); (new ObjectsController())->form(); break;
    case '/objects/save': Auth::requireAuth(); (new ObjectsController())->save(); break;
    case '/objects/delete': Auth::requireAuth(); (new ObjectsController())->delete(); break;

    case '/units': Auth::requireAuth(); (new UnitsController())->index(); break;
    case '/units/new':
    case '/units/edit': Auth::requireAuth(); (new UnitsController())->form(); break;
    case '/units/save': Auth::requireAuth(); (new UnitsController())->save(); break;
    case '/units/delete': Auth::requireAuth(); (new UnitsController())->delete(); break;

    case '/users': Auth::requireAuth(); (new UsersController())->index(); break;
    case '/users/new':
    case '/users/edit': Auth::requireAuth(); (new UsersController())->form(); break;
    case '/users/save': Auth::requireAuth(); (new UsersController())->save(); break;
    case '/users/delete': Auth::requireAuth(); (new UsersController())->delete(); break;

    case '/password/forgot':
        if ($_SERVER['REQUEST_METHOD']==='GET') { (new PasswordController())->forgotForm(); }
        else { (new PasswordController())->forgot(); }
        break;
    case '/password/reset':
        if ($_SERVER['REQUEST_METHOD']==='GET') { (new PasswordController())->resetForm(); }
        else { (new PasswordController())->reset(); }
        break;

    case '/protocols': Auth::requireAuth(); (new ProtocolsController())->index(); break;
    case '/protocols/new':
    case '/protocols/edit': Auth::requireAuth(); (new ProtocolsController())->form(); break;
    case '/protocols/save': Auth::requireAuth(); (new ProtocolsController())->save(); break;
    case '/protocols/delete': Auth::requireAuth(); (new ProtocolsController())->delete(); break;

    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'Not Found','path'=>$path]);
}
