<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;

final class DoctorRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function search(?string $name, ?string $specialization, ?string $location): array
    {
        $sql = 'SELECT d.doctor_id, d.name, d.specialization, c.name AS clinic_name, c.location
                FROM doctors d JOIN clinics c ON c.clinic_id = d.clinic_id WHERE 1=1';
        $params = [];
        foreach ([
            ['d.name',           ':name', $name],
            ['d.specialization', ':spec', $specialization],
            ['c.location',       ':loc',  $location],
        ] as [$column, $param, $value]) {
            if ($value !== null && trim($value) !== '') {
                $sql .= " AND {$column} LIKE {$param}";
                $params[$param] = '%' . trim($value) . '%';
            }
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY d.name');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findProfile(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, c.name AS clinic_name, c.location, c.contact_number
             FROM doctors d JOIN clinics c ON c.clinic_id = d.clinic_id WHERE d.doctor_id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function belongsToClinic(int $doctorId, int $clinicId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM doctors WHERE doctor_id = :d AND clinic_id = :c');
        $stmt->execute([':d' => $doctorId, ':c' => $clinicId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function insert(string $name, string $specialization, int $clinicId): int
    {
        $this->pdo->prepare('INSERT INTO doctors (name, specialization, clinic_id) VALUES (:n,:s,:c)')
            ->execute([':n' => $name, ':s' => $specialization, ':c' => $clinicId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $specialization): void
    {
        $this->pdo->prepare('UPDATE doctors SET name=:n, specialization=:s WHERE doctor_id=:id')
            ->execute([':n' => $name, ':s' => $specialization, ':id' => $id]);
    }
}
