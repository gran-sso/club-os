<?php

namespace App\Domain\Integrations\Adapters;

use App\Domain\Integrations\Contracts\PaymentProviderInterface;
use InvalidArgumentException;

class ManualVerifiedPaymentProvider implements PaymentProviderInterface
{
    public function verifyPayment(string $providerReference, int $expectedAmountPaise, array $metadata = []): array
    {
        $ref = trim($providerReference);
        if (empty($ref)) {
            throw new InvalidArgumentException("A valid external bank or UPI transaction reference is required for digital verification.");
        }

        if ($expectedAmountPaise <= 0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        return [
            'status' => 'confirmed',
            'provider' => 'cashier_manual_verified',
            'reference' => $ref,
            'amount_paise' => $expectedAmountPaise,
            'confirmed_at' => now()->toIso8601String(),
            'verified_by_user_id' => $metadata['user_id'] ?? auth()->id(),
        ];
    }

    public function getStatus(): array
    {
        return [
            'driver' => 'manual_verified',
            'label' => 'Cashier-Verified Manual Digital/Terminal',
            'configured' => true,
            'is_production_ready' => true,
        ];
    }
}
