<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedLayerRegistry;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Enums\SalesOrderStatus;
use App\Modules\CRM\Models\SalesOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OrderToCashHistoryLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
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

    public function test_it_creates_a_sales_draft_for_confirmation_and_replays_without_drift(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-16', 'Asia/Manila'),
            scale: 'small',
            seed: 20261116,
            only: 'order-to-cash-history',
        );

        app(DemoSeedRunner::class)->run($options);

        $order = SalesOrder::query()->where('notes', 'Pending confirmation — FC-001 customer replenishment')->firstOrFail();
        $this->assertSame(SalesOrderStatus::Draft, $order->status);
        $this->assertSame('internal', $order->submission_source->value);
        $this->assertSame('2026-11-16', $order->created_at->timezone('Asia/Manila')->toDateString());
        $this->assertSame(1, $order->items()->count());
        $this->assertSame((int) $order->created_by, (int) User::query()->where('email', 'abiera.shiela@gmail.com')->value('id'));

        $item = $order->items()->firstOrFail();
        $this->assertNotNull($item->price_agreement_id);
        $this->assertDatabaseHas('product_price_agreements', [
            'id' => $item->price_agreement_id,
            'customer_id' => $order->customer_id,
            'product_id' => $item->product_id,
        ]);
        $this->assertSame((string) $item->total, (string) $order->subtotal);
        $this->assertSame(bcadd((string) $order->subtotal, (string) $order->vat_amount, 2), (string) $order->total_amount);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'actor_type' => 'user',
            'model_type' => SalesOrder::class,
            'model_id' => $order->id,
        ]);

        $before = $this->fingerprint($order->id);
        $layers = app(DemoSeedLayerRegistry::class)->ordered($options->only);
        $layer = end($layers);
        $this->assertNotFalse($layer);
        $layer->run((new DemoSeedContext($options))->forLayer($layer->key()));
        $this->assertSame($before, $this->fingerprint($order->id));
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function fingerprint(int $orderId): array
    {
        return [
            'order' => DB::table('sales_orders')->where('id', $orderId)->get()->map(static fn ($row): array => (array) $row)->all(),
            'items' => DB::table('sales_order_items')->where('sales_order_id', $orderId)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'audit' => DB::table('audit_logs')->where('action', 'created')->where('model_id', $orderId)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
        ];
    }
}
