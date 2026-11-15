<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedLayerRegistry;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Production\Enums\WorkOrderStatus;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Production\Models\WorkOrderOutput;
use App\Modules\Production\Services\OeeService;
use App\Modules\Quality\Enums\InspectionStage;
use App\Modules\Quality\Enums\InspectionStatus;
use App\Modules\Quality\Models\Inspection;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionExecutionHistoryLayerTest extends TestCase
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

    public function test_it_records_partial_traceable_output_and_produces_oee_inputs_through_domain_services(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-12-01', 'Asia/Manila'),
            scale: 'small',
            seed: 20261201,
            only: 'production-execution-history',
        );

        app(DemoSeedRunner::class)->run($options);

        $workOrder = WorkOrder::query()->where('stock_creation_request_key', '6fbeec8d-0000-5000-8000-000000000004')->firstOrFail();
        $this->assertSame(WorkOrderStatus::InProgress, $workOrder->status);
        $this->assertGreaterThan(0, (int) $workOrder->quantity_good);
        $this->assertLessThan((int) $workOrder->quantity_target, (int) $workOrder->quantity_good);
        $this->assertSame($workOrder->usesFinishFirstQc() ? 1 : 2, $workOrder->outputs()->count());

        $output = $workOrder->outputs()->where('idempotency_key', 'demo-production-execution-fc001-batch-1')->firstOrFail();
        $this->assertSame($workOrder->usesFinishFirstQc() ? 80 : 75, $output->good_count);
        $this->assertNotNull($output->production_receipt_movement_id);
        $this->assertSame('generated', $output->production_receipt_handoff_status->value);
        $this->assertSame($output->id, StockMovement::query()->whereKey($output->production_receipt_movement_id)
            ->where('movement_type', 'production_receipt')->where('reference_type', 'work_order_output')
            ->where('reference_id', $output->id)->value('reference_id'));

        $inspectionQuery = Inspection::query()->where('entity_type', 'work_order')->where('entity_id', $workOrder->id)
            ->where('stage', InspectionStage::InProcess->value)->whereNull('wo_operation_id');
        if ($workOrder->usesFinishFirstQc()) {
            $this->assertFalse($inspectionQuery->exists(), 'The configured finish-first workflow does not create an in-process inspection.');
        } else {
            $inspection = $inspectionQuery->firstOrFail();
            $this->assertSame(InspectionStatus::Passed, $inspection->status);
            $this->assertNotNull($inspection->completed_at);
        }

        $from = Carbon::parse($workOrder->actual_start)->subMinutes(1);
        $to = Carbon::parse($output->recorded_at)->addMinutes(1);
        $oee = app(OeeService::class)->calculate($workOrder->machine, $from, $to);
        $this->assertSame(80, $oee['diagnostics']['good_count']);
        $this->assertGreaterThan(0, $oee['diagnostics']['ideal_cycle_seconds']);

        $before = $this->fingerprint($workOrder->id);
        $layers = app(DemoSeedLayerRegistry::class)->ordered($options->only);
        $layer = end($layers);
        $this->assertNotFalse($layer);
        $layer->run((new DemoSeedContext($options))->forLayer($layer->key()));
        $this->assertSame($before, $this->fingerprint($workOrder->id), 'Rerunning production execution history must not duplicate or drift its output chain.');
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function fingerprint(int $workOrderId): array
    {
        return [
            'work_order' => DB::table('work_orders')->where('id', $workOrderId)->get()->map(static fn ($row): array => (array) $row)->all(),
            'outputs' => WorkOrderOutput::query()->where('work_order_id', $workOrderId)->orderBy('id')->get()->map(static fn ($row): array => $row->getAttributes())->all(),
            'movements' => StockMovement::query()->where('reference_type', 'work_order_output')
                ->whereIn('reference_id', WorkOrderOutput::query()->select('id')->where('work_order_id', $workOrderId))
                ->orderBy('id')->get()->map(static fn ($row): array => $row->getAttributes())->all(),
            'inspections' => Inspection::query()->where('entity_type', 'work_order')->where('entity_id', $workOrderId)
                ->orderBy('id')->get()->map(static fn ($row): array => $row->getAttributes())->all(),
        ];
    }
}
