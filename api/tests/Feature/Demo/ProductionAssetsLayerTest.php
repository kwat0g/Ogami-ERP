<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Assets\Models\Asset;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\MRP\Models\Machine;
use App\Modules\MRP\Models\Mold;
use App\Modules\MRP\Models\MoldHistory;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionAssetsLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_scale_seeds_the_planned_machine_and_mold_volume(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            scale: 'demo',
            seed: 20261114,
            only: 'production-assets',
        );

        app(DemoSeedRunner::class)->run($options);

        $this->assertSame(30, Machine::query()->count());
        $this->assertSame(60, Mold::query()->count());
        $this->assertSame(102, Asset::query()->count());
        $this->assertSame(60, MaintenanceSchedule::query()->where('maintainable_type', 'mold')->count());
        $this->assertSame(0, Mold::query()->doesntHave('compatibleMachines')->count());
        $this->assertSame(0, Machine::query()->whereNull('asset_id')->count());
        $this->assertSame(0, Mold::query()->whereNull('asset_id')->count());
    }

    public function test_it_creates_linked_production_assets_molds_and_compatibility_idempotently(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            scale: 'small',
            seed: 20261114,
            only: 'production-assets',
        );

        $runner = app(DemoSeedRunner::class);
        $runner->run($options);

        $this->assertSame(4, Machine::query()->count());
        $this->assertSame(16, Mold::query()->count());
        $this->assertSame(23, Asset::query()->count());
        $this->assertSame(4, Asset::query()->where('category', 'machine')->count());
        $this->assertSame(16, Asset::query()->where('category', 'mold')->count());
        $this->assertSame(3, Asset::query()->where('category', 'equipment')->count());
        $this->assertSame(16, MaintenanceSchedule::query()->where('maintainable_type', 'mold')->count());
        $this->assertSame(16, MoldHistory::query()->where('event_type', 'created')->count());
        $this->assertGreaterThan(0, \DB::table('mold_machine_compatibility')->count());
        $this->assertSame(0, Mold::query()->doesntHave('compatibleMachines')->count());
        $this->assertSame(0, Machine::query()->whereNull('asset_id')->count());
        $this->assertSame(0, Mold::query()->whereNull('asset_id')->count());

        $machine = Machine::query()->where('machine_code', 'IMM-01')->firstOrFail();
        $mold = Mold::query()->where('mold_code', 'M-WB-001')->firstOrFail();
        $this->assertSame('2026-11-14', $machine->created_at->toDateString());
        $this->assertSame('2026-11-14', $mold->created_at->toDateString());
        $this->assertGreaterThanOrEqual(80, $mold->shot_percentage);
        $this->assertGreaterThan(0, $mold->compatibleMachines()->count());

        $assetCodes = Asset::query()->orderBy('asset_code')->pluck('asset_code')->all();
        $compatibilityCount = \DB::table('mold_machine_compatibility')->count();

        $runner->run($options);

        $this->assertSame(4, Machine::query()->count());
        $this->assertSame(16, Mold::query()->count());
        $this->assertSame(23, Asset::query()->count());
        $this->assertSame(16, MaintenanceSchedule::query()->where('maintainable_type', 'mold')->count());
        $this->assertSame(16, MoldHistory::query()->where('event_type', 'created')->count());
        $this->assertSame($assetCodes, Asset::query()->orderBy('asset_code')->pluck('asset_code')->all());
        $this->assertSame($compatibilityCount, \DB::table('mold_machine_compatibility')->count());
        $this->assertNull(Carbon::getTestNow());
        $this->assertNull(CarbonImmutable::getTestNow());
    }
}
