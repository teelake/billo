<?php

declare(strict_types=1);

use App\Autoloader;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\ClientController;
use App\Controllers\TeamController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

$root = dirname(__DIR__);
require $root . '/app/Autoloader.php';
Autoloader::register($root . '/app');

$configDir = $root . '/config';
$configFile = $configDir . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Billo: configuration missing.\n\n"
        . "1. Copy the template: config.example/ → config/\n"
        . "   (PowerShell: Copy-Item -Recurse config.example config )\n"
        . "2. Edit config/config.php for your URL, DB, and mail.\n"
        . "3. Optional: copy config/local.example.php to config/local.php for secrets.\n";
    exit;
}

$config = require $configFile;
if (!is_array($config)) {
    throw new RuntimeException('Invalid configuration.');
}
Config::load($configDir, $config);

require $root . '/app/helpers.php';

Session::start();

$basePath = (string) Config::get('app.base_path', '');
$request = Request::fromGlobals($basePath);

$router = new Router();

$router->get('/', static fn () => (new HomeController())->index());
$router->get('/login', static fn () => (new AuthController($request))->showLogin());
$router->post('/login', static fn () => (new AuthController($request))->login());
$router->get('/signup', static fn () => (new AuthController($request))->showSignup());
$router->post('/signup', static fn () => (new AuthController($request))->signup());
$router->post('/logout', static fn () => (new AuthController($request))->logout());
$router->get('/dashboard', static fn () => (new DashboardController())->index());
$router->get('/clients', static fn () => (new ClientController($request))->index());
$router->get('/clients/create', static fn () => (new ClientController($request))->create());
$router->post('/clients', static fn () => (new ClientController($request))->store());
$router->get('/clients/edit', static fn () => (new ClientController($request))->edit());
$router->post('/clients/update', static fn () => (new ClientController($request))->update());
$router->post('/clients/delete', static fn () => (new ClientController($request))->destroy());
$router->get('/forgot-password', static fn () => (new AuthController($request))->showForgotPassword());
$router->post('/forgot-password', static fn () => (new AuthController($request))->forgotPassword());
$router->get('/reset-password', static fn () => (new AuthController($request))->showResetPassword());
$router->post('/reset-password', static fn () => (new AuthController($request))->resetPassword());
$router->get('/verify-email', static fn () => (new AuthController($request))->verifyEmail());
$router->post('/email/verification-notification', static fn () => (new AuthController($request))->resendVerificationEmail());
$router->get('/invitations/accept', static fn () => (new AuthController($request))->acceptInvitation());
$router->get('/team', static fn () => (new TeamController($request))->index());
$router->post('/team/invite', static fn () => (new TeamController($request))->invite());
$router->post('/team/invites/revoke', static fn () => (new TeamController($request))->revokeInvite());

$router->dispatch($request);
