<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('gstin')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Inventory Items
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('department_code')->default('bar');
            $table->string('category')->default('liquor');
            $table->string('base_unit')->default('ml'); // ml, pcs, bottle, kg
            $table->decimal('current_stock_qty', 12, 3)->default(0);
            $table->unsignedBigInteger('current_avg_cost_paise')->default(0); // in paise per base unit
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_code', 'is_active']);
        });

        // 3. Serving Mappings (mapping tariff / sellable serving to inventory item base units)
        Schema::create('serving_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_id')->constrained('tariffs')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('serving_name');
            $table->decimal('serving_qty', 10, 3); // quantity in base units (e.g. 30.000 ml or 60.000 ml)
            $table->timestamps();

            $table->unique(['tariff_id', 'inventory_item_id']);
        });

        // 4. Purchase Receipts (Supplier Goods Inward)
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date');
            $table->unsignedBigInteger('total_gross_paise')->default(0);
            $table->unsignedBigInteger('total_tax_paise')->default(0);
            $table->unsignedBigInteger('total_net_paise')->default(0);
            $table->string('status')->default('received'); // received, verified, cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });

        // 5. Purchase Receipt Lines
        Schema::create('purchase_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->decimal('quantity', 12, 3); // quantity in base units
            $table->unsignedBigInteger('unit_cost_paise'); // per base unit
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('line_total_paise');
            $table->timestamps();
        });

        // 6. Inventory Movements (Immutable Audit Trail)
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('type'); // purchase_receipt, sale_issue, wastage, adjustment, return_unopened
            $table->decimal('quantity', 12, 3); // positive or negative
            $table->unsignedBigInteger('unit_cost_paise'); // unit cost at time of movement
            $table->unsignedBigInteger('total_cost_paise');
            $table->decimal('balance_after_qty', 12, 3);
            $table->unsignedBigInteger('avg_cost_after_paise');
            $table->string('reference_type')->nullable(); // bill_line, purchase_receipt, waste_log, physical_count
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('purchase_receipt_lines');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('serving_mappings');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('suppliers');
    }
};
