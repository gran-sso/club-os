<?php

namespace App\Domain\Integrations\Contracts;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\PaymentReceipt;

interface ReceiptPrinterInterface
{
    /**
     * Render 80mm thermal receipt content.
     */
    public function render80mmReceipt(?Bill $bill = null, ?PaymentReceipt $receipt = null): string;

    /**
     * Render full A4 invoice or statement layout.
     */
    public function renderA4Document(?Bill $bill = null, ?PaymentReceipt $receipt = null): string;

    /**
     * Render 80mm pre-settlement table check / interim pro-forma bill for table service.
     */
    public function render80mmTableCheck(
        array $cartItems,
        string $customerName,
        ?string $customerIdentifier = null,
        ?string $tableNumber = null,
        ?string $waiterName = null,
        ?string $counterName = null,
        int $grossPaise = 0,
        int $taxPaise = 0,
        int $netPaise = 0,
        int $discountPaise = 0,
        ?string $tabNumber = null,
        ?string $accompanyingGuestName = null
    ): string;

    /**
     * Render 80mm thermal shift closing and handover summary slip.
     */
    public function render80mmShiftHandoverSlip(array $details): string;

    /**
     * Device status.
     */
    public function getStatus(): array;
}
