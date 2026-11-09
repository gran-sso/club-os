<?php

namespace App\Domain\Billing\Services;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Services\GuestService;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\PaymentAllocation;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Refund;
use App\Domain\Billing\Models\Shift;
use App\Domain\Finance\Services\JournalService;
use App\Domain\Integrations\Contracts\ReceiptPrinterInterface;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\DuplicatePaymentException;
use App\Domain\Shared\Exceptions\InsufficientFundsException;
use App\Domain\Shared\Exceptions\ShiftNotOpenException;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingService
{
    public function __construct(
        protected WalletService $walletService,
        protected JournalService $journalService,
        protected GuestService $guestService,
        protected ReceiptPrinterInterface $printer,
        protected ?InventoryService $inventoryService = null
    ) {
        $this->inventoryService = $this->inventoryService ?? app(InventoryService::class);
    }

    /**
     * Create a draft sales bill.
     */
    public function createDraftBill(
        Shift $shift,
        Model $customer,
        string $departmentCode,
        array $items,
        string $idempotencyKey,
        ?string $notes = null,
        ?int $userId = null
    ): Bill {
        if (!$shift->isOpen()) {
            throw new ShiftNotOpenException("Cannot create bills on a closed shift.");
        }

        return DB::transaction(function () use ($shift, $customer, $departmentCode, $items, $idempotencyKey, $notes, $userId) {
            $payloadHash = hash('sha256', json_encode([
                'customer_type' => get_class($customer),
                'customer_id' => $customer->getKey(),
                'department_code' => $departmentCode,
                'items' => $items,
            ]));

            $existingBill = Bill::where('idempotency_key', $idempotencyKey)->first();
            if ($existingBill) {
                if ($existingBill->idempotency_hash && $existingBill->idempotency_hash !== $payloadHash) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' was already used with a different request payload.");
                }
                return $existingBill;
            }

            $yearMonth = now()->format('Ym');
            $count = Bill::where('bill_number', 'LIKE', "BILL-{$yearMonth}-%")->count() + 1;
            $billNumber = sprintf("BILL-%s-%05d", $yearMonth, $count);

            $grossPaise = 0;
            $taxPaise = 0;
            $lineItemsData = [];

            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $unitPrice = (int) $item['unit_price_paise'];
                $taxPercent = (float) ($item['tax_percent'] ?? 0);

                $lineSubtotal = (int) round($qty * $unitPrice);
                $lineTax = (int) round(($lineSubtotal * $taxPercent) / 100);
                $lineTotal = $lineSubtotal + $lineTax;

                $grossPaise += $lineSubtotal;
                $taxPaise += $lineTax;

                $lineItemsData[] = [
                    'department_code' => $item['department_code'] ?? $departmentCode,
                    'item_type' => $item['item_type'] ?? 'service',
                    'item_id' => $item['item_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'quantity' => $qty,
                    'unit_price_paise' => $unitPrice,
                    'tax_percent' => $taxPercent,
                    'tax_amount_paise' => $lineTax,
                    'line_total_paise' => $lineTotal,
                ];
            }

            $netPaise = $grossPaise + $taxPaise;

            $bill = Bill::create([
                'bill_number' => $billNumber,
                'shift_id' => $shift->id,
                'customer_type' => get_class($customer),
                'customer_id' => $customer->getKey(),
                'department_code' => $departmentCode,
                'status' => BillStatus::DRAFT,
                'gross_amount_paise' => $grossPaise,
                'discount_amount_paise' => 0,
                'tax_amount_paise' => $taxPaise,
                'net_amount_paise' => $netPaise,
                'paid_amount_paise' => 0,
                'idempotency_key' => $idempotencyKey,
                'idempotency_hash' => $payloadHash,
                'notes' => $notes,
                'created_by_user_id' => $userId ?? auth()->id() ?? $shift->user_id ?? 1,
            ]);

            foreach ($lineItemsData as $lineData) {
                $bill->lines()->create($lineData);
            }

            return $bill->fresh(['lines']);
        });
    }

    /**
     * Settle and fulfill a bill with confirmed payment tenders.
     * Enforces the non-negotiable no-credit policy: exact allocation required.
     *
     * @param array<array{tender_type: TenderType|string, amount_paise: int, reference?: string, wallet_account_id?: int}> $tenders
     */
    public function checkout(Bill $bill, array $tenders, ?int $userId = null): array
    {
        return DB::transaction(function () use ($bill, $tenders, $userId) {
            // Check if already paid
            if ($bill->isPaid()) {
                $receipts = PaymentReceipt::where('bill_id', $bill->id)->get();
                return [
                    'bill' => $bill,
                    'receipts' => $receipts,
                    'receipt_text' => $this->printer->render80mmReceipt($bill, $receipts->first()),
                ];
            }

            // Calculate total allocated tenders
            $totalTenderPaise = 0;
            foreach ($tenders as $tender) {
                $amount = (int) $tender['amount_paise'];
                if ($amount <= 0) {
                    throw new InvalidArgumentException("Tender amount must be greater than zero.");
                }
                $totalTenderPaise += $amount;
            }

            if ($totalTenderPaise !== $bill->net_amount_paise) {
                throw new InvalidArgumentException(
                    sprintf("Tender total (₹%.2f) does not match bill net amount (₹%.2f). No credit permitted.",
                    $totalTenderPaise / 100.0, $bill->net_amount_paise / 100.0)
                );
            }

            $createdReceipts = [];
            $journalDebitLines = [];
            $customer = $bill->customer;

            foreach ($tenders as $tender) {
                $tenderType = $tender['tender_type'] instanceof TenderType 
                    ? $tender['tender_type'] 
                    : TenderType::from($tender['tender_type']);
                $amount = (int) $tender['amount_paise'];
                $walletTxId = null;

                if ($tenderType === TenderType::PREPAID) {
                    // Resolve and validate wallet
                    $wallet = null;
                    if ($customer instanceof Member) {
                        $wallet = $customer->wallet;
                    } elseif ($customer instanceof Dependent) {
                        if (!$customer->can_spend_wallet) {
                            throw new UnauthorizedActionException("Dependent does not have authorized spending privileges on the member wallet.");
                        }
                        $wallet = $customer->member->wallet;
                    } elseif ($customer instanceof Guest && !empty($tender['wallet_account_id'])) {
                        $wallet = WalletAccount::find($tender['wallet_account_id']);
                    }

                    if (!$wallet) {
                        throw new InsufficientFundsException("No prepaid wallet account found for this customer.");
                    }

                    // Perform atomic debit with row-locking
                    $walletTx = $this->walletService->debit(
                        wallet: $wallet,
                        amountPaise: $amount,
                        referenceType: 'bill',
                        referenceId: $bill->id,
                        notes: "Payment for Bill {$bill->bill_number}",
                        userId: $userId
                    );
                    $walletTxId = $walletTx->id;

                    // Accounting: Debit Member Prepaid Advances Liability (2010)
                    $journalDebitLines[] = [
                        'account_code' => '2010',
                        'debit_paise' => $amount,
                        'narration' => "Prepaid redemption for {$bill->bill_number}",
                    ];
                } elseif ($tenderType === TenderType::CASH) {
                    // Accounting: Debit Cash in Till (1010)
                    $journalDebitLines[] = [
                        'account_code' => '1010',
                        'debit_paise' => $amount,
                        'narration' => "Cash collected for {$bill->bill_number}",
                    ];
                } elseif ($tenderType === TenderType::DIGITAL) {
                    // Accounting: Debit Bank Clearing (1020)
                    $journalDebitLines[] = [
                        'account_code' => '1020',
                        'debit_paise' => $amount,
                        'narration' => "Digital clearing for {$bill->bill_number}",
                    ];
                }

                $yearMonth = now()->format('Ym');
                $receiptCount = PaymentReceipt::where('receipt_number', 'LIKE', "RCP-{$yearMonth}-%")->count() + 1;
                $receiptNumber = sprintf("RCP-%s-%05d", $yearMonth, $receiptCount);

                $receipt = PaymentReceipt::create([
                    'receipt_number' => $receiptNumber,
                    'shift_id' => $bill->shift_id,
                    'bill_id' => $bill->id,
                    'wallet_transaction_id' => $walletTxId,
                    'customer_type' => get_class($customer),
                    'customer_id' => $customer->getKey(),
                    'tender_type' => $tenderType,
                    'amount_paise' => $amount,
                    'provider_name' => $tenderType === TenderType::DIGITAL ? 'upi' : 'cashier_manual',
                    'provider_reference' => $tender['reference'] ?? "RCP-{$receiptNumber}",
                    'provider_status' => 'confirmed',
                    'idempotency_key' => $bill->idempotency_key . '_' . $tenderType->value . '_' . uniqid(),
                    'confirmed_at' => now(),
                    'created_by_user_id' => $userId ?? auth()->id() ?? $bill->created_by_user_id ?? 1,
                ]);

                PaymentAllocation::create([
                    'payment_receipt_id' => $receipt->id,
                    'bill_id' => $bill->id,
                    'amount_paise' => $amount,
                ]);

                $createdReceipts[] = $receipt;
            }

            // Post balanced journal entry:
            // Debits: Cash / Bank / Prepaid liability (already in $journalDebitLines)
            // Credits: Department Revenue (4010 Guest / 4020 Bar / 4030 Room / 4040 Sports) + GST (2030)
            $revenueAccountCode = match ($bill->department_code) {
                'bar' => '4020',
                'card_room' => '4010',
                'accommodation' => '4030',
                'sports' => '4040',
                default => '4010',
            };

            $journalLines = $journalDebitLines;
            $journalLines[] = [
                'account_code' => $revenueAccountCode,
                'credit_paise' => $bill->gross_amount_paise,
                'narration' => "Sales revenue for {$bill->bill_number}",
                'department_code' => $bill->department_code,
            ];

            if ($bill->tax_amount_paise > 0) {
                $journalLines[] = [
                    'account_code' => '2030', // GST / Taxes Payable
                    'credit_paise' => $bill->tax_amount_paise,
                    'narration' => "GST collected on {$bill->bill_number}",
                ];
            }

            $billBusinessDateStr = $bill->business_date
                ? $bill->business_date->toDateString()
                : app(\App\Domain\Shared\Services\BusinessDateService::class)->getBusinessDate($bill->created_at ?? now());
            $billBusinessDate = \Carbon\Carbon::parse($billBusinessDateStr);

            $journal = $this->journalService->post(
                sourceType: 'bill',
                sourceId: $bill->id,
                description: "Sales settlement {$bill->bill_number}",
                lines: $journalLines,
                businessDate: $billBusinessDate,
                userId: $userId
            );

            // Update bill status
            $bill->update([
                'status' => BillStatus::PAID,
                'paid_amount_paise' => $bill->net_amount_paise,
                'business_date' => $billBusinessDateStr,
            ]);

            // Issue inventory stock for any mapped bar/store items
            foreach ($bill->lines as $line) {
                $this->inventoryService->issueStockForBillLine(
                    line: $line,
                    shiftId: $bill->shift_id,
                    userId: $userId
                );
            }

            // Activate any paid Guest Passes linked to this bill
            if ($customer instanceof Guest) {
                foreach ($bill->lines as $line) {
                    if (str_contains(strtolower($line->item_name), 'guest pass') || $line->department_code === 'card_room' || $line->department_code === 'bar') {
                        $this->guestService->activateGuestPass(
                            guest: $customer,
                            facilityCode: $bill->department_code === 'bar' ? 'bar' : 'bar_card_room',
                            billLineId: $line->id,
                            paidAmountPaise: $line->line_total_paise
                        );
                    }
                }
            }

            // Record outbox message for external sync
            OutboxMessage::create([
                'event_type' => 'billing.bill.paid',
                'aggregate_type' => 'Bill',
                'aggregate_id' => $bill->id,
                'payload' => [
                    'bill_number' => $bill->bill_number,
                    'net_amount_paise' => $bill->net_amount_paise,
                    'department_code' => $bill->department_code,
                    'customer_id' => $bill->customer_id,
                ],
                'status' => 'pending',
                'idempotency_key' => "outbox_bill_{$bill->id}",
            ]);

            return [
                'bill' => $bill->fresh(['lines']),
                'receipts' => $createdReceipts,
                'journal' => $journal,
                'receipt_text' => $this->printer->render80mmReceipt($bill, $createdReceipts[0] ?? null),
            ];
        });
    }

    /**
     * Authorised refund of a paid bill.
     */
    public function refund(
        Bill $bill,
        int $amountPaise,
        string $reason,
        User $approver,
        string $idempotencyKey,
        bool $returnStock = false
    ): Refund {
        if (!$bill->isPaid()) {
            throw new InvalidArgumentException("Only fully paid bills can be refunded.");
        }

        if ($amountPaise <= 0) {
            throw new InvalidArgumentException("Refund amount must be greater than zero.");
        }

        // Cashier cannot approve refunds - requires manager or admin
        if (!$approver->hasAnyRole(['admin', 'manager', 'Administrator', 'Manager'])) {
            throw new UnauthorizedActionException("Only a Manager or Administrator can approve refunds.");
        }

        return DB::transaction(function () use ($bill, $amountPaise, $reason, $approver, $idempotencyKey, $returnStock) {
            $priorRefunds = (int) Refund::where('bill_id', $bill->id)->sum('amount_paise');
            if (($priorRefunds + $amountPaise) > $bill->paid_amount_paise) {
                throw new InvalidArgumentException("Refund amount exceeds net refundable balance.");
            }

            $primaryReceipt = PaymentReceipt::where('bill_id', $bill->id)->first();
            $tenderType = $primaryReceipt ? $primaryReceipt->tender_type : TenderType::CASH;

            $yearMonth = now()->format('Ym');
            $count = Refund::where('refund_number', 'LIKE', "REF-{$yearMonth}-%")->count() + 1;
            $refundNumber = sprintf("REF-%s-%05d", $yearMonth, $count);

            $refund = Refund::create([
                'refund_number' => $refundNumber,
                'bill_id' => $bill->id,
                'payment_receipt_id' => $primaryReceipt?->id,
                'amount_paise' => $amountPaise,
                'tender_type' => $tenderType,
                'reason' => $reason,
                'approved_by_user_id' => $approver->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            // If refunded to prepaid wallet, restore wallet balance
            if ($tenderType === TenderType::PREPAID && $bill->customer instanceof Member) {
                $wallet = $bill->customer->wallet;
                if ($wallet) {
                    $wallet->increment('balance_paise', $amountPaise);
                    $wallet->transactions()->create([
                        'type' => 'refund',
                        'amount_paise' => $amountPaise,
                        'balance_after_paise' => $wallet->balance_paise,
                        'reference_type' => 'refund',
                        'reference_id' => $refund->id,
                        'notes' => "Refund for Bill {$bill->bill_number}: {$reason}",
                        'created_by_user_id' => $approver->id,
                    ]);
                }
            }

            // Post reversing journal entry
            // Debits: Revenue (4010/4020)
            // Credits: Cash (1010) or Prepaid Liability (2010)
            $revenueCode = match ($bill->department_code) {
                'bar' => '4020',
                default => '4010',
            };
            $assetOrLiabilityCode = $tenderType === TenderType::PREPAID ? '2010' : '1010';

            $this->journalService->post(
                sourceType: 'refund',
                sourceId: $refund->id,
                description: "Refund {$refund->refund_number} for {$bill->bill_number}",
                lines: [
                    [
                        'account_code' => $revenueCode,
                        'debit_paise' => $amountPaise,
                        'narration' => "Sales reversal on {$bill->bill_number}",
                    ],
                    [
                        'account_code' => $assetOrLiabilityCode,
                        'credit_paise' => $amountPaise,
                        'narration' => "Refund disbursed via {$tenderType->label()}",
                    ],
                ],
                userId: $approver->id
            );

            if (($priorRefunds + $amountPaise) >= $bill->paid_amount_paise) {
                $bill->update(['status' => BillStatus::REFUNDED]);
            }

            // Handle physical stock restoration if unopened return; otherwise consumed liquor is not fabricated into stock
            foreach ($bill->lines as $line) {
                $this->inventoryService->handleRefundStock($line, $returnStock, $approver->id);

                if ($line->department_code === 'food') {
                    app(\App\Domain\Contractor\Services\ContractorSettlementService::class)->handlePostSettlementRefund(
                        foodLine: $line,
                        refundAmountPaise: $line->line_total_paise,
                        reason: $reason,
                        userId: $approver->id
                    );
                }
            }

            return $refund;
        });
    }
}
