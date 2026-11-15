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
        Schema::table('warehouse_zones', function (Blueprint $table): void {
            $table->string('capacity_status', 16)->default('unverified')->after('capacity_kg');
        });

        Schema::table('warehouse_locations', function (Blueprint $table): void {
            $table->string('capacity_status', 16)->default('unverified')->after('capacity_kg');
        });

        // The previous migration derived zone capacities from active bins.
        // Preserve that provenance so those estimates can continue to track
        // layout changes. Existing bin values remain unverified because the
        // old field cannot distinguish a backfill from a rated value.
        foreach (DB::table('warehouse_zones')->whereNotNull('capacity_kg')->get(['id', 'capacity_kg']) as $zone) {
            $activeBinCapacity = DB::table('warehouse_locations')
                ->where('zone_id', $zone->id)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->sum('capacity_kg');

            if (bccomp((string) $zone->capacity_kg, (string) $activeBinCapacity, 2) === 0) {
                DB::table('warehouse_zones')
                    ->where('id', $zone->id)
                    ->update(['capacity_status' => 'derived']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('warehouse_locations', function (Blueprint $table): void {
            $table->dropColumn('capacity_status');
        });

        Schema::table('warehouse_zones', function (Blueprint $table): void {
            $table->dropColumn('capacity_status');
        });
    }
};
