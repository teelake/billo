<?php

declare(strict_types=1);

/**
 * Install early (from public/index.php before bootstrap). Sets php-error.log and basic handlers.
 */
function billo_setup_error_logging(string $projectRoot): void
{
    $logDir = $projectRoot . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/php-error.log';
    ini_set('log_errors', '1');
    ini_set('error_log', $logFile);

    if (PHP_SAPI !== 'cli') {
        error_reporting(E_ALL);
    }

    set_exception_handler(static function (\Throwable $e) use ($logFile): void {
        $line = sprintf(
            "[%s] Uncaught %s: %s in %s:%d\n%s\n",
            date('c'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        error_log($line, 3, $logFile);
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo 'An unexpected error occurred. If this persists, check storage/logs/php-error.log';
        exit(1);
    });

    register_shutdown_function(static function () use ($logFile): void {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        if (!in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $line = sprintf(
            "[%s] Fatal: %s in %s:%d\n",
            date('c'),
            $err['message'],
            $err['file'],
            $err['line']
        );
        error_log($line, 3, $logFile);
    });
}
