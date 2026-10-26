<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE user_id = :id', [':id' => $id]);
    }

    public function findByIdForUpdate(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE user_id = :id FOR UPDATE', [':id' => $id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE email = :e', [':e' => $email]);
    }

    public function insertPatient(string $name, string $email, string $passwordHash, string $phone): int
    {
        $this->pdo->prepare(
            "INSERT INTO users (name, email, password_hash, phone, role)
             VALUES (:n, :e, :h, :p, 'Patient')"
        )->execute([':n' => $name, ':e' => $email, ':h' => $passwordHash, ':p' => $phone]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateProfile(int $id, string $name, string $phone, ?string $passwordHash): void
    {
        if ($passwordHash !== null) {
            $this->pdo->prepare('UPDATE users SET name=:n, phone=:p, password_hash=:h WHERE user_id=:id')
                ->execute([':n' => $name, ':p' => $phone, ':h' => $passwordHash, ':id' => $id]);
            return;
        }
        $this->pdo->prepare('UPDATE users SET name=:n, phone=:p WHERE user_id=:id')
            ->execute([':n' => $name, ':p' => $phone, ':id' => $id]);
    }

    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }
}
