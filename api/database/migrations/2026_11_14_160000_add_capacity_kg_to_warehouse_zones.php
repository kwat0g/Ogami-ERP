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
            $table->decimal('capacity_kg', 10, 2)->nullable()->after('zone_type');
        });

        // Planning baseline for unconfigured storage positions. This value is
        // informational and is not used to block stock movements.
        DB::table('warehouse_locations')
            ->whereNull('deleted_at')
            ->whereNull('capacity_kg')
            ->update(['capacity_kg' => '250.00']);

        $zones = DB::table('warehouse_zones')->whereNull('capacity_kg')->get(['id']);
        foreach ($zones as $zone) {
            $capacityKg = DB::table('warehouse_locations')
                ->where('zone_id', $zone->id)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->sum('capacity_kg');

            if ($capacityKg > 0) {
                DB::table('warehouse_zones')
                    ->where('id', $zone->id)
                    ->update(['capacity_kg' => $capacityKg]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('warehouse_zones', function (Blueprint $table): void {
            $table->dropColumn('capacity_kg');
        });
    }
};
