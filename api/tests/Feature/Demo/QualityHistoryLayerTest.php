<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedLayerRegistry;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\CRM\Enums\ComplaintStatus;
use App\Modules\CRM\Models\CustomerComplaint;
use App\Modules\Inventory\Models\GoodsReceiptNote;
use App\Modules\Quality\Enums\NcrActionType;
use App\Modules\Quality\Enums\NcrStatus;
use App\Modules\Quality\Models\Inspection;
use App\Modules\Quality\Models\NonConformanceReport;
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

class QualityHistoryLayerTest extends TestCase
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

    public function test_it_seeds_complaints_and_corrective_actions_through_domain_services_idempotently(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-12-01', 'Asia/Manila'),
            scale: 'small',
            seed: 20261201,
            only: 'quality-history',
        );
        $runner = app(DemoSeedRunner::class);

        $runner->run($options);

        $complaints = CustomerComplaint::query()->where('description', 'like', 'DEMO-QUALITY:%');
        $statuses = (clone $complaints)->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')->pluck('aggregate', 'status')->all();
        foreach ([ComplaintStatus::Open, ComplaintStatus::Resolved, ComplaintStatus::Closed] as $status) {
            $this->assertArrayHasKey($status->value, $statuses, "Expected a demo customer complaint in {$status->value} status.");
        }

        $asOf = CarbonImmutable::parse('2026-12-01 00:00:00', 'Asia/Manila');
        $withinClock = (clone $complaints)->where('description', 'like', 'DEMO-QUALITY:OPEN-WITHIN-CLOCK%')->firstOrFail();
        $overdue = (clone $complaints)->where('description', 'like', 'DEMO-QUALITY:OPEN-OVERDUE%')->firstOrFail();
        $this->assertTrue($withinClock->d3_due_at->greaterThan($asOf));
        $this->assertTrue($overdue->d3_due_at->lessThan($asOf));
        $overdueCreatedAt = CarbonImmutable::parse($overdue->created_at)->setTimezone('Asia/Manila');
        $this->assertFalse($overdueCreatedAt->isWeekend());
        $this->assertFalse(Holiday::query()->whereDate('date', $overdueCreatedAt->toDateString())->exists());
        $this->assertFalse(Holiday::query()->where('is_recurring', true)
            ->whereRaw("to_char(date, 'MM-DD') = ?", [$overdueCreatedAt->format('m-d')])->exists());

        $resolvedComplaint = (clone $complaints)->where('description', 'like', 'DEMO-QUALITY:RESOLVED%')->firstOrFail();
        $this->assertSame(ComplaintStatus::Resolved, $resolvedComplaint->status);
        $this->assertSame(NcrStatus::Closed, $resolvedComplaint->ncr->status);
        $this->assertNotNull($resolvedComplaint->eightDReport->finalized_at);

        $closedComplaint = (clone $complaints)->where('description', 'like', 'DEMO-QUALITY:CLOSED%')->firstOrFail();
        $this->assertSame(NcrStatus::Closed, $closedComplaint->ncr->status);
        $this->assertNotNull($closedComplaint->eightDReport->finalized_at);

        $overdueNcr = $overdue->ncr;
        $actionTypes = DB::table('ncr_actions')->where('ncr_id', $overdueNcr->id)->pluck('action_type')->all();
        foreach (NcrActionType::cases() as $actionType) {
            $this->assertContains($actionType->value, $actionTypes);
        }
        $this->assertTrue($overdueNcr->actions()->where('due_date', '<', $asOf->toDateString())->whereNull('verified_at')->exists());

        $partialGrn = GoodsReceiptNote::query()->where('remarks', 'DEMO-RECEIVING:GRN-PARTIAL-ACCEPT')->firstOrFail();
        $incomingInspection = Inspection::query()->where('stage', 'incoming')->where('entity_type', 'grn')
            ->where('entity_id', $partialGrn->id)->firstOrFail();
        $incomingNcr = NonConformanceReport::query()->where('inspection_id', $incomingInspection->id)->firstOrFail();
        foreach ($incomingNcr->actions()->where('description', 'like', 'DEMO-QUALITY:INCOMING:%')->get() as $action) {
            $this->assertGreaterThanOrEqual($incomingNcr->created_at, $action->performed_at);
        }

        $before = $this->fingerprint();
        $layers = app(DemoSeedLayerRegistry::class)->ordered($options->only);
        $qualityLayer = end($layers);
        if ($qualityLayer === false) {
            throw new \RuntimeException('Quality history was not registered for the focused replay.');
        }
        $qualityLayer->run((new DemoSeedContext($options))->forLayer($qualityLayer->key()));
        $this->assertSame($before, $this->fingerprint(), 'Rerunning the layer must not create complaint, NCR, or action drift.');
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function fingerprint(): array
    {
        $complaintIds = CustomerComplaint::query()->where('description', 'like', 'DEMO-QUALITY:%')->select('id');
        $ncrIds = NonConformanceReport::query()->whereIn('complaint_id', $complaintIds)->select('id');

        return [
            'complaints' => DB::table('customer_complaints')->whereIn('id', $complaintIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'reports' => DB::table('complaint_8d_reports')->whereIn('complaint_id', $complaintIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'ncrs' => DB::table('non_conformance_reports')->whereIn('id', $ncrIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'actions' => DB::table('ncr_actions')->whereIn('ncr_id', $ncrIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
        ];
    }
}
