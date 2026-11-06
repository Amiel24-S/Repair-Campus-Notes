<?php
declare(strict_types=1);

final class Auth
{
    private ?array $current = null;
    private bool $loaded = false;

    public function __construct(private UserRepository $users) {}

    /** Returns the user on success, null on any failure (same response either way). */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);
        // Verify against a dummy hash when the user does not exist, so response time
        // does not reveal whether the email is registered.
        $hash = $user['password_hash'] ?? self::dummyHash();
        $ok = password_verify($password, $hash);
        if ($user === null || !$ok) {
            return null;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        return $user;
    }

    /** Loads the signed-in user from the database (role is never trusted from the session). */
    public function currentUser(): ?array
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $id = Session::userId();
            $this->current = $id !== null ? $this->users->findById($id) : null;
            if ($id !== null && $this->current === null) {
                Session::destroy(); // account no longer exists
            }
        }
        return $this->current;
    }

    private static function dummyHash(): string
    {
        static $hash = null;
        return $hash ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }
}
