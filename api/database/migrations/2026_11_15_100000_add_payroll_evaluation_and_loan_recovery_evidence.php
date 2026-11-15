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
        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->string('computation_policy_version', 64)->nullable()->index();
        });

        Schema::create('payroll_anomaly_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->char('input_fingerprint', 64)->nullable();
            $table->string('policy_version', 64);
            $table->unsignedInteger('finding_count')->default(0);
            $table->unsignedInteger('unresolved_finding_count')->default(0);
            $table->text('failure_message')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->index(['payroll_period_id', 'id'], 'payroll_anomaly_eval_latest_idx');
            $table->index(['status', 'evaluated_at'], 'payroll_anomaly_eval_status_idx');
        });

        Schema::create('payroll_loan_recovery_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_id')->nullable()->constrained('payrolls')->cascadeOnDelete();
            $table->foreignId('clearance_id')->nullable()->constrained('clearances')->cascadeOnDelete();
            $table->uuid('allocation_run_id')->nullable();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->restrictOnDelete();
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('withheld_amount', 15, 2);
            $table->decimal('deferred_amount', 15, 2);
            $table->string('deferred_reason', 40)->nullable();
            $table->timestamps();

            $table->unique(['payroll_id', 'employee_loan_id'], 'payroll_loan_recovery_unique');
            $table->unique(['clearance_id', 'allocation_run_id', 'employee_loan_id'], 'clearance_loan_run_recovery_unique');
            $table->index('employee_loan_id');
        });
    }

    public function down(): void
    {
        if ((Schema::hasTable('payroll_anomaly_evaluations') && DB::table('payroll_anomaly_evaluations')->exists())
            || (Schema::hasTable('payroll_loan_recovery_allocations') && DB::table('payroll_loan_recovery_allocations')->exists())) {
            throw new RuntimeException('Payroll evaluation and deduction allocation evidence is in use. Preserve it and use a forward migration to roll back.');
        }

        Schema::dropIfExists('payroll_loan_recovery_allocations');
        Schema::dropIfExists('payroll_anomaly_evaluations');
        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->dropColumn('computation_policy_version');
        });
    }
};
