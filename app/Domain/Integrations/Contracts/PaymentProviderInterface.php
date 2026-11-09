<?php

namespace App\Domain\Integrations\Contracts;

interface PaymentProviderInterface
{
    /**
     * Verify and confirm a payment attempt.
     */
    public function verifyPayment(string $providerReference, int $expectedAmountPaise, array $metadata = []): array;

    /**
     * Get integration status.
     */
    public function getStatus(): array;
}
