<?php
declare(strict_types=1);

/** Synchronizer-token CSRF protection plus an Origin / Fetch-Metadata check. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function rotate(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function isValid(mixed $submitted): bool
    {
        return is_string($submitted)
            && isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $submitted);
    }

    /**
     * Browsers send Origin on POST and on cross-origin fetches. If it is present it must be
     * one of the configured origins. If absent, fall back to Sec-Fetch-Site when sent.
     * Non-browser clients send neither; they still need a valid CSRF token and session.
     */
    public static function originIsTrusted(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($origin !== null) {
            return in_array(rtrim($origin, '/'), Config::allowedOrigins(), true);
        }
        $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? null;
        return $site === null || in_array($site, ['same-origin', 'none'], true);
    }
}
