<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Domain\Inventory\Models\Supplier;
use App\Domain\Inventory\Services\InventoryService;
use App\Filament\Resources\Purchases\PurchaseReceiptResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreatePurchaseReceipt extends CreateRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $supplier = Supplier::findOrFail((int) $data['supplier_id']);

        $lines = array_map(function ($line) {
            return [
                'inventory_item_id' => (int) $line['inventory_item_id'],
                'quantity' => (float) $line['quantity'],
                'unit_cost_paise' => (int) ($line['unit_cost_paise'] ?? 0),
                'line_gross_paise' => isset($line['line_gross_paise']) ? (int) $line['line_gross_paise'] : null,
                'discount_paise' => (int) ($line['discount_paise'] ?? 0),
                'tax_percent' => (float) ($line['tax_percent'] ?? 0),
                'scheme_notes' => $line['scheme_notes'] ?? null,
            ];
        }, $data['lines'] ?? []);

        return app(InventoryService::class)->receivePurchase(
            supplier: $supplier,
            invoiceNumber: $data['invoice_number'],
            invoiceDate: $data['invoice_date'],
            items: $lines,
            idempotencyKey: (string) Str::uuid(),
            userId: auth()->id()
        );
    }
}

