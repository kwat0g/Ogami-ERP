<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_order_limit_increase_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('portal_user_id')->nullable()->constrained('customer_portal_users')->nullOnDelete();
            $table->decimal('current_maximum_quantity', 12, 3);
            $table->decimal('requested_maximum_quantity', 12, 3);
            $table->string('status', 20)->default('requested');
            $table->text('customer_notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['customer_id', 'product_id', 'status'], 'customer_order_limit_requests_customer_product_status');
            $table->index(['status', 'created_at'], 'customer_order_limit_requests_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_limit_increase_requests');
    }
};
