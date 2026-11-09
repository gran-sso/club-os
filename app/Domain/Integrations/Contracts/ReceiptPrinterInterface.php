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
     * Device status.
     */
    public function getStatus(): array;
}
