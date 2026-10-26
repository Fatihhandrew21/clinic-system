<?php
declare(strict_types=1);

namespace Clinic\Validation;

use Clinic\Exception\BookingException;
use Clinic\Exception\NotFoundException;

/** Pure rule checks: no SQL, no I/O. Facts are fetched by repositories and passed in. */
final class BookingValidator
{
    public function assertIsPatient(?array $user): void
    {
        if ($user === null) {
            throw new NotFoundException('Patient not found.');
        }
        if ($user['role'] !== 'Patient') {
            throw new BookingException('Only patients can book appointments.');
        }
    }

    public function assertSlotBookable(?array $slot): void
    {
        if ($slot === null) {
            throw new NotFoundException('Slot not found.');
        }
        if ((int)$slot['is_future'] !== 1) {
            throw new BookingException('This slot has already started or passed.');
        }
    }

    public function assertSlotUnbooked(bool $isBooked): void
    {
        if ($isBooked) {
            throw new BookingException('This slot is already booked.');
        }
    }

    public function assertNoOverlap(bool $overlaps): void
    {
        if ($overlaps) {
            throw new BookingException('This slot overlaps with one of your existing bookings.');
        }
    }

    public function assertCancellable(?array $appointment, int $patientId): void
    {
        if ($appointment === null || (int)$appointment['patient_id'] !== $patientId) {
            throw new NotFoundException('Appointment not found.');
        }
        if ($appointment['status'] !== 'Booked') {
            throw new BookingException('Only booked appointments can be cancelled.');
        }
        if ((int)$appointment['before_start'] !== 1) {
            throw new BookingException('Appointments cannot be cancelled after the start time.');
        }
    }

    public function assertCancellableByStaff(?array $appointment, int $staffClinicId): void
    {
        if ($appointment === null || (int)$appointment['clinic_id'] !== $staffClinicId) {
            throw new NotFoundException('Appointment not found in your clinic.');
        }
        if ($appointment['status'] !== 'Booked') {
            throw new BookingException('Only booked appointments can be cancelled.');
        }
        if ((int)$appointment['before_start'] !== 1) {
            throw new BookingException('Appointments cannot be cancelled after the start time.');
        }
    }

    public function assertCompletable(?array $appointment, int $staffClinicId): void
    {
        if ($appointment === null || (int)$appointment['clinic_id'] !== $staffClinicId) {
            throw new NotFoundException('Appointment not found in your clinic.');
        }
        if ($appointment['status'] !== 'Booked') {
            throw new BookingException('Only booked appointments can be completed.');
        }
        if ((int)$appointment['has_ended'] !== 1) {
            throw new BookingException('Appointment has not ended yet.');
        }
    }
}
