<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

define('APP_ROOT', dirname(__DIR__));

$GLOBALS['config'] = require APP_ROOT . '/config/config.php';

// Erreurs journalisées dans logs/php-error.log (hors racine web), affichées seulement en dev
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/logs/php-error.log');
ini_set('display_errors', $GLOBALS['config']['app_env'] === 'dev' ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $ex): void {
    error_log(sprintf("%s: %s in %s:%d\n%s", get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine(), $ex->getTraceAsString()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo $GLOBALS['config']['app_env'] === 'dev'
        ? (string) $ex
        : "Une erreur est survenue. Elle a été enregistrée dans le journal de l'application.";
});

$GLOBALS['criteria'] = require __DIR__ . '/criteria.php';

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/scoring.php';
require __DIR__ . '/import.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/xlsx.php';
