<?php

declare(strict_types=1);

use App\Autoloader;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\ClientController;
use App\Controllers\InvoiceController;
use App\Controllers\OrganizationController;
use App\Controllers\PayController;
use App\Controllers\PlatformLandingController;
use App\Controllers\PaystackWebhookController;
use App\Controllers\StripeWebhookController;
use App\Controllers\TeamController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Services\PlatformSettings;

$root = dirname(__DIR__);
if (!defined('BILLO_ROOT')) {
    define('BILLO_ROOT', $root);
}

$composerAutoload = $root . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

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
        . "3. Optional: copy config/local.example.php to config/local.php for secrets.\n"
        . "4. For PDF invoices: run composer install (see composer.json).\n";
    exit;
}

$config = require $configFile;
if (!is_array($config)) {
    throw new RuntimeException('Invalid configuration.');
}
Config::load($configDir, $config);
PlatformSettings::applyFromDatabase();

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
$router->get('/invoices', static fn () => (new InvoiceController($request))->index());
$router->get('/invoices/create', static fn () => (new InvoiceController($request))->create());
$router->post('/invoices', static fn () => (new InvoiceController($request))->store());
$router->get('/invoices/show', static fn () => (new InvoiceController($request))->show());
$router->get('/invoices/print', static fn () => (new InvoiceController($request))->printView());
$router->get('/invoices/pdf', static fn () => (new InvoiceController($request))->pdf());
$router->post('/invoices/email', static fn () => (new InvoiceController($request))->emailClient());
$router->get('/invoices/edit', static fn () => (new InvoiceController($request))->edit());
$router->post('/invoices/update', static fn () => (new InvoiceController($request))->update());
$router->post('/invoices/delete', static fn () => (new InvoiceController($request))->destroy());
$router->post('/invoices/send', static fn () => (new InvoiceController($request))->send());
$router->post('/invoices/mark-paid', static fn () => (new InvoiceController($request))->markPaid());
$router->post('/invoices/void', static fn () => (new InvoiceController($request))->markVoid());
$router->post('/invoices/credit-note', static fn () => (new InvoiceController($request))->startCreditNote());
$router->get('/pay', static fn () => (new PayController())->checkout());
$router->get('/pay/return', static fn () => (new PayController())->returnPage());
$router->get('/pay/cancel', static fn () => (new PayController())->cancelPage());
$router->post('/webhooks/paystack', static fn () => (new PaystackWebhookController())->handle());
$router->post('/webhooks/stripe', static fn () => (new StripeWebhookController())->handle());
$router->get('/platform/landing', static fn () => (new PlatformLandingController($request))->edit());
$router->post('/platform/landing', static fn () => (new PlatformLandingController($request))->save());
$router->get('/forgot-password', static fn () => (new AuthController($request))->showForgotPassword());
$router->post('/forgot-password', static fn () => (new AuthController($request))->forgotPassword());
$router->get('/reset-password', static fn () => (new AuthController($request))->showResetPassword());
$router->post('/reset-password', static fn () => (new AuthController($request))->resetPassword());
$router->get('/verify-email', static fn () => (new AuthController($request))->verifyEmail());
$router->post('/email/verification-notification', static fn () => (new AuthController($request))->resendVerificationEmail());
$router->get('/invitations/accept', static fn () => (new AuthController($request))->acceptInvitation());
$router->get('/organization', static fn () => (new OrganizationController($request))->edit());
$router->post('/organization', static fn () => (new OrganizationController($request))->update());
$router->get('/team', static fn () => (new TeamController($request))->index());
$router->post('/team/invite', static fn () => (new TeamController($request))->invite());
$router->post('/team/invites/revoke', static fn () => (new TeamController($request))->revokeInvite());

$router->dispatch($request);
