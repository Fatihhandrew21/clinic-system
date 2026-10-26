<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\NotFoundException;
use Clinic\Exception\ValidationException;
use Clinic\Repository\DoctorRepository;

final class DoctorService
{
    public function __construct(
        private readonly DoctorRepository $doctors,
        private readonly StaffAuthorizer $staff,
    ) {}

    public function search(?string $name = null, ?string $specialization = null, ?string $location = null): array
    {
        return $this->doctors->search($name, $specialization, $location);
    }

    public function getProfile(int $doctorId): array
    {
        return $this->doctors->findProfile($doctorId) ?? throw new NotFoundException('Doctor not found.');
    }

    /** Add (doctorId = null) or update a doctor of the staff member's own clinic. */
    public function save(int $staffId, string $name, string $specialization, ?int $doctorId = null): int
    {
        $clinicId = $this->staff->requireClinicId($staffId);
        if (trim($name) === '' || trim($specialization) === '') {
            throw new ValidationException('Name and specialization are required.');
        }
        if ($doctorId === null) {
            return $this->doctors->insert(trim($name), trim($specialization), $clinicId);
        }
        if (!$this->doctors->belongsToClinic($doctorId, $clinicId)) {
            throw new NotFoundException('Doctor not found in your clinic.');
        }
        $this->doctors->update($doctorId, trim($name), trim($specialization));
        return $doctorId;
    }
}
