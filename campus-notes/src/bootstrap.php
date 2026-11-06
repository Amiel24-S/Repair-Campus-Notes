<?php
declare(strict_types=1);

/*
 * Shared setup for every entry point: error handling, class loading, helpers.
 * Entry points define APP_CONTEXT ('web' or 'api') before requiring this file.
 */

// Never show errors to the client; log them server-side instead.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . basename(str_replace('\\', '/', $class)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    // Sanitised diagnostic: class, code and location only. No DSN, credentials, SQL or input.
    error_log(sprintf('[campus-notes] %s (code %s) at %s:%d', $e::class, (string) $e->getCode(), basename($e->getFile()), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (defined('APP_CONTEXT') && APP_CONTEXT === 'api') {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => 'Internal server error']);
    } else {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><p>Something went wrong. Please try again later.</p>';
    }
});
