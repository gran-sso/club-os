<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Billing\Models\BillLine;
use App\Domain\Finance\Services\JournalService;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\PurchaseReceipt;
use App\Domain\Inventory\Models\PurchaseReceiptLine;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Shared\Exceptions\InsufficientStockException;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function __construct(
        protected JournalService $journalService
    ) {}

    /**
     * Receive goods from supplier with moving weighted-average cost calculation.
     * Posts journal: Debit Inventory Stock (1030) + GST (2030), Credit Bank Clearing (1020).
     */
    public function receivePurchase(
        Supplier $supplier,
        string $invoiceNumber,
        string $invoiceDate,
        array $items,
        string $idempotencyKey,
        ?int $shiftId = null,
        ?int $userId = null
    ): PurchaseReceipt {
        return DB::transaction(function () use ($supplier, $invoiceNumber, $invoiceDate, $items, $idempotencyKey, $shiftId, $userId) {
            $existing = PurchaseReceipt::where('invoice_number', $invoiceNumber)
                ->where('supplier_id', $supplier->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $yearMonth = now()->format('Ym');
            $count = PurchaseReceipt::where('receipt_number', 'LIKE', "PUR-{$yearMonth}-%")->count() + 1;
            $receiptNumber = sprintf("PUR-%s-%05d", $yearMonth, $count);

            $totalGrossPaise = 0;
            $totalDiscountPaise = 0;
            $totalTaxPaise = 0;
            $linesData = [];

            foreach ($items as $itemData) {
                $qty = (float) $itemData['quantity'];
                $explicitGrossPaise = isset($itemData['line_gross_paise']) ? (int) $itemData['line_gross_paise'] : null;
                $rawUnitCostPaise = (int) ($itemData['unit_cost_paise'] ?? 0);
                $discountPaise = (int) ($itemData['discount_paise'] ?? 0);
                $taxPercent = (float) ($itemData['tax_percent'] ?? 0);
                $schemeNotes = $itemData['scheme_notes'] ?? null;

                if ($qty <= 0 || $rawUnitCostPaise < 0 || ($explicitGrossPaise !== null && $explicitGrossPaise < 0)) {
                    throw new InvalidArgumentException("Quantity must be positive and unit cost cannot be negative.");
                }

                $lineGrossBeforeDiscount = $explicitGrossPaise ?? (int) round($qty * $rawUnitCostPaise);
                if ($explicitGrossPaise !== null && $rawUnitCostPaise === 0) {
                    $rawUnitCostPaise = (int) round($lineGrossBeforeDiscount / $qty);
                }
                if ($discountPaise < 0 || $discountPaise > $lineGrossBeforeDiscount) {
                    throw new InvalidArgumentException("Supplier cash/trade discount cannot be negative or exceed gross consignment value.");
                }

                $lineNetGoodsCost = $lineGrossBeforeDiscount - $discountPaise;
                $effectiveUnitCostPaise = (int) round($lineNetGoodsCost / $qty);
                $lineTax = (int) round(($lineNetGoodsCost * $taxPercent) / 100);
                $lineTotal = $lineNetGoodsCost + $lineTax;

                $totalGrossPaise += $lineNetGoodsCost;
                $totalDiscountPaise += $discountPaise;
                $totalTaxPaise += $lineTax;

                $linesData[] = [
                    'inventory_item_id' => $itemData['inventory_item_id'],
                    'quantity' => $qty,
                    'unit_cost_paise' => $effectiveUnitCostPaise,
                    'gross_unit_cost_paise' => $rawUnitCostPaise,
                    'discount_paise' => $discountPaise,
                    'tax_percent' => $taxPercent,
                    'line_total_paise' => $lineTotal,
                    'scheme_notes' => $schemeNotes,
                    '_net_goods_cost_paise' => $lineNetGoodsCost,
                ];
            }

            $totalNetPaise = $totalGrossPaise + $totalTaxPaise;

            $userId = $userId ?? auth()->id() ?? \App\Models\User::value('id') ?? 1;

            $receipt = PurchaseReceipt::create([
                'receipt_number' => $receiptNumber,
                'supplier_id' => $supplier->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'total_gross_paise' => $totalGrossPaise,
                'total_discount_paise' => $totalDiscountPaise,
                'total_tax_paise' => $totalTaxPaise,
                'total_net_paise' => $totalNetPaise,
                'status' => 'received',
                'created_by_user_id' => $userId,
            ]);

            foreach ($linesData as $lData) {
                $netGoodsCostPaise = $lData['_net_goods_cost_paise'];
                unset($lData['_net_goods_cost_paise']);

                $receipt->lines()->create($lData);

                // Lock inventory item and recalculate moving weighted-average cost
                /** @var InventoryItem $item */
                $item = InventoryItem::where('id', $lData['inventory_item_id'])->lockForUpdate()->firstOrFail();

                $oldQty = (float) $item->current_stock_qty;
                $oldAvgCost = (int) $item->current_avg_cost_paise;
                $newQty = $oldQty + $lData['quantity'];

                $newAvgCost = $newQty > 0
                    ? (int) round((($oldQty * $oldAvgCost) + $netGoodsCostPaise) / $newQty)
                    : $lData['unit_cost_paise'];

                $item->update([
                    'current_stock_qty' => $newQty,
                    'current_avg_cost_paise' => $newAvgCost,
                ]);

                $discountNote = $lData['discount_paise'] > 0
                    ? sprintf(" [Supplier discount: ₹%.2f]", $lData['discount_paise'] / 100)
                    : '';

                // Record immutable movement
                InventoryMovement::create([
                    'inventory_item_id' => $item->id,
                    'shift_id' => $shiftId,
                    'type' => 'purchase_receipt',
                    'quantity' => $lData['quantity'],
                    'unit_cost_paise' => $lData['unit_cost_paise'],
                    'total_cost_paise' => $lData['line_total_paise'],
                    'balance_after_qty' => $newQty,
                    'avg_cost_after_paise' => $newAvgCost,
                    'reference_type' => 'purchase_receipt',
                    'reference_id' => $receipt->id,
                    'notes' => "Purchase from {$supplier->name} (Inv #{$invoiceNumber}){$discountNote}",
                    'created_by_user_id' => $userId ?? auth()->id(),
                ]);
            }

            // Post balanced journal entry:
            // Debit Inventory Stock (1030) ₹totalGrossPaise
            // Debit GST / Taxes (2030) ₹totalTaxPaise
            // Credit Bank Clearing (1020) ₹totalNetPaise
            $journalLines = [
                [
                    'account_code' => '1030', // Inventory Stock
                    'debit_paise' => $totalGrossPaise,
                    'narration' => "Stock receiving {$receipt->receipt_number}",
                    'department_code' => 'bar',
                ],
            ];

            if ($totalTaxPaise > 0) {
                $journalLines[] = [
                    'account_code' => '2030', // GST / Tax input
                    'debit_paise' => $totalTaxPaise,
                    'narration' => "Input tax on purchase {$receipt->receipt_number}",
                ];
            }

            $journalLines[] = [
                'account_code' => '1020', // Bank Clearing / Supplier settlement
                'credit_paise' => $totalNetPaise,
                'narration' => "Payment/payable for {$receipt->receipt_number}",
            ];

            $this->journalService->post(
                sourceType: 'purchase_receipt',
                sourceId: $receipt->id,
                description: "Purchase invoice #{$invoiceNumber} from {$supplier->name}",
                lines: $journalLines,
                userId: $userId
            );

            return $receipt->fresh(['lines.item', 'supplier']);
        });
    }

    /**
     * Issue stock for a sold bill line based on serving mappings.
     * Enforces concurrency lock and rejects if available stock is insufficient.
     *
     * @throws InsufficientStockException
     */
    public function issueStockForBillLine(BillLine $line, ?int $shiftId = null, ?int $userId = null): ?int
    {
        // Check if there is a serving mapping for this bill item
        $mapping = ServingMapping::where('tariff_id', $line->item_id)->first();
        if (!$mapping) {
            return null;
        }

        return DB::transaction(function () use ($line, $mapping, $shiftId, $userId) {
            /** @var InventoryItem $item */
            $item = InventoryItem::where('id', $mapping->inventory_item_id)->lockForUpdate()->firstOrFail();

            $requiredQty = (float) ($mapping->serving_qty * $line->quantity);

            $allowZeroStock = \App\Domain\Shared\Models\SystemSetting::getBool('allow_zero_stock_sales', false);

            if (!$allowZeroStock && $item->current_stock_qty < $requiredQty) {
                throw new InsufficientStockException(
                    sprintf(
                        "Insufficient stock for %s. Available: %.3f %s, Required: %.3f %s.",
                        $item->name,
                        $item->current_stock_qty,
                        $item->base_unit,
                        $requiredQty,
                        $item->base_unit
                    )
                );
            }

            $unitCost = (int) $item->current_avg_cost_paise;
            $cogsPaise = (int) round($requiredQty * $unitCost);
            $newQty = $item->current_stock_qty - $requiredQty;

            $item->update(['current_stock_qty' => $newQty]);

            $promoNote = ((float) ($line->free_quantity ?? 0) > 0)
                ? " (incl. {$line->free_quantity} promo free)"
                : '';

            // Record immutable stock movement
            InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'shift_id' => $shiftId,
                'type' => 'sale_issue',
                'quantity' => -$requiredQty,
                'unit_cost_paise' => $unitCost,
                'total_cost_paise' => $cogsPaise,
                'balance_after_qty' => $newQty,
                'avg_cost_after_paise' => $unitCost,
                'reference_type' => 'bill_line',
                'reference_id' => $line->id,
                'notes' => "Sold {$line->quantity} x {$mapping->serving_name}{$promoNote} on Bill #{$line->bill->bill_number}",
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);

            // Post COGS Journal Entry:
            // Debit 5010 (Cost of Goods Sold - Liquor)
            // Credit 1030 (Inventory Stock)
            if ($cogsPaise > 0) {
                $this->journalService->post(
                    sourceType: 'cogs_issue',
                    sourceId: $line->id,
                    description: "COGS for Bill #{$line->bill->bill_number} Line #{$line->id}",
                    lines: [
                        [
                            'account_code' => '5010', // COGS - Liquor
                            'debit_paise' => $cogsPaise,
                            'narration' => "COGS for {$mapping->serving_name}",
                            'department_code' => 'bar',
                        ],
                        [
                            'account_code' => '1030', // Inventory Stock
                            'credit_paise' => $cogsPaise,
                            'narration' => "Inventory issue for {$line->bill->bill_number}",
                            'department_code' => 'bar',
                        ],
                    ],
                    userId: $userId
                );
            }

            return $cogsPaise;
        });
    }

    /**
     * Record approved spillage/breakage/wastage.
     * Journal: Debit 5030 (Operating Expenses), Credit 1030 (Inventory Stock).
     */
    public function recordWastage(
        InventoryItem $item,
        float $qty,
        string $reason,
        ?int $shiftId = null,
        ?int $userId = null
    ): InventoryMovement {
        if ($qty <= 0) {
            throw new InvalidArgumentException("Wastage quantity must be positive.");
        }

        return DB::transaction(function () use ($item, $qty, $reason, $shiftId, $userId) {
            /** @var InventoryItem $lockedItem */
            $lockedItem = InventoryItem::where('id', $item->id)->lockForUpdate()->firstOrFail();

            if ($lockedItem->current_stock_qty < $qty) {
                throw new InsufficientStockException("Cannot record wastage exceeding current stock.");
            }

            $unitCost = (int) $lockedItem->current_avg_cost_paise;
            $totalCost = (int) round($qty * $unitCost);
            $newQty = $lockedItem->current_stock_qty - $qty;

            $lockedItem->update(['current_stock_qty' => $newQty]);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $lockedItem->id,
                'shift_id' => $shiftId,
                'type' => 'wastage',
                'quantity' => -$qty,
                'unit_cost_paise' => $unitCost,
                'total_cost_paise' => $totalCost,
                'balance_after_qty' => $newQty,
                'avg_cost_after_paise' => $unitCost,
                'reference_type' => 'waste_log',
                'reference_id' => null,
                'notes' => "Approved wastage: {$reason}",
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);

            if ($totalCost > 0) {
                $this->journalService->post(
                    sourceType: 'inventory_wastage',
                    sourceId: $movement->id,
                    description: "Wastage write-off: {$lockedItem->name} ({$reason})",
                    lines: [
                        [
                            'account_code' => '5030', // Operating Expenses
                            'debit_paise' => $totalCost,
                            'narration' => "Wastage write-off: {$reason}",
                            'department_code' => 'bar',
                        ],
                        [
                            'account_code' => '1030', // Inventory Stock
                            'credit_paise' => $totalCost,
                            'narration' => "Inventory reduction for wastage",
                            'department_code' => 'bar',
                        ],
                    ],
                    userId: $userId
                );
            }

            return $movement;
        });
    }

    /**
     * Record physical count adjustment with full before/after audit trail.
     */
    public function recordPhysicalCountAdjustment(
        InventoryItem $item,
        float $countedQty,
        string $reason,
        ?int $userId = null
    ): InventoryMovement {
        if ($countedQty < 0) {
            throw new InvalidArgumentException("Counted quantity cannot be negative.");
        }

        $operator = $userId ? User::find($userId) : auth()->user();
        if ($operator && !$operator->hasClubPermission('stock.manage')) {
            throw new UnauthorizedActionException("Only a Manager or Administrator can adjust stock.");
        }

        return DB::transaction(function () use ($item, $countedQty, $reason, $userId) {
            /** @var InventoryItem $lockedItem */
            $lockedItem = InventoryItem::where('id', $item->id)->lockForUpdate()->firstOrFail();

            $previousQty = (float) $lockedItem->current_stock_qty;
            $variance = $countedQty - $previousQty;
            $unitCost = (int) $lockedItem->current_avg_cost_paise;
            $varianceCost = (int) round(abs($variance) * $unitCost);

            $lockedItem->update(['current_stock_qty' => $countedQty]);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $lockedItem->id,
                'type' => 'adjustment',
                'quantity' => $variance,
                'unit_cost_paise' => $unitCost,
                'total_cost_paise' => $varianceCost,
                'balance_after_qty' => $countedQty,
                'avg_cost_after_paise' => $unitCost,
                'reference_type' => 'physical_count',
                'reference_id' => null,
                'notes' => sprintf("Physical count adjustment: Was %.3f, Counted %.3f, Variance %.3f. Reason: %s", $previousQty, $countedQty, $variance, $reason),
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);

            if ($varianceCost > 0) {
                if ($variance < 0) {
                    // Stock loss
                    $this->journalService->post(
                        sourceType: 'inventory_adjustment',
                        sourceId: $movement->id,
                        description: "Stock shortage adjustment: {$lockedItem->name}",
                        lines: [
                            [
                                'account_code' => '5030',
                                'debit_paise' => $varianceCost,
                                'narration' => "Physical count shortage for {$lockedItem->name}",
                                'department_code' => 'bar',
                            ],
                            [
                                'account_code' => '1030',
                                'credit_paise' => $varianceCost,
                                'narration' => "Inventory count reduction",
                                'department_code' => 'bar',
                            ],
                        ],
                        userId: $userId
                    );
                } else {
                    // Stock gain
                    $this->journalService->post(
                        sourceType: 'inventory_adjustment',
                        sourceId: $movement->id,
                        description: "Stock surplus adjustment: {$lockedItem->name}",
                        lines: [
                            [
                                'account_code' => '1030',
                                'debit_paise' => $varianceCost,
                                'narration' => "Inventory count gain",
                                'department_code' => 'bar',
                            ],
                            [
                                'account_code' => '5030',
                                'credit_paise' => $varianceCost,
                                'narration' => "Physical count surplus for {$lockedItem->name}",
                                'department_code' => 'bar',
                            ],
                        ],
                        userId: $userId
                    );
                }
            }

            return $movement;
        });
    }

    /**
     * Handle stock treatment during a refund:
     * - If liquor was consumed/poured: $wasPhysicallyReturned is FALSE -> NO stock restored!
     * - If unopened item is physically returned: $wasPhysicallyReturned is TRUE -> Stock restored & COGS reversed.
     */
    public function handleRefundStock(BillLine $line, bool $wasPhysicallyReturned, ?int $userId = null): ?InventoryMovement
    {
        if (!$wasPhysicallyReturned) {
            // Strictly NO fabricated stock returns for consumed liquor (Scenario I03)
            return null;
        }

        $mapping = ServingMapping::where('tariff_id', $line->item_id)->first();
        if (!$mapping) {
            return null;
        }

        return DB::transaction(function () use ($line, $mapping, $userId) {
            /** @var InventoryItem $item */
            $item = InventoryItem::where('id', $mapping->inventory_item_id)->lockForUpdate()->firstOrFail();

            $restoreQty = (float) ($mapping->serving_qty * $line->quantity);
            $unitCost = (int) $item->current_avg_cost_paise;
            $totalCost = (int) round($restoreQty * $unitCost);
            $newQty = $item->current_stock_qty + $restoreQty;

            $item->update(['current_stock_qty' => $newQty]);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'type' => 'return_unopened',
                'quantity' => $restoreQty,
                'unit_cost_paise' => $unitCost,
                'total_cost_paise' => $totalCost,
                'balance_after_qty' => $newQty,
                'avg_cost_after_paise' => $unitCost,
                'reference_type' => 'bill_line',
                'reference_id' => $line->id,
                'notes' => "Unopened bottle return for Bill #{$line->bill->bill_number}",
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);

            if ($totalCost > 0) {
                // Reverse COGS
                $this->journalService->post(
                    sourceType: 'inventory_return',
                    sourceId: $movement->id,
                    description: "COGS reversal for unopened return {$item->name}",
                    lines: [
                        [
                            'account_code' => '1030', // Inventory Stock
                            'debit_paise' => $totalCost,
                            'narration' => "Stock restored from unopened return",
                            'department_code' => 'bar',
                        ],
                        [
                            'account_code' => '5010', // COGS - Liquor
                            'credit_paise' => $totalCost,
                            'narration' => "COGS credit for returned stock",
                            'department_code' => 'bar',
                        ],
                    ],
                    userId: $userId
                );
            }

            return $movement;
        });
    }
}
