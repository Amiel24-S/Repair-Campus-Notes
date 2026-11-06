<?php
declare(strict_types=1);

final class UserRepository
{
    public function __construct(private PDO $db) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT id, email, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, email, role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** New accounts are always 'student'; the role is never taken from the request. */
    public function create(string $email, string $passwordHash): bool
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'student')");
            return $stmt->execute([$email, $passwordHash]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // duplicate email (unique constraint)
                return false;
            }
            throw $e;
        }
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);
    }
}
