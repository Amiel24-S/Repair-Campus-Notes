<?php
declare(strict_types=1);

/*
 * CLI only. Re-hashes the two synthetic lab accounts with password_hash(), for a database
 * that was created from the original MD5 starter schema.
 *   php tools/reset_seed_passwords.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

$seed = [
    'alex@example.test' => 'LabOnly-Alex-123',
    'sam@example.test' => 'LabOnly-Sam-456',
];

$db = Database::connection();
$stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
foreach ($seed as $email => $password) {
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
    echo $email . ': ' . ($stmt->rowCount() === 1 ? 'reset' : 'not found') . PHP_EOL;
}
