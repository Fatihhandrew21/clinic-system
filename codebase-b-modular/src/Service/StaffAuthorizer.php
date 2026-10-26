<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\AuthorizationException;
use Clinic\Repository\UserRepository;

final class StaffAuthorizer
{
    public function __construct(private readonly UserRepository $users) {}

    /** @return int the staff member's clinic_id */
    public function requireClinicId(int $staffId): int
    {
        $user = $this->users->findById($staffId);
        if ($user === null || $user['role'] !== 'Staff' || $user['clinic_id'] === null) {
            throw new AuthorizationException('Staff access required.');
        }
        return (int)$user['clinic_id'];
    }
}
