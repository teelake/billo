<?php

declare(strict_types=1);

define('BILLO_ROOT', dirname(__DIR__));

require BILLO_ROOT . '/app/error_bootstrap.php';
billo_setup_error_logging(BILLO_ROOT);

require BILLO_ROOT . '/app/bootstrap.php';
