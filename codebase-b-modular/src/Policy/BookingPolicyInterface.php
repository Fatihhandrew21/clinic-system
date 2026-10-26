<?php
declare(strict_types=1);

namespace Clinic\Policy;

interface BookingPolicyInterface
{
    public function getMaxActiveBookings(): int;
}
