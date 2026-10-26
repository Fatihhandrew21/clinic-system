<?php
declare(strict_types=1);

namespace Clinic\Repository;

use PDO;

final class SlotRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** Row-locks the slot so concurrent bookings are serialised. */
    public function findByIdForUpdate(int $slotId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT slot_id, doctor_id, start_time, end_time, (start_time > NOW()) AS is_future
             FROM appointment_slots WHERE slot_id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $slotId]);
        return $stmt->fetch() ?: null;
    }

    public function findClinicIdForUpdate(int $slotId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.clinic_id FROM appointment_slots s JOIN doctors d ON d.doctor_id = s.doctor_id
             WHERE s.slot_id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $slotId]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (int)$v;
    }

    public function findFutureByDoctor(int $doctorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.slot_id, s.start_time, s.end_time,
                    NOT EXISTS (SELECT 1 FROM appointments a WHERE a.slot_id = s.slot_id AND a.status = 'Booked') AS is_available
             FROM appointment_slots s
             WHERE s.doctor_id = :id AND s.start_time > NOW() ORDER BY s.start_time"
        );
        $stmt->execute([':id' => $doctorId]);
        return $stmt->fetchAll();
    }

    public function doctorHasOverlap(int $doctorId, string $start, string $end): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM appointment_slots WHERE doctor_id = :d AND start_time < :e AND end_time > :s'
        );
        $stmt->execute([':d' => $doctorId, ':s' => $start, ':e' => $end]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function insert(int $doctorId, string $start, string $end): int
    {
        $this->pdo->prepare('INSERT INTO appointment_slots (doctor_id, start_time, end_time) VALUES (:d,:s,:e)')
            ->execute([':d' => $doctorId, ':s' => $start, ':e' => $end]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $slotId): void
    {
        $this->pdo->prepare('DELETE FROM appointment_slots WHERE slot_id = :id')->execute([':id' => $slotId]);
    }
}
