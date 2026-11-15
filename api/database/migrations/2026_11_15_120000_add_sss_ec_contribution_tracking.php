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
        if (! Schema::hasColumn('government_contribution_tables', 'ec_amount')) {
            Schema::table('government_contribution_tables', function (Blueprint $table): void {
                $table->decimal('ec_amount', 15, 4)->default(0);
            });
        }

        if (! Schema::hasColumn('payrolls', 'sss_ec')) {
            Schema::table('payrolls', function (Blueprint $table): void {
                $table->decimal('sss_ec', 15, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payrolls', 'sss_ec')
            && DB::table('payrolls')->where('sss_ec', '>', 0)->exists()) {
            throw new \RuntimeException('SSS EC payroll amounts exist; preserve them with a reviewed data migration before rollback.');
        }

        if (Schema::hasColumn('government_contribution_tables', 'ec_amount')
            && DB::table('government_contribution_tables')->where('ec_amount', '>', 0)->exists()) {
            throw new \RuntimeException('SSS EC schedule amounts exist; preserve them with a reviewed data migration before rollback.');
        }

        if (Schema::hasColumn('payrolls', 'sss_ec')) {
            Schema::table('payrolls', function (Blueprint $table): void {
                $table->dropColumn('sss_ec');
            });
        }

        if (Schema::hasColumn('government_contribution_tables', 'ec_amount')) {
            Schema::table('government_contribution_tables', function (Blueprint $table): void {
                $table->dropColumn('ec_amount');
            });
        }
    }
};
