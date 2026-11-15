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
        Schema::create('government_schedule_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('agency', 20);
            $table->date('effective_date');
            $table->unsignedInteger('version_number');
            $table->string('status', 24)->default('draft');
            $table->string('source_title', 200)->nullable();
            $table->text('source_url')->nullable();
            $table->string('source_file_name')->nullable();
            $table->char('source_file_sha256', 64)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('completeness_confirmed')->default(false);
            $table->timestamp('completeness_confirmed_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()
                ->constrained('government_schedule_versions')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->unique(['agency', 'effective_date', 'version_number'], 'gsv_agency_date_version_unique');
            $table->index(['agency', 'status', 'effective_date'], 'gsv_agency_status_date_idx');
            $table->index('source_file_sha256', 'gsv_source_hash_idx');
        });

        Schema::table('government_contribution_tables', function (Blueprint $table): void {
            $table->unsignedBigInteger('schedule_version_id')->nullable()->after('effective_date');
            $table->index('schedule_version_id', 'gct_schedule_version_idx');
        });

        // Existing rows can be grouped deterministically by agency/effective
        // date, but their source files and approval history are unavailable.
        // Keep that evidence gap explicit so these rows cannot be selected for
        // a new payroll computation.
        $groups = DB::table('government_contribution_tables')
            ->select('agency', 'effective_date')
            ->selectRaw('COUNT(*) as row_count')
            ->groupBy('agency', 'effective_date')
            ->orderBy('agency')
            ->orderBy('effective_date')
            ->get();

        foreach ($groups as $group) {
            $versionId = DB::table('government_schedule_versions')->insertGetId([
                'agency' => $group->agency,
                'effective_date' => $group->effective_date,
                'version_number' => 1,
                'status' => 'legacy_unverified',
                'row_count' => $group->row_count,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('government_contribution_tables')
                ->where('agency', $group->agency)
                ->whereDate('effective_date', $group->effective_date)
                ->update(['schedule_version_id' => $versionId]);
        }

        Schema::table('government_contribution_tables', function (Blueprint $table): void {
            $table->dropUnique('gct_agency_date_bracket_unique');
            $table->foreign('schedule_version_id', 'gct_schedule_version_fk')
                ->references('id')->on('government_schedule_versions')->restrictOnDelete();
            $table->unique(
                ['schedule_version_id', 'bracket_min', 'bracket_max'],
                'gct_version_bracket_unique',
            );
        });

        Schema::create('payroll_period_government_schedule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->string('agency', 20);
            $table->foreignId('government_schedule_version_id')
                ->constrained('government_schedule_versions')->restrictOnDelete();
            $table->date('effective_date');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['payroll_period_id', 'agency'], 'ppgsv_period_agency_unique');
            $table->index('government_schedule_version_id', 'ppgsv_version_idx');
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Government schedule versions contain source and approval evidence. Rollback requires a separately reviewed data-preserving migration.',
        );
    }
};
