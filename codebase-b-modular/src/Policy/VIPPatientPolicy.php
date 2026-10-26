<?php
declare(strict_types=1);

namespace Clinic\Policy;

final class VIPPatientPolicy implements BookingPolicyInterface
{
    public function getMaxActiveBookings(): int
    {
        return 10;
    }
}
