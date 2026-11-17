<?php

namespace App\Domain\Integrations\Adapters;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Integrations\Contracts\ReceiptPrinterInterface;

class ThermalReceiptPrinter implements ReceiptPrinterInterface
{
    public function render80mmReceipt(?Bill $bill = null, ?PaymentReceipt $receipt = null): string
    {
        $clubName = config('club.name', config('app.name', 'Malabar Club'));
        $clubLocation = config('club.location', 'Vadakkencherry, Kerala');
        $date = ($bill?->created_at ?? $receipt?->created_at ?? now())->timezone(config('club.timezone', 'Asia/Kolkata'));
        $dateStr = $date->format('d/m/Y h:i A');

        if (!$bill && $receipt) {
            $amount = $receipt->amount_paise / 100.0;
            $tender = $receipt->tender_type->label();
            $customerName = 'Member';
            if ($receipt->customer) {
                $customerName = method_exists($receipt->customer, 'person') 
                    ? ($receipt->customer->person->full_name ?? ($receipt->customer->person->first_name . ' ' . $receipt->customer->person->last_name))
                    : ($receipt->customer->name ?? 'Member');
            }

            return <<<TXT
================================================
               {$clubName}
         {$clubLocation}
================================================
PREPAID WALLET RECHARGE RECEIPT
Receipt: {$receipt->receipt_number}
Date:    {$dateStr}
Customer: {$customerName}
Counter:  {$receipt->shift?->counter?->name}
------------------------------------------------
Description: Wallet Top-Up
Tender Type: {$tender}
Amount Added:                          ₹{$amount}
------------------------------------------------
Status: Confirmed Server Ledger Posting
================================================
         Thank you for your visit!
================================================
TXT;
        }

        $linesText = "";
        if ($bill) {
            foreach ($bill->lines as $line) {
                $qty = (float) $line->quantity;
                $unit = $line->unit_price_paise / 100.0;
                $lineGrossPaise = (int) ($line->gross_amount_paise ?: round($qty * $line->unit_price_paise));
                $lineDiscountPaise = (int) ($line->discount_amount_paise ?? 0);
                $lineGrossDisplay = $lineGrossPaise / 100.0;

                $linesText .= sprintf("%-20.20s %2.0f x %6.2f = %7.2f\n", $line->item_name, $qty, $unit, $lineGrossDisplay);

                if ($lineDiscountPaise > 0 || (float) ($line->free_quantity ?? 0) > 0) {
                    $freeQty = (float) ($line->free_quantity ?? 0);
                    $offerLabel = $line->promotion_name
                        ? substr($line->promotion_name, 0, 26)
                        : ($freeQty > 0 ? sprintf("%.0f Free Offer", $freeQty) : 'Promo Discount');
                    $linesText .= sprintf("  Offer: %-27.27s -%6.2f\n", $offerLabel, $lineDiscountPaise / 100.0);
                }
            }
        }

        $gross = number_format(($bill?->gross_amount_paise ?? 0) / 100.0, 2);
        $discountPaise = (int) ($bill?->discount_amount_paise ?? 0);
        $discountLine = $discountPaise > 0
            ? sprintf("Promo Discount:                       -₹%s\n", number_format($discountPaise / 100.0, 2))
            : "";
        $tax = number_format(($bill?->tax_amount_paise ?? 0) / 100.0, 2);
        $net = number_format(($bill?->net_amount_paise ?? 0) / 100.0, 2);
        $tender = $receipt ? $receipt->tender_type->label() : 'Paid';

        $customerName = 'Guest';
        if ($bill && $bill->customer) {
            $customerName = method_exists($bill->customer, 'person') 
                ? ($bill->customer->person->full_name ?? ($bill->customer->person->first_name . ' ' . $bill->customer->person->last_name))
                : ($bill->customer->name ?? 'Member');
        }

        $waiterLine = $bill?->waiter ? "Bearer:   {$bill->waiter->name} ({$bill->waiter->code})\n" : "";
        $tableLine = $bill?->table_number ? "Table:    {$bill->table_number}\n" : "";
        $guestLine = $bill?->accompanying_guest_name ? "Guest:    {$bill->accompanying_guest_name}\n" : "";
        $notesLine = $bill?->notes ? "Ref:      {$bill->notes}\n" : "";

        $tipPaise = (int) ($bill?->tip_amount_paise ?? 0);
        $tipLine = $tipPaise > 0
            ? sprintf("Staff Tip (Gratuity):                 ₹%s\n", number_format($tipPaise / 100.0, 2))
            : "";
        $totalPaidPaise = ($bill?->paid_amount_paise ?? 0) ?: (($bill?->net_amount_paise ?? 0) + $tipPaise);
        $totalPaidDisplay = number_format($totalPaidPaise / 100.0, 2);

        return <<<TXT
================================================
               {$clubName}
         {$clubLocation}
================================================
Receipt: {$bill?->bill_number}
Date:    {$dateStr}
Customer: {$customerName}
Counter:  {$bill?->shift?->counter?->name}
{$waiterLine}{$tableLine}{$guestLine}{$notesLine}------------------------------------------------
Item                  Qty    Price    Total(INR)
------------------------------------------------
{$linesText}------------------------------------------------
Gross Total:                           ₹{$gross}
{$discountLine}GST / Tax:                             ₹{$tax}
Net Payable:                           ₹{$net}
{$tipLine}Total Tendered:                        ₹{$totalPaidDisplay}
Tender: {$tender}
Status: Confirmed Paid
================================================
        Thank you for visiting!
================================================
TXT;
    }

