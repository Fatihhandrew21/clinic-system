<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Infrastructure\TransactionManager;
use Clinic\Repository\AppointmentRepository;
use Clinic\Repository\SlotRepository;
use Clinic\Repository\UserRepository;
use Clinic\Validation\BookingValidator;

/** Orchestrates the booking workflow; every step is delegated. */
final class AppointmentService
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly SlotRepository $slots,
        private readonly UserRepository $users,
        private readonly BookingValidator $validator,
        private readonly StaffAuthorizer $staff,
        private readonly TransactionManager $tx,
    ) {}

    /** @return int new appointment_id; throws a ClinicException subtype on rule violation */
    public function book(int $patientId, int $slotId): int
    {
        return $this->tx->transactional(function () use ($patientId, $slotId): int {
            $patient = $this->users->findByIdForUpdate($patientId);
            $this->validator->assertIsPatient($patient);

            $slot = $this->slots->findByIdForUpdate($slotId);
            $this->validator->assertSlotBookable($slot);
            $this->validator->assertSlotUnbooked($this->appointments->isSlotBooked($slotId));

            $this->validator->assertNoOverlap(
                $this->appointments->patientHasOverlap($patientId, $slot['start_time'], $slot['end_time'])
            );

            return $this->appointments->insertBooked($patientId, $slotId);
        });
    }

    public function cancel(int $patientId, int $appointmentId): void
    {
        $this->tx->transactional(function () use ($patientId, $appointmentId): void {
            $appt = $this->appointments->findDetailForUpdate($appointmentId);
            $this->validator->assertCancellable($appt, $patientId);
            $this->appointments->updateStatus($appointmentId, 'Cancelled');
        });
    }

    public function complete(int $staffId, int $appointmentId): void
    {
        $clinicId = $this->staff->requireClinicId($staffId);
        $this->tx->transactional(function () use ($clinicId, $appointmentId): void {
            $appt = $this->appointments->findDetailForUpdate($appointmentId);
            $this->validator->assertCompletable($appt, $clinicId);
            $this->appointments->updateStatus($appointmentId, 'Completed');
        });
    }

    public function cancelByStaff(int $staffId, int $appointmentId): void
    {
        $clinicId = $this->staff->requireClinicId($staffId);
        $this->tx->transactional(function () use ($clinicId, $appointmentId): void {
            $appt = $this->appointments->findDetailForUpdate($appointmentId);
            $this->validator->assertCancellableByStaff($appt, $clinicId);
            $this->appointments->updateStatus($appointmentId, 'Cancelled');
        });
    }

    public function listForClinic(int $staffId): array
    {
        return $this->appointments->findByClinic($this->staff->requireClinicId($staffId));
    }

    public function listForPatient(int $patientId): array
    {
        return $this->appointments->findByPatient($patientId);
    }
}
