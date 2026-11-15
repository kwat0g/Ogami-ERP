<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Enums\StockCountItemStatus;
use App\Modules\Inventory\Enums\StockCountSessionStatus;
use App\Modules\Inventory\Models\StockCountSession;
use App\Modules\Inventory\Support\StockCountAvailableActions;
use Carbon\CarbonImmutable;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InventoryCountLiveScenarioLayerTest extends TestCase
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
            WorkflowSeeder::class,
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

    public function test_it_parks_a_count_session_with_a_real_recorded_variance_and_replays_without_drift(): void
    {
        $asOf = CarbonImmutable::parse('2026-11-16', 'Asia/Manila');
        $options = new DemoSeedOptions(
            asOf: $asOf,
            scale: 'small',
            seed: 20261116,
            only: 'inventory-count-live-scenario',
        );
        $runner = app(DemoSeedRunner::class);

        $runner->run($options);

        $session = StockCountSession::query()->where('title', 'DEMO-CYCLE-COUNT-LIVE:ZONE-A')->firstOrFail();
        $line = $session->items()->whereHas('item', static fn ($query) => $query->where('code', 'RM-002'))->firstOrFail();
        $pendingCount = $session->items()->where('status', StockCountItemStatus::Pending->value)->count();
        $this->assertSame(StockCountSessionStatus::InProgress, $session->status);
        $this->assertSame(StockCountItemStatus::Counted, $line->status);
        $this->assertSame('warehouse.staff@accounts.ogami-demo.test', $session->creator->email);
        $this->assertSame($session->items()->count() - 1, $pendingCount);
        $this->assertSame(1, (int) $session->counted_locations);
        $this->assertNotSame(0, bccomp((string) $line->variance, '0.000', 3));
        $this->assertSame('2026-11-16 09:15:00', $session->created_at->timezone('Asia/Manila')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-16 10:00:00', $line->counted_at->timezone('Asia/Manila')->format('Y-m-d H:i:s'));
        $reviewer = User::query()->where('email', 'system.admin@accounts.ogami-demo.test')->firstOrFail();
        $this->assertContains('approve_variance', StockCountAvailableActions::forItem($line->load('session'), $reviewer));

        $stockBefore = DB::table('stock_levels')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
        $movementsBefore = DB::table('stock_movements')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
        $sessionBefore = (array) DB::table('stock_count_sessions')->where('id', $session->id)->first();
        $itemsBefore = DB::table('stock_count_items')->where('session_id', $session->id)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
        $auditsBefore = DB::table('audit_logs')
            ->where('model_type', $session->getMorphClass())
            ->where('model_id', $session->id)
            ->orderBy('id')
            ->get()
            ->map(static fn ($row): array => (array) $row)
            ->all();

        $runner->run($options);

        $this->assertSame(1, StockCountSession::query()->where('title', 'DEMO-CYCLE-COUNT-LIVE:ZONE-A')->count());
        $this->assertSame($stockBefore, DB::table('stock_levels')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all());
        $this->assertSame($movementsBefore, DB::table('stock_movements')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all());
        $this->assertSame($sessionBefore, (array) DB::table('stock_count_sessions')->where('id', $session->id)->first());
        $this->assertSame($itemsBefore, DB::table('stock_count_items')->where('session_id', $session->id)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all());
        $this->assertSame($auditsBefore, DB::table('audit_logs')
            ->where('model_type', $session->getMorphClass())
            ->where('model_id', $session->id)
            ->orderBy('id')
            ->get()
            ->map(static fn ($row): array => (array) $row)
            ->all());
    }
}
