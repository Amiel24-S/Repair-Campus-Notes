<?php
declare(strict_types=1);

/** Input validation. Each method returns [value|null, error|null]. */
final class Validator
{
    public const TITLE_MAX = 120;      // matches notes.title VARCHAR(120)
    public const BODY_MAX = 10000;
    public const PASSWORD_MIN = 12;
    public const PASSWORD_MAX = 128;   // bounds hashing cost; bcrypt also only uses 72 bytes

    public static function id(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v > 0 ? $v : null;
        }
        if (is_string($v)) {
            $id = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            return $id === false ? null : $id;
        }
        return null;
    }

    public static function email(mixed $v): array
    {
        if (!is_string($v)) {
            return [null, 'Enter a valid email address.'];
        }
        $v = trim($v);
        if (strlen($v) > 254 || filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
            return [null, 'Enter a valid email address.'];
        }
        return [strtolower($v), null];
    }

    public static function password(mixed $v): array
    {
        if (!is_string($v)) {
            return [null, 'Enter a password.'];
        }
        $len = mb_strlen($v, 'UTF-8');
        if ($len < self::PASSWORD_MIN || $len > self::PASSWORD_MAX) {
            return [null, sprintf('Password must be %d-%d characters.', self::PASSWORD_MIN, self::PASSWORD_MAX)];
        }
        return [$v, null];
    }

    /** @return array{0: ?array{title:string, body:string}, 1: ?string} */
    public static function note(mixed $title, mixed $body): array
    {
        if (!is_string($title) || !is_string($body)) {
            return [null, 'Title and body must be text.'];
        }
        if (!mb_check_encoding($title, 'UTF-8') || !mb_check_encoding($body, 'UTF-8')) {
            return [null, 'Title and body must be valid UTF-8.'];
        }
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || mb_strlen($title) > self::TITLE_MAX) {
            return [null, sprintf('Title is required (max %d characters).', self::TITLE_MAX)];
        }
        if ($body === '' || mb_strlen($body) > self::BODY_MAX) {
            return [null, sprintf('Body is required (max %d characters).', self::BODY_MAX)];
        }
        return [['title' => $title, 'body' => $body], null];
    }
}
