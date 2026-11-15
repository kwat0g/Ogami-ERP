<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Services\SettingsService;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Enums\StockCountItemStatus;
use App\Modules\Inventory\Enums\StockCountScope;
use App\Modules\Inventory\Enums\StockCountSessionStatus;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockCountItem;
use App\Modules\Inventory\Models\StockCountSession;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\WarehouseZone;
use App\Modules\Inventory\Services\StockCountService;
use App\Modules\Inventory\Support\StockCountAvailableActions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Parks one count variance at its independent-review step for a live walkthrough. */
final class InventoryCountLiveScenarioLayer extends DemoSeeder
{
    private const TITLE = 'DEMO-CYCLE-COUNT-LIVE:ZONE-A';

    public function __construct(private readonly StockCountService $stockCounts) {}

    public function key(): string
    {
        return 'inventory-count-live-scenario';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Inventory', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['opening-stock-balances', 'workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $countDay = $this->priorBusinessDay($context->options->asOf->setTimezone('Asia/Manila'));
        $warehouse = $this->activeUser('warehouse.staff@accounts.ogami-demo.test', ['inventory.stock_count.view', 'inventory.stock_count.manage']);
        $reviewer = $this->activeUser('system.admin@accounts.ogami-demo.test', ['inventory.stock_count.view', 'inventory.stock_count.manage']);
        $zone = WarehouseZone::query()
            ->where('code', 'A')
            ->whereHas('warehouse', static fn ($query) => $query->where('code', 'MW')->where('is_active', true))
            ->first();
        $item = Item::query()->where('code', 'RM-002')->where('is_active', true)->first();

        if ($zone === null || $item === null) {
            throw new RuntimeException('Inventory count scenario requires the active Main Warehouse raw-material zone and item RM-002.');
        }

        $stock = StockLevel::query()
            ->where('item_id', $item->id)
            ->where('quantity', '>', 0)
            ->whereHas('location', static fn ($query) => $query->where('zone_id', $zone->id)->where('is_active', true))
            ->orderBy('location_id')
            ->first();

        if (! $stock instanceof StockLevel || bccomp((string) $stock->quantity, '0.000', 3) <= 0) {
            throw new RuntimeException('Inventory count scenario requires positive opening stock for RM-002 in Main Warehouse zone A.');
        }

        $countedQuantity = bcdiv(bcmul((string) $stock->quantity, '90.000', 3), '100.000', 3);
        if (bccomp($countedQuantity, '0.000', 3) <= 0) {
            throw new RuntimeException('Inventory count scenario needs at least 0.002 units of RM-002 to record a positive count.');
        }

        $matches = StockCountSession::query()->where('title', self::TITLE)->limit(2)->get();
        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple stock-count sessions use the inventory live-scenario marker; reconcile duplicates before reseeding.');
        }

        $existing = $matches->first();
        if ($existing instanceof StockCountSession) {
            $this->assertExpectedSession($existing, $warehouse, $reviewer, $zone, $stock, $countedQuantity, $countDay);
            $context->records()->remember('inventory.count.zone-a.live', $existing);
            $context->records()->remember('inventory.count.zone-a.rm-002-variance', $existing->items()->where('item_id', $stock->item_id)->firstOrFail());

            return;
        }

        $session = $context->at($countDay->setTime(9, 15), fn (): StockCountSession => $context->asUser($warehouse, fn (): StockCountSession => $this->stockCounts->createSession([
            'title' => self::TITLE,
            'scope' => StockCountScope::Zone->value,
            'zone_id' => (int) $zone->id,
        ], $warehouse)));

        $session = $context->at($countDay->setTime(9, 30), fn (): StockCountSession => $context->asUser($warehouse, fn (): StockCountSession => $this->stockCounts->startSession((int) $session->id, $warehouse)));
        $line = StockCountItem::query()
            ->where('session_id', $session->id)
            ->where('location_id', $stock->location_id)
            ->where('item_id', $stock->item_id)
            ->first();
        if (! $line instanceof StockCountItem) {
            throw new RuntimeException('Started inventory count did not include the RM-002 opening-stock line.');
        }

        $line = $context->at($countDay->setTime(10, 0), fn (): StockCountItem => $context->asUser($warehouse, fn (): StockCountItem => $this->stockCounts->recordCount((int) $line->id, [
            'counted_quantity' => $countedQuantity,
            'lot_number' => null,
            'notes' => 'Demo count records a 10% short count for independent verification.',
        ], $warehouse)));
        $session = $session->fresh();

