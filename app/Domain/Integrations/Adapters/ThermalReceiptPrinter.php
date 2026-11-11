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

        $notesLine = $bill?->notes ? "Ref:      {$bill->notes}\n" : "";

        return <<<TXT
================================================
               {$clubName}
         {$clubLocation}
================================================
Receipt: {$bill?->bill_number}
Date:    {$dateStr}
Customer: {$customerName}
Counter:  {$bill?->shift?->counter?->name}
{$notesLine}------------------------------------------------
Item                  Qty    Price    Total(INR)
------------------------------------------------
{$linesText}------------------------------------------------
Gross Total:                           ₹{$gross}
{$discountLine}GST / Tax:                             ₹{$tax}
Net Payable:                           ₹{$net}
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
