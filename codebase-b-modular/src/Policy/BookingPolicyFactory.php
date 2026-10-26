<?php
declare(strict_types=1);

namespace Clinic\Policy;

use Clinic\Exception\ValidationException;

/** Open for extension: register() a new tier without touching booking code. */
final class BookingPolicyFactory
{
    /** @var array<string, BookingPolicyInterface> */
    private array $policies = [];

    public function __construct()
    {
        $this->register('Standard', new StandardPatientPolicy());
        $this->register('VIP', new VIPPatientPolicy());
    }

    public function register(string $tier, BookingPolicyInterface $policy): void
    {
        $this->policies[$tier] = $policy;
    }

    public function forTier(string $tier): BookingPolicyInterface
    {
        return $this->policies[$tier] ?? throw new ValidationException("Unknown patient tier: {$tier}");
    }
}
