<?php
declare(strict_types=1);

/**
 * Session lifecycle: hardened cookie settings, idle timeout,
 * ID regeneration on login and full destruction on logout.
 */
final class Session
{
    private const IDLE_TIMEOUT_SECONDS = 1800;
    private const NAME = 'campus_notes_sid';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');   // reject uninitialised (attacker-chosen) IDs
        ini_set('session.use_only_cookies', '1');  // never accept IDs from the URL
        ini_set('session.use_trans_sid', '0');
        session_name(self::NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Config::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();

        $now = time();
        if (isset($_SESSION['last_seen']) && $now - (int) $_SESSION['last_seen'] > self::IDLE_TIMEOUT_SECONDS) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['last_seen'] = $now;
    }

    /** Call only after the password has been verified. */
    public static function login(int $userId): void
    {
        session_regenerate_id(true);          // prevents session fixation
        $_SESSION = [
            'user_id' => $userId,
            'last_seen' => time(),
        ];
        Csrf::rotate();
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
            session_destroy();
        }
    }

    public static function flash(string $type, string $text): void
    {
        $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    }

    public static function takeFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($f) ? $f : null;
    }
}