    public function renderA4Document(?Bill $bill = null, ?PaymentReceipt $receipt = null): string
    {
        return $this->render80mmReceipt($bill, $receipt);
    }

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
    ): string {
        $clubName = config('club.name', config('app.name', 'Malabar Club'));
        $clubLocation = config('club.location', 'Vadakkencherry, Kerala');
        $date = now()->timezone(config('club.timezone', 'Asia/Kolkata'));
        $dateStr = $date->format('d/m/Y h:i A');

        $tableLine = $tableNumber ? "Table:    {$tableNumber}\n" : "Spot:     Bar / Counter\n";
        $waiterLine = $waiterName ? "Bearer:   {$waiterName}\n" : "";
        $guestLine = $accompanyingGuestName ? "Guest:    {$accompanyingGuestName}\n" : "";
        $tabLine = $tabNumber ? "Tab Ref:  {$tabNumber}\n" : "";
        $counterLine = $counterName ? "Counter:  {$counterName}\n" : "";

        $customerDisplay = $customerName;
        if ($customerIdentifier) {
            $customerDisplay .= " ({$customerIdentifier})";
        }

        $linesText = "";
        foreach ($cartItems as $item) {
            $name = (string) ($item['name'] ?? $item['item_name'] ?? 'Item');
            $qty = (float) ($item['quantity'] ?? 1);
            $unitPaise = (int) ($item['unit_price_paise'] ?? 0);
            $unitDisplay = $unitPaise / 100.0;
            $lineGross = ($qty * $unitPaise) / 100.0;

            $linesText .= sprintf("%-20.20s %2.0f x %6.2f = %7.2f\n", $name, $qty, $unitDisplay, $lineGross);

            $lineDiscountPaise = (int) ($item['discount_paise'] ?? 0);
            if ($lineDiscountPaise > 0) {
                $promoLabel = !empty($item['promotion_name']) ? substr($item['promotion_name'], 0, 24) : 'Promo Discount';
                $linesText .= sprintf("  Offer: %-25.25s -%6.2f\n", $promoLabel, $lineDiscountPaise / 100.0);
            }
        }

        $gross = number_format($grossPaise / 100.0, 2);
        $tax = number_format($taxPaise / 100.0, 2);
        $net = number_format($netPaise / 100.0, 2);

        $discountLine = "";
        if ($discountPaise > 0) {
            $discountLine = sprintf("Promo Discount:                       -₹%s\n", number_format($discountPaise / 100.0, 2));
        }

        return <<<TXT
================================================
               {$clubName}
         {$clubLocation}
================================================
            TABLE CHECK / PRE-BILL
           (Interim Bill for Table)
Date:    {$dateStr}
{$tabLine}{$tableLine}{$waiterLine}{$guestLine}Customer: {$customerDisplay}
{$counterLine}------------------------------------------------
Item                  Qty    Price    Total(INR)
------------------------------------------------
{$linesText}------------------------------------------------
Gross Total:                           ₹{$gross}
{$discountLine}GST / Taxes:                            ₹{$tax}
------------------------------------------------
TOTAL PAYABLE:                         ₹{$net}
================================================
PAYMENT & GRATUITY INSTRUCTION:
[ ] Prepaid Wallet / Smartcard
[ ] Cash
[ ] UPI / QR Code (GPay / PhonePe)

Add Bearer Tip (Gratuity): ₹ ______________

Member Signature:          ________________
================================================
  Please return this check with card / payment
               to your bearer.
================================================
TXT;
    }

    public function getStatus(): array
    {
        return [
            'driver' => 'browser_thermal',
            'label' => 'Standard Thermal 80mm / Browser Print Bridge',
            'configured' => true,
            'is_production_ready' => true,
        ];
    }
}
