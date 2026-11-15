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
        Schema::create('customer_production_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_number', 20)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('portal_user_id')->nullable()->constrained('customer_portal_users')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('requested_quantity');
            $table->date('requested_need_by_date');
            $table->date('offered_ready_date')->nullable();
            $table->string('status', 24)->default('requested');
            $table->text('customer_notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->uuid('request_key');
            $table->string('payload_fingerprint', 64);
            $table->uuid('planning_request_key')->nullable()->unique();
            $table->foreignId('converted_sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_notified_at')->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'request_key'], 'customer_production_request_idempotency_unique');
            $table->index(['customer_id', 'status', 'created_at'], 'customer_production_request_customer_status_index');
            $table->index(['product_id', 'status'], 'customer_production_request_product_status_index');
        });
        DB::statement('ALTER TABLE customer_production_requests ADD CONSTRAINT customer_production_request_quantity_valid CHECK (requested_quantity > 0 AND requested_quantity <= 5000)');
        DB::statement("ALTER TABLE customer_production_requests ADD CONSTRAINT customer_production_request_status_valid CHECK (status IN ('requested','planning','date_offered','accepted','in_production','ready_to_order','converted','declined','cancelled','expired'))");
        DB::statement("CREATE UNIQUE INDEX customer_production_request_one_open_per_customer_product ON customer_production_requests (customer_id, product_id) WHERE status NOT IN ('converted','declined','cancelled','expired')");

        Schema::create('customer_production_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_production_request_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 40);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_production_request_id', 'created_at'], 'customer_production_request_events_timeline_index');
        });

        Schema::table('mrp_plans', function (Blueprint $table): void {
            $table->foreignId('customer_production_request_id')->nullable()
                ->after('stock_production_work_order_id')->constrained('customer_production_requests')->restrictOnDelete();
            $table->index('customer_production_request_id', 'mrp_plans_customer_request_index');
        });
        DB::statement('ALTER TABLE mrp_plans DROP CONSTRAINT mrp_plans_exactly_one_source');
        DB::statement("ALTER TABLE mrp_plans ADD CONSTRAINT mrp_plans_exactly_one_source CHECK (((sales_order_id IS NOT NULL)::integer + (stock_production_work_order_id IS NOT NULL)::integer + (customer_production_request_id IS NOT NULL)::integer) = 1)");
        DB::statement("CREATE UNIQUE INDEX mrp_plans_one_active_per_customer_request ON mrp_plans (customer_production_request_id) WHERE status = 'active'");
        DB::statement('CREATE UNIQUE INDEX mrp_plans_customer_request_version_unique ON mrp_plans (customer_production_request_id, version) WHERE customer_production_request_id IS NOT NULL');

        $setting = DB::table('settings')->where('key', 'documents.sequence_config')->first();
        if ($setting) {
            $config = json_decode((string) $setting->value, true);
            if (is_array($config) && ! isset($config['customer_production_request'])) {
                $config['customer_production_request'] = ['prefix' => 'CPR', 'reset' => 'monthly', 'pad' => 4];
                DB::table('settings')->where('key', 'documents.sequence_config')->update([
                    'value' => json_encode($config, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (DB::table('customer_production_requests')->exists()
            || DB::table('mrp_plans')->whereNotNull('customer_production_request_id')->exists()) {
            throw new RuntimeException('Customer production request history exists. Its MRP source and audit trail must be preserved.');
        }

        $setting = DB::table('settings')->where('key', 'documents.sequence_config')->first();
        if ($setting) {
            $config = json_decode((string) $setting->value, true);
            if (is_array($config) && array_key_exists('customer_production_request', $config)) {
                unset($config['customer_production_request']);
                DB::table('settings')->where('key', 'documents.sequence_config')->update([
                    'value' => json_encode($config, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                ]);
            }
        }

        DB::statement('DROP INDEX IF EXISTS mrp_plans_customer_request_version_unique');
        DB::statement('DROP INDEX IF EXISTS mrp_plans_one_active_per_customer_request');
        DB::statement('ALTER TABLE mrp_plans DROP CONSTRAINT mrp_plans_exactly_one_source');
        DB::statement("ALTER TABLE mrp_plans ADD CONSTRAINT mrp_plans_exactly_one_source CHECK (((sales_order_id IS NOT NULL)::integer + (stock_production_work_order_id IS NOT NULL)::integer) = 1)");
        Schema::table('mrp_plans', function (Blueprint $table): void {
            $table->dropIndex('mrp_plans_customer_request_index');
            $table->dropConstrainedForeignId('customer_production_request_id');
        });
        Schema::dropIfExists('customer_production_request_events');
        DB::statement('DROP INDEX IF EXISTS customer_production_request_one_open_per_customer_product');
        Schema::dropIfExists('customer_production_requests');
    }
};
