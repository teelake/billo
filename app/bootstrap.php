<?php

declare(strict_types=1);

use App\Autoloader;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\TeamController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

$root = dirname(__DIR__);
require $root . '/app/Autoloader.php';
Autoloader::register($root . '/app');

$config = require $root . '/config/config.php';
if (!is_array($config)) {
    throw new RuntimeException('Invalid configuration.');
}
Config::load($root . '/config', $config);

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
