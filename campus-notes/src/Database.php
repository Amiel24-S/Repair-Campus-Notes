<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = Config::dbDsn();
        [$user, $pass] = Config::dbCredentials();

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (str_starts_with($dsn, 'mysql:')) {
            // Use real server-side prepared statements, not emulated ones.
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
        }

        self::$pdo = new PDO($dsn, $user, $pass, $options);
        if (str_starts_with($dsn, 'sqlite:')) {
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }
        return self::$pdo;
    }
}
