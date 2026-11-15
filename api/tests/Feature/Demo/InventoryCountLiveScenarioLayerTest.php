<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Enums\StockCountItemStatus;
use App\Modules\Inventory\Enums\StockCountSessionStatus;
use App\Modules\Inventory\Models\StockCountSession;
use App\Modules\Inventory\Services\StockCountService;
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

    public function test_seeded_warehouse_supervisor_can_complete_the_count_after_review_and_remaining_counts(): void
    {
        $asOf = CarbonImmutable::parse('2026-11-16', 'Asia/Manila');
        $options = new DemoSeedOptions(
            asOf: $asOf,
            scale: 'small',
            seed: 20261116,
            only: 'inventory-count-live-scenario',
        );
        app(DemoSeedRunner::class)->run($options);

        $context = new DemoSeedContext($options);
        $service = app(StockCountService::class);
        $session = StockCountSession::query()->where('title', 'DEMO-CYCLE-COUNT-LIVE:ZONE-A')->firstOrFail();
        $varianceLine = $session->items()->whereHas('item', static fn ($query) => $query->where('code', 'RM-002'))->firstOrFail();
        $counter = User::query()->where('email', 'warehouse.staff@accounts.ogami-demo.test')->firstOrFail();
        $reviewer = User::query()->where('email', 'system.admin@accounts.ogami-demo.test')->firstOrFail();
        $finisher = User::query()->where('email', 'warehouse.supervisor@accounts.ogami-demo.test')->firstOrFail();

        $context->at($asOf->setTime(10, 30), fn () => $context->asUser($reviewer, fn () => $service->approveVariance((int) $varianceLine->id, $reviewer)));

        foreach ($session->items()->where('status', StockCountItemStatus::Pending->value)->orderBy('id')->get() as $pendingLine) {
            $context->at($asOf->setTime(10, 45), fn () => $context->asUser($counter, fn () => $service->recordCount((int) $pendingLine->id, [
                'counted_quantity' => (string) $pendingLine->system_quantity,
                'notes' => 'Demo walkthrough count matches the captured quantity.',
            ], $counter)));
        }

        $session->load('items');
        $this->assertContains('complete', StockCountAvailableActions::forSession($session, $finisher));
        $completed = $context->at($asOf->setTime(11, 15), fn () => $context->asUser($finisher, fn () => $service->completeSession((int) $session->id, $finisher)));

        $varianceLine->refresh();
        $this->assertSame(StockCountSessionStatus::Completed, $completed->status);
        $this->assertSame($finisher->id, $completed->approved_by);
        $this->assertSame(StockCountItemStatus::Adjusted, $varianceLine->status);
        $this->assertSame(0, $completed->items()->where('status', StockCountItemStatus::Pending->value)->count());
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'stock_count_session',
            'reference_id' => $session->id,
            'item_id' => $varianceLine->item_id,
            'movement_type' => 'adjustment_out',
            'quantity' => '90.000',
            'created_by' => $finisher->id,
        ]);
        $this->assertSame('810.000', (string) DB::table('stock_levels')
            ->where('item_id', $varianceLine->item_id)
            ->where('location_id', $varianceLine->location_id)
            ->value('quantity'));
    }
}
