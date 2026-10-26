<?php
declare(strict_types=1);

namespace Clinic\Policy;

final class StandardPatientPolicy implements BookingPolicyInterface
{
    public function getMaxActiveBookings(): int
    {
        return 3;
    }
}
