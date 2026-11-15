<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table): void {
            $table->foreignId('source_leave_request_id')
                ->nullable()
                ->constrained('leave_requests')
                ->restrictOnDelete();
            $table->foreignId('target_payroll_period_id')
                ->nullable()
                ->constrained('payroll_periods')
                ->restrictOnDelete();
            $table->index(['source_leave_request_id', 'target_payroll_period_id'], 'payroll_adjustments_absence_target_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table): void {
            $table->dropIndex('payroll_adjustments_absence_target_idx');
            $table->dropConstrainedForeignId('source_leave_request_id');
            $table->dropConstrainedForeignId('target_payroll_period_id');
        });
    }
};
