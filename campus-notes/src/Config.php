<?php
declare(strict_types=1);

/**
 * Reads configuration from environment variables only.
 * No credentials are hard-coded and there is no fallback to a root account.
 */
final class Config
{
    public static function dbDsn(): string
    {
        $dsn = getenv('LAB_DB_DSN');
        return ($dsn !== false && $dsn !== '')
            ? $dsn
            : 'mysql:host=127.0.0.1;port=3306;dbname=notes_lab;charset=utf8mb4';
    }

    /** @return array{0: ?string, 1: ?string} */
    public static function dbCredentials(): array
    {
        if (str_starts_with(self::dbDsn(), 'sqlite:')) {
            return [null, null]; // SQLite (used only for automated local tests) has no accounts.
        }
        $user = getenv('LAB_DB_USER');
        $pass = getenv('LAB_DB_PASSWORD');
        if ($user === false || $user === '' || $pass === false) {
            throw new RuntimeException('Database credentials are not configured (set LAB_DB_USER and LAB_DB_PASSWORD).');
        }
        return [$user, $pass];
    }

    /** Exact origins (scheme://host:port) allowed to send state-changing or API requests. */
    public static function allowedOrigins(): array
    {
        $raw = getenv('LAB_ALLOWED_ORIGINS');
        if ($raw === false || trim($raw) === '') {
            $raw = 'http://127.0.0.1:8000,http://localhost:8000';
        }
        return array_values(array_filter(array_map(
            static fn (string $o): string => rtrim(trim($o), '/'),
            explode(',', $raw)
        )));
    }

    /** Secure cookies whenever the request arrived over HTTPS, or when forced by env. */
    public static function cookieSecure(): bool
    {
        return getenv('LAB_COOKIE_SECURE') === '1'
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    }
}