        $this->assertExpectedSession($session, $warehouse, $reviewer, $zone, $stock, $countedQuantity, $countDay);
        $context->records()->remember('inventory.count.zone-a.live', $session);
        $context->records()->remember('inventory.count.zone-a.rm-002-variance', $line);
    }

    private function assertExpectedSession(
        StockCountSession $session,
        User $warehouse,
        User $reviewer,
        WarehouseZone $zone,
        StockLevel $stock,
        string $countedQuantity,
        CarbonImmutable $countDay,
    ): void {
        $line = $session->items()
            ->where('location_id', $stock->location_id)
            ->where('item_id', $stock->item_id)
            ->first();
        $pendingLines = $session->items()->where('status', StockCountItemStatus::Pending->value)->count();
        $totalLines = $session->items()->count();

        if (! $line instanceof StockCountItem
            || $session->status !== StockCountSessionStatus::InProgress
            || $session->title !== self::TITLE
            || $session->scope !== StockCountScope::Zone->value
            || (int) $session->zone_id !== (int) $zone->id
            || (int) $session->created_by !== (int) $warehouse->id
            || $session->created_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') !== $countDay->setTime(9, 15)->format('Y-m-d H:i:s')
            || $session->frozen_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') !== $countDay->setTime(9, 30)->format('Y-m-d H:i:s')
            || (int) $session->total_locations !== $totalLines
            || (int) $session->counted_locations !== 1
            || $pendingLines !== $totalLines - 1
            || $line->status !== StockCountItemStatus::Counted
            || (int) $line->counted_by !== (int) $warehouse->id
            || $line->counted_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') !== $countDay->setTime(10, 0)->format('Y-m-d H:i:s')
            || bccomp((string) $line->system_quantity, (string) $stock->quantity, 3) !== 0
            || bccomp((string) $line->counted_quantity, $countedQuantity, 3) !== 0
            || bccomp((string) $line->variance, bcsub($countedQuantity, (string) $stock->quantity, 3), 3) !== 0
            || bccomp(ltrim((string) $line->variance_percent, '-'), (string) app(SettingsService::class)->requiredFloat('inventory.stock_count.variance_tolerance_pct', 0), 2) <= 0) {
            throw new RuntimeException('Seed-owned inventory count has drifted from its in-progress RM-002 variance scenario.');
        }

        $line->setRelation('session', $session);
        if (! in_array('approve_variance', StockCountAvailableActions::forItem($line, $reviewer), true)) {
            throw new RuntimeException('Current stock-count rules no longer offer independent variance approval to the seeded reviewer.');
        }

        $auditExists = DB::table('audit_logs')
            ->where('action', 'created')
            ->where('actor_type', 'user')
            ->where('user_id', $warehouse->id)
            ->where('model_type', $session->getMorphClass())
            ->where('model_id', $session->id)
            ->where('created_at', $countDay->setTime(9, 15)->format('Y-m-d H:i:s'))
            ->exists();
        if (! $auditExists) {
            throw new RuntimeException('Seed-owned inventory count is missing its controlled warehouse-user audit event.');
        }
    }

    /** @param list<string> $permissions */
    private function activeUser(string $email, array $permissions): User
    {
        $user = User::query()->where('email', $email)->first();
        if (! $user instanceof User || ! $user->is_active) {
            throw new RuntimeException("Inventory count scenario requires active demo account {$email}.");
        }
        foreach ($permissions as $permission) {
            if (! $user->hasPermission($permission)) {
                throw new RuntimeException("Inventory count scenario actor {$email} lacks required permission {$permission}.");
            }
        }

        return $user;
    }

    private function priorBusinessDay(CarbonImmutable $asOf): CarbonImmutable
    {
        $day = $asOf->startOfDay();
        while ($day->isWeekend() || $this->isHoliday($day)) {
            $day = $day->subDay();
        }

        return $day;
    }

    private function isHoliday(CarbonImmutable $day): bool
    {
        return Holiday::query()->whereDate('date', $day->toDateString())->exists()
            || Holiday::query()->where('is_recurring', true)
                ->whereRaw("to_char(date, 'MM-DD') = ?", [$day->format('m-d')])->exists();
    }
}
