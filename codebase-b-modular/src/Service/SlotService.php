<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\BookingException;
use Clinic\Exception\NotFoundException;
use Clinic\Exception\ValidationException;
use Clinic\Infrastructure\TransactionManager;
use Clinic\Repository\AppointmentRepository;
use Clinic\Repository\DoctorRepository;
use Clinic\Repository\SlotRepository;
use DateTimeImmutable;

final class SlotService
{
    public function __construct(
        private readonly SlotRepository $slots,
        private readonly AppointmentRepository $appointments,
        private readonly DoctorRepository $doctors,
        private readonly StaffAuthorizer $staff,
        private readonly TransactionManager $tx,
    ) {}

    public function listFutureSlots(int $doctorId): array
    {
        return $this->slots->findFutureByDoctor($doctorId);
    }

    public function createSlot(int $staffId, int $doctorId, string $start, string $end): int
    {
        $clinicId = $this->staff->requireClinicId($staffId);
        if (!$this->doctors->belongsToClinic($doctorId, $clinicId)) {
            throw new NotFoundException('Doctor not found in your clinic.');
        }
        $s = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start);
        $e = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end);
        if (!$s || !$e)                         { throw new ValidationException('Use the format YYYY-MM-DD HH:MM:SS.'); }
        if ($e <= $s)                           { throw new ValidationException('End time must be after start time.'); }
        if ($s <= new DateTimeImmutable('now')) { throw new ValidationException('Slots must be in the future.'); }
        if ($this->slots->doctorHasOverlap($doctorId, $start, $end)) {
            throw new ValidationException('Slot overlaps an existing slot for this doctor.');
        }
        return $this->slots->insert($doctorId, $start, $end);
    }

    public function deleteSlot(int $staffId, int $slotId): void
    {
        $clinicId = $this->staff->requireClinicId($staffId);
        $this->tx->transactional(function () use ($clinicId, $slotId): void {
            $slotClinic = $this->slots->findClinicIdForUpdate($slotId);
            if ($slotClinic === null || $slotClinic !== $clinicId) {
                throw new NotFoundException('Slot not found in your clinic.');
            }
            if ($this->appointments->countAllForSlot($slotId) > 0) {
                throw new BookingException('Slot has appointment history and cannot be deleted.');
            }
            $this->slots->delete($slotId);
        });
    }
}
