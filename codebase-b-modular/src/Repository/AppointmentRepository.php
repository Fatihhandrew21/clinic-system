<?php
declare(strict_types=1);

namespace Clinic\Repository;

use Clinic\Exception\BookingException;
use PDO;
use PDOException;

final class AppointmentRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function isSlotBooked(int $slotId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM appointments WHERE slot_id = :s AND status = 'Booked'");
        $stmt->execute([':s' => $slotId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function patientHasOverlap(int $patientId, string $start, string $end): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM appointments a JOIN appointment_slots s ON s.slot_id = a.slot_id
             WHERE a.patient_id = :p AND a.status = 'Booked' AND s.start_time < :e AND s.end_time > :s"
        );
        $stmt->execute([':p' => $patientId, ':s' => $start, ':e' => $end]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Counts ALL statuses (Booked, Cancelled, Completed). */
    public function countAllForSlot(int $slotId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM appointments WHERE slot_id = :s');
        $stmt->execute([':s' => $slotId]);
        return (int)$stmt->fetchColumn();
    }

    public function insertBooked(int $patientId, int $slotId): int
    {
        try {
            $this->pdo->prepare("INSERT INTO appointments (patient_id, slot_id, status) VALUES (:p,:s,'Booked')")
                ->execute([':p' => $patientId, ':s' => $slotId]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {   // uq_active_slot safety net
                throw new BookingException('This slot is already booked.', 0, $e);
            }
            throw $e;
        }
        return (int)$this->pdo->lastInsertId();
    }

    /** Appointment + slot timing + owning clinic, with DB-clock flags. */
    public function findDetailForUpdate(int $appointmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.appointment_id, a.patient_id, a.status, d.clinic_id,
                    (s.start_time > NOW()) AS before_start, (s.end_time <= NOW()) AS has_ended
             FROM appointments a
             JOIN appointment_slots s ON s.slot_id = a.slot_id
             JOIN doctors d ON d.doctor_id = s.doctor_id
             WHERE a.appointment_id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $appointmentId]);
        return $stmt->fetch() ?: null;
    }

    public function updateStatus(int $appointmentId, string $status): void
    {
        $this->pdo->prepare('UPDATE appointments SET status = :st WHERE appointment_id = :id')
            ->execute([':st' => $status, ':id' => $appointmentId]);
    }

    public function findByPatient(int $patientId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.appointment_id, a.status, s.start_time, s.end_time, d.name AS doctor_name, c.name AS clinic_name
             FROM appointments a
             JOIN appointment_slots s ON s.slot_id = a.slot_id
             JOIN doctors d ON d.doctor_id = s.doctor_id
             JOIN clinics c ON c.clinic_id = d.clinic_id
             WHERE a.patient_id = :p ORDER BY s.start_time DESC'
        );
        $stmt->execute([':p' => $patientId]);
        return $stmt->fetchAll();
    }

    public function findByClinic(int $clinicId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.appointment_id, a.status, a.created_at, s.start_time, s.end_time,
                    d.name AS doctor_name, u.name AS patient_name, u.phone AS patient_phone
             FROM appointments a
             JOIN appointment_slots s ON s.slot_id = a.slot_id
             JOIN doctors d ON d.doctor_id = s.doctor_id
             JOIN users u ON u.user_id = a.patient_id
             WHERE d.clinic_id = :c ORDER BY s.start_time DESC'
        );
        $stmt->execute([':c' => $clinicId]);
        return $stmt->fetchAll();
    }
}
