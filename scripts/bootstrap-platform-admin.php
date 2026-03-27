<?php

declare(strict_types=1);

/**
 * Create (or upgrade) a platform operator account: system-wide /system access after normal login.
 *
 * Run on the server once (or whenever you need to reset the bootstrap user):
 *   php scripts/bootstrap-platform-admin.php you@yourdomain.com
 *
 * With your own password (must meet app password rules):
 *   php scripts/bootstrap-platform-admin.php you@yourdomain.com --password='YourStr0ng!Pass'
 *
 * Omit --password to generate a one-time random password (printed to stdout only).
 *
 * Idempotent: if the email already exists, grants is_system_admin, ensures membership
 * in the internal org, and optional --password updates the hash.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$root = dirname(__DIR__);
define('BILLO_ROOT', $root);

require $root . '/app/Autoloader.php';
App\Autoloader::register($root . '/app');

$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Missing config/config.php — copy from config.example first.\n");
    exit(1);
}

/** @var array<string, mixed> $config */
$config = require $configFile;
if (!is_array($config)) {
    fwrite(STDERR, "Invalid config/config.php\n");
    exit(1);
}
App\Core\Config::load($root . '/config', $config);

use App\Core\Database;
use App\Support\PasswordRules;

$args = array_slice($argv, 1);
$email = '';
$password = null;
$explicitPassword = false;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--password=')) {
        $password = substr($arg, strlen('--password='));
        $explicitPassword = true;
        continue;
    }
    if ($email === '' && !str_starts_with($arg, '--')) {
        $email = $arg;
    }
}

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Usage: php scripts/bootstrap-platform-admin.php <email> [--password='…']\n");
    exit(1);
}

$emailNorm = strtolower(trim($email));

if (!$explicitPassword) {
    $password = sprintf(
        'Billo-%s-%s',
        bin2hex(random_bytes(4)),
        bin2hex(random_bytes(4))
    );
}

$pwdErr = PasswordRules::validate($password);
if ($pwdErr !== null) {
    fwrite(STDERR, "Password invalid: {$pwdErr}\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    fwrite(STDERR, "Could not hash password.\n");
    exit(1);
}

const PLATFORM_ORG_SLUG = 'billo-platform';
const PLATFORM_ORG_NAME = 'Billo (platform)';

$pdo = Database::pdo();

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT id FROM organizations WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => PLATFORM_ORG_SLUG]);
    $orgId = $stmt->fetchColumn();
    if ($orgId === false) {
        $stmt = $pdo->prepare('INSERT INTO organizations (name, slug) VALUES (:name, :slug)');
        $stmt->execute(['name' => PLATFORM_ORG_NAME, 'slug' => PLATFORM_ORG_SLUG]);
        $orgId = (int) $pdo->lastInsertId();
    } else {
        $orgId = (int) $orgId;
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $emailNorm]);
    $userId = $stmt->fetchColumn();

    if ($userId === false) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, name, is_system_admin, email_verified_at, active_organization_id)
             VALUES (:email, :password_hash, :name, 1, NOW(), :org_id)'
        );
        $stmt->execute([
            'email' => $emailNorm,
            'password_hash' => $hash,
            'name' => 'Platform operator',
            'org_id' => $orgId,
        ]);
        $userId = (int) $pdo->lastInsertId();
    } else {
        $userId = (int) $userId;
        $stmt = $pdo->prepare(
            'UPDATE users SET is_system_admin = 1, active_organization_id = :org_id, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $userId]);
        if ($explicitPassword) {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute(['h' => $hash, 'id' => $userId]);
        }
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO organization_members (organization_id, user_id, role) VALUES (:org_id, :user_id, \'owner\')'
    );
    $stmt->execute(['org_id' => $orgId, 'user_id' => $userId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Database error: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "\nPlatform operator ready.\n";
echo "  Email:    {$emailNorm}\n";
if (!$explicitPassword) {
    echo "  Password: {$password}\n";
    echo "\n(Save this password now; it was randomly generated and is not stored in plain text.)\n";
} else {
    echo "  Password: (the one you passed with --password)\n";
}
echo "\nSign in at your normal /login URL, then open /system for the platform dashboard.\n";
echo "Change the password after first login if you used a generated one.\n\n";
