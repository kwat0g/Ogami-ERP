<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_production_request_stock_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_production_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_output_id')->constrained('work_order_outputs')->restrictOnDelete();
            $table->foreignId('inspection_id')->constrained('inspections')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('warehouse_locations')->restrictOnDelete();
            $table->string('lot_number', 100);
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('released_quantity', 12, 3)->default(0);
            $table->decimal('transferred_quantity', 12, 3)->default(0);
            $table->string('status', 16)->default('held');
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->foreignId('sales_order_stock_allocation_id')->nullable()
                ->constrained('sales_order_stock_allocations')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['customer_production_request_id', 'work_order_output_id', 'location_id', 'lot_number'], 'customer_request_stock_hold_source_unique');
            $table->index(['status', 'expires_at'], 'customer_request_stock_hold_expiry_index');
            $table->index(['item_id', 'location_id', 'lot_number', 'status'], 'customer_request_stock_hold_lot_index');
        });
        DB::statement("ALTER TABLE customer_production_request_stock_holds ADD CONSTRAINT customer_request_stock_hold_status_valid CHECK (status IN ('held','released','transferred','expired'))");
        DB::statement("ALTER TABLE customer_production_request_stock_holds ADD CONSTRAINT customer_request_stock_hold_quantity_valid CHECK (quantity > 0 AND released_quantity >= 0 AND transferred_quantity >= 0 AND released_quantity + transferred_quantity <= quantity)");
    }

    public function down(): void
    {
        if (DB::table('customer_production_request_stock_holds')->exists()) {
            throw new RuntimeException('Customer production request stock holds exist. Their reservation history must be preserved.');
        }
        Schema::dropIfExists('customer_production_request_stock_holds');
    }
};
