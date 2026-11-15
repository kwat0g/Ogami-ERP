<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Accounting\Enums\InvoiceStatus;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\CRM\Enums\SalesOrderStatus;
use App\Modules\CRM\Models\SalesOrder;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Production\Enums\WorkOrderStatus;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Production\Models\WorkOrderOutput;
use App\Modules\Quality\Enums\InspectionStage;
use App\Modules\Quality\Enums\InspectionStatus;
use App\Modules\Quality\Models\Inspection;
use App\Modules\SupplyChain\Enums\DeliveryStatus;
use App\Modules\SupplyChain\Models\Delivery;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Database\Seeders\WorkflowSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OrderToCashFulfillmentLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
            ShiftSeeder::class,
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
            WorkflowSeeder::class,
            VehicleSeeder::class,
        ]);

        $connectionName = (string) config('database.default');
        $databaseHost = (string) config("database.connections.{$connectionName}.host");
        config([
            'demo.credentials.password' => 'Demo-Only-Pass-2026!',
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test'],
            'demo.seeding.allowed_hosts' => [$databaseHost],
            'demo.layers' => [
                ...config('demo.layers', []),
                \Database\Seeders\Demo\OrderToCashFulfillmentLayer::class,
            ],
        ]);
    }

    public function test_it_creates_a_historical_order_to_collection_chain_without_moving_the_live_draft(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-15', 'Asia/Manila'),
            scale: 'small',
            seed: 20261115,
            only: 'order-to-cash-fulfillment',
        );

        app(DemoSeedRunner::class)->run($options);

        $liveOrder = SalesOrder::query()->where('notes', 'OGI-O2C-LIVE:SO-DRAFT-0001')->firstOrFail();
        $this->assertSame(SalesOrderStatus::Draft, $liveOrder->status);

        $order = SalesOrder::query()->where('notes', 'Monthly replenishment order for BB-001, customer cycle 01')->firstOrFail();
        $line = $order->items()->firstOrFail();
        $this->assertSame('BB-001', $line->product->part_number);

        $workOrder = WorkOrder::query()->where('sales_order_item_id', $line->id)
            ->where('sales_order_id', $order->id)->firstOrFail();
        $this->assertContains($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Closed]);
        $output = WorkOrderOutput::query()->where('work_order_id', $workOrder->id)->firstOrFail();
        $receipt = StockMovement::query()->findOrFail($output->production_receipt_movement_id);
        $this->assertSame('work_order_output', $receipt->reference_type);
        $this->assertSame((int) $output->id, (int) $receipt->reference_id);

        $outgoing = Inspection::query()->where('work_order_output_id', $output->id)
            ->where('stage', InspectionStage::Outgoing->value)->firstOrFail();
        $this->assertSame(InspectionStatus::Passed, $outgoing->status);

        $delivery = Delivery::query()->where('sales_order_id', $order->id)->firstOrFail();
        $this->assertSame(DeliveryStatus::Confirmed, $delivery->status);
        $this->assertDatabaseHas('delivery_items', [
            'delivery_id' => $delivery->id,
            'sales_order_item_id' => $line->id,
            'inspection_id' => $outgoing->id,
        ]);

        $invoice = Invoice::query()->where('delivery_id', $delivery->id)->firstOrFail();
        $this->assertSame((int) $order->id, (int) $invoice->sales_order_id);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('0.00', (string) $invoice->balance);
        $this->assertDatabaseHas('collections', ['invoice_id' => $invoice->id]);

        $layer = app(\Database\Seeders\Demo\OrderToCashFulfillmentLayer::class);
        $layer->run((new DemoSeedContext($options))->forLayer($layer->key()));
        $this->assertSame(1, SalesOrder::query()->where('notes', 'Monthly replenishment order for BB-001, customer cycle 01')->count());
        $this->assertSame(1, Delivery::query()->where('sales_order_id', $order->id)->count());
        $this->assertSame(1, Invoice::query()->where('delivery_id', $delivery->id)->count());
        $this->assertSame(1, DB::table('collections')->where('invoice_id', $invoice->id)->count());
    }
}
