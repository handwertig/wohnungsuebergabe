<?php
declare(strict_types=1);

// Autoload & .env
require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
}

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\PasswordController;
use App\Controllers\ProtocolsController;
use App\Controllers\ProtocolWizardController;
use App\Controllers\SettingsController;
use App\Controllers\StatsController;
use App\Controllers\OwnersController;
use App\Controllers\ManagersController;
use App\Controllers\ObjectsController;
use App\Controllers\UsersController;
use App\Auth;

// Pfad ermitteln
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Routing
switch ($path) {
    // Root / Health
    case '/':
    case '/health':
        (new HomeController())->index();
        break;

    // Auth
    case '/login':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            (new AuthController())->loginForm();
        } else {
            (new AuthController())->login();
        }
        break;

    case '/logout':
        (new AuthController())->logout();
        break;

    // Passwort
    case '/password/forgot':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            (new PasswordController())->forgotForm();
        } else {
            (new PasswordController())->forgot();
        }
        break;

    case '/password/reset':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            (new PasswordController())->resetForm();
        } else {
            (new PasswordController())->reset();
        }
        break;

    // Protokolle
    case '/protocols':
        Auth::requireAuth();
        (new ProtocolsController())->index();
        break;

    case '/protocols/new':
        Auth::requireAuth();
        (new ProtocolsController())->form();
        break;

    case '/protocols/edit':
        Auth::requireAuth();
        (new ProtocolsController())->edit();
        break;

    case '/protocols/save':
        Auth::requireAuth();
        (new ProtocolsController())->save();
        break;

    case '/protocols/delete':
        Auth::requireAuth();
        (new ProtocolsController())->delete();
        break;

    case '/protocols/export':
        Auth::requireAuth();
        (new ProtocolsController())->export();
        break;

    // PDF & Versand
    case '/protocols/pdf':
        Auth::requireAuth();
        (new ProtocolsController())->pdf();   // ?protocol_id=&version=latest|N
        break;

    case '/protocols/send':
        Auth::requireAuth();
        (new ProtocolsController())->send();  // ?protocol_id=&to=owner|manager|tenant
        break;

    // Wizard
    case '/protocols/wizard/start':
        Auth::requireAuth();
        (new ProtocolWizardController())->start();
        break;

    case '/protocols/wizard':
        Auth::requireAuth();
        (new ProtocolWizardController())->step();
        break;

    case '/protocols/wizard/save':
        Auth::requireAuth();
        (new ProtocolWizardController())->save();
        break;

    case '/protocols/wizard/review':
        Auth::requireAuth();
        (new ProtocolWizardController())->review();
        break;

    case '/protocols/wizard/finish':
        Auth::requireAuth();
        (new ProtocolWizardController())->finish();
        break;

    // Statistiken
    case '/stats':
        Auth::requireAuth();
        (new StatsController())->index();
        break;

    // Einstellungen – Tabs
    case '/settings':
        Auth::requireAuth();
        (new SettingsController())->general();
        break;

    case '/settings/general/save':
        Auth::requireAuth();
        (new SettingsController())->generalSave();
        break;

    case '/settings/mail':
        Auth::requireAuth();
        (new SettingsController())->mail();
        break;

    case '/settings/mail/save':
        Auth::requireAuth();
        (new SettingsController())->mailSave();
        break;

    case '/settings/docusign':
        Auth::requireAuth();
        (new SettingsController())->docusign();
        break;

    case '/settings/docusign/save':
        Auth::requireAuth();
        (new SettingsController())->docusignSave();
        break;

    case '/settings/texts':
        Auth::requireAuth();
        (new SettingsController())->texts();
        break;

    case '/settings/texts/save':
        Auth::requireAuth();
        (new SettingsController())->textsSave();
        break;

    case '/settings/users':
        Auth::requireAuth();
        (new SettingsController())->users();
        break;

    case '/settings/users/save':
        Auth::requireAuth();
        (new SettingsController())->usersSave();
        break;

    case '/settings/branding':
        Auth::requireAuth();
        (new SettingsController())->branding();
        break;

    case '/settings/branding/save':
        Auth::requireAuth();
        (new SettingsController())->brandingSave();
        break;

    // NEUE ROUTE: Logo löschen
    case '/settings/branding/delete-logo':
        Auth::requireAuth();
        (new SettingsController())->brandingDeleteLogo();
        break;

    // Owners / Managers / Objects
    case '/owners':
        Auth::requireAuth();
        (new OwnersController())->index();
        break;

    case '/owners/new':
    case '/owners/edit':
        Auth::requireAuth();
        (new OwnersController())->form();
        break;

    case '/owners/save':
        Auth::requireAuth();
        (new OwnersController())->save();
        break;

    case '/owners/delete':
        Auth::requireAuth();
        (new OwnersController())->delete();
        break;

    case '/managers':
        Auth::requireAuth();
        (new ManagersController())->index();
        break;

    case '/managers/new':
    case '/managers/edit':
        Auth::requireAuth();
        (new ManagersController())->form();
        break;

    case '/managers/save':
        Auth::requireAuth();
        (new ManagersController())->save();
        break;

    case '/managers/delete':
        Auth::requireAuth();
        (new ManagersController())->delete();
        break;

    case '/objects':
        Auth::requireAuth();
        (new ObjectsController())->index();
        break;

    case '/objects/new':
    case '/objects/edit':
        Auth::requireAuth();
        (new ObjectsController())->form();
        break;

    case '/objects/save':
        Auth::requireAuth();
        (new ObjectsController())->save();
        break;

    case '/objects/delete':
        Auth::requireAuth();
        (new ObjectsController())->delete();
        break;

    // 404 JSON
    default:
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Not Found', 'path' => $path], JSON_UNESCAPED_UNICODE);
        break;
}
