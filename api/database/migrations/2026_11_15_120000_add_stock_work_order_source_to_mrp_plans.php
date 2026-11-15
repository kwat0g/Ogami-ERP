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
        DB::statement('ALTER TABLE mrp_plans DROP CONSTRAINT mrp_plans_sales_order_id_foreign');
        DB::statement('ALTER TABLE mrp_plans ALTER COLUMN sales_order_id DROP NOT NULL');
        DB::statement('ALTER TABLE mrp_plans ADD CONSTRAINT mrp_plans_sales_order_id_foreign FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE');

        Schema::table('mrp_plans', function (Blueprint $table): void {
            $table->foreignId('stock_production_work_order_id')->nullable()
                ->after('sales_order_id')->constrained('work_orders')->restrictOnDelete();
            $table->index('stock_production_work_order_id', 'mrp_plans_stock_wo_index');
        });

        DB::statement("ALTER TABLE mrp_plans ADD CONSTRAINT mrp_plans_exactly_one_source CHECK (((sales_order_id IS NOT NULL)::integer + (stock_production_work_order_id IS NOT NULL)::integer) = 1)");
        DB::statement("CREATE UNIQUE INDEX mrp_plans_one_active_per_stock_wo ON mrp_plans (stock_production_work_order_id) WHERE status = 'active'");
        DB::statement('CREATE UNIQUE INDEX mrp_plans_stock_wo_version_unique ON mrp_plans (stock_production_work_order_id, version) WHERE stock_production_work_order_id IS NOT NULL');
    }

    public function down(): void
    {
        if (DB::table('mrp_plans')->whereNotNull('stock_production_work_order_id')->exists()
            || DB::table('mrp_plans')->whereNull('sales_order_id')->exists()) {
            throw new RuntimeException('Stock work-order MRP plans exist. Their source history must be preserved.');
        }

        DB::statement('DROP INDEX IF EXISTS mrp_plans_stock_wo_version_unique');
        DB::statement('DROP INDEX IF EXISTS mrp_plans_one_active_per_stock_wo');
        DB::statement('ALTER TABLE mrp_plans DROP CONSTRAINT mrp_plans_exactly_one_source');
        Schema::table('mrp_plans', function (Blueprint $table): void {
            $table->dropIndex('mrp_plans_stock_wo_index');
            $table->dropConstrainedForeignId('stock_production_work_order_id');
        });
        DB::statement('ALTER TABLE mrp_plans DROP CONSTRAINT mrp_plans_sales_order_id_foreign');
        DB::statement('ALTER TABLE mrp_plans ALTER COLUMN sales_order_id SET NOT NULL');
        DB::statement('ALTER TABLE mrp_plans ADD CONSTRAINT mrp_plans_sales_order_id_foreign FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE');
    }
};
