<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use Carbon\CarbonImmutable;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OpeningStockBalancesLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ShiftSeeder::class,
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
        ]);

        $connectionName = (string) config('database.default');
        $databaseHost = (string) config("database.connections.{$connectionName}.host");
        config([
            'demo.credentials.password' => 'Demo-Only-Pass-2026!',
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test'],
            'demo.seeding.allowed_hosts' => [$databaseHost],
        ]);
    }

    public function test_it_creates_opening_stock_and_lots_through_the_movement_service_idempotently(): void
    {
        $asOf = CarbonImmutable::parse('2026-11-15', 'Asia/Manila');
        $openingDate = $asOf->subMonthsNoOverflow(18)->startOfDay();
        $options = new DemoSeedOptions(
            asOf: $asOf,
            scale: 'small',
            seed: 20261115,
            only: 'opening-stock-balances',
        );
        $runner = app(DemoSeedRunner::class);

        $runner->run($options);

        $movements = StockMovement::query()
            ->where('movement_type', StockMovementType::Opening)
            ->where('reference_type', 'opening')
            ->orderBy('id')
            ->get();
        $this->assertNotEmpty($movements);
        $this->assertTrue($movements->every(fn (StockMovement $movement): bool => bccomp((string) $movement->quantity, '0', 3) > 0
            && bccomp((string) $movement->unit_cost, '0', 4) > 0
            && $movement->lot_number !== null
            && $movement->created_at->toDateString() === $openingDate->toDateString()
            && $movement->gl_handoff_status?->value === 'not_required'));

        $warehouseActor = User::query()->where('email', 'villanueva.aira@gmail.com')->firstOrFail();
        $this->assertTrue($movements->every(fn (StockMovement $movement): bool => (int) $movement->created_by === (int) $warehouseActor->id));

        $nearExpiryItem = Item::query()->where('code', 'RM-005')->firstOrFail();
        $nearExpiryLots = $movements->where('item_id', $nearExpiryItem->id);
        $this->assertCount(3, $nearExpiryLots);
        $this->assertSame(1, $nearExpiryLots->filter(fn (StockMovement $movement): bool => $movement->expiry_date !== null
            && $movement->expiry_date->gte($asOf)
            && $movement->expiry_date->lte($asOf->addDays(30)))->count());
        $this->assertSame(1, $nearExpiryLots->filter(fn (StockMovement $movement): bool => $movement->expiry_date?->gt($asOf->addDays(30)) ?? false)->count());

        $stockOutItem = Item::query()->where('code', 'RM-003')->firstOrFail();
        $this->assertSame(1, $movements->where('item_id', $stockOutItem->id)->count());
        $this->assertGreaterThan(0, (float) StockLevel::query()->where('item_id', $stockOutItem->id)->value('quantity'));

        $productItems = Item::query()->where('item_type', 'finished_good')->get();
        $this->assertNotEmpty($productItems);
        foreach ($productItems as $product) {
            $this->assertGreaterThan(0, (float) StockLevel::query()->where('item_id', $product->id)->value('quantity'));
        }

        foreach (StockLevel::query()->orderBy('item_id')->get() as $level) {
            $ledgerQuantity = $movements->where('item_id', $level->item_id)
                ->where('to_location_id', $level->location_id)
                ->reduce(fn (string $sum, StockMovement $movement): string => bcadd($sum, (string) $movement->quantity, 3), '0.000');
            $this->assertSame(0, bccomp((string) $level->quantity, $ledgerQuantity, 3));
            $this->assertGreaterThanOrEqual(0, bccomp((string) $level->quantity, '0.000', 3));
        }

        $movementAudits = DB::table('audit_logs')
            ->where('model_type', (new StockMovement)->getMorphClass())
            ->whereIn('model_id', $movements->modelKeys())
            ->where('action', 'created')
            ->get();
        $this->assertCount($movements->count(), $movementAudits);
        $this->assertTrue($movementAudits->every(fn ($row): bool => (int) $row->user_id === (int) $warehouseActor->id
            && CarbonImmutable::parse($row->created_at)->toDateString() === $openingDate->toDateString()));

        $before = [
            'movements' => DB::table('stock_movements')->orderBy('id')->get()->toArray(),
            'levels' => DB::table('stock_levels')->orderBy('id')->get()->toArray(),
            'audits' => DB::table('audit_logs')->where('model_type', (new StockMovement)->getMorphClass())->orderBy('id')->get()->toArray(),
        ];
        $runner->run($options);
        $after = [
            'movements' => DB::table('stock_movements')->orderBy('id')->get()->toArray(),
            'levels' => DB::table('stock_levels')->orderBy('id')->get()->toArray(),
            'audits' => DB::table('audit_logs')->where('model_type', (new StockMovement)->getMorphClass())->orderBy('id')->get()->toArray(),
        ];

        $this->assertEquals($before, $after);
    }
}
