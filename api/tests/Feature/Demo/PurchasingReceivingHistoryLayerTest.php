<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedLayerRegistry;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Accounting\Enums\BillStatus;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Inventory\Enums\GrnStatus;
use App\Modules\Inventory\Models\GoodsReceiptNote;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
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

class PurchasingReceivingHistoryLayerTest extends TestCase
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

    public function test_it_seeds_receiving_qc_and_payables_through_domain_services_idempotently(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-12-01', 'Asia/Manila'),
            scale: 'small',
            seed: 20261115,
            only: 'purchasing-receiving-history',
        );
        $runner = app(DemoSeedRunner::class);

        $runner->run($options);

        $receipts = GoodsReceiptNote::query()->where('remarks', 'like', 'OGI-RECEIVING:%');
        $receiptStatuses = (clone $receipts)->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')->pluck('aggregate', 'status')->all();
        foreach (array_diff(GrnStatus::values(), [GrnStatus::Draft->value]) as $status) {
            $this->assertArrayHasKey($status, $receiptStatuses, "Expected a demo GRN in {$status} status.");
        }
        $partialAcceptance = (clone $receipts)->where('remarks', 'OGI-RECEIVING:GRN-PARTIAL-ACCEPT')->firstOrFail();
        $this->assertSame('2026-11-27', $partialAcceptance->received_date->toDateString(), 'The Nov 30 seeded holiday must not be selected as an operating day.');
        $sentRequestId = PurchaseRequest::query()->where('reason', 'OGI-PURCHASING:PO-PR:PO-SENT')->value('id');
        $sentPoIds = PurchaseOrder::query()->where('purchase_request_id', $sentRequestId)->select('id');
        $this->assertTrue(DB::table('goods_receipt_notes')->whereIn('purchase_order_id', $sentPoIds)->where('status', GrnStatus::Draft->value)->exists());

        $billStatuses = Bill::query()
            ->whereIn('goods_receipt_note_id', $receipts->select('id'))
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')->pluck('aggregate', 'status')->all();
        foreach ([BillStatus::Draft, BillStatus::Unpaid, BillStatus::Partial, BillStatus::Paid, BillStatus::Cancelled] as $status) {
            $this->assertArrayHasKey($status->value, $billStatuses, "Expected a demo supplier bill in {$status->value} status.");
        }

        $before = $this->fingerprint();
        $layers = app(DemoSeedLayerRegistry::class)->ordered($options->only);
        $receivingLayer = end($layers);
        if ($receivingLayer === false) {
            throw new \RuntimeException('Receiving history was not registered for the focused replay.');
        }
        $receivingLayer->run((new DemoSeedContext($options))->forLayer($receivingLayer->key()));
        $this->assertSame($before, $this->fingerprint(), 'Rerunning the layer must not create transaction or audit drift.');
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function fingerprint(): array
    {
        $receiptIds = GoodsReceiptNote::query()->where('remarks', 'like', 'OGI-RECEIVING:%')->select('id');

        return [
            'receipts' => DB::table('goods_receipt_notes')->whereIn('id', $receiptIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'receipt_lines' => DB::table('grn_items')->whereIn('goods_receipt_note_id', $receiptIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'bills' => DB::table('bills')->whereIn('goods_receipt_note_id', $receiptIds)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'payments' => DB::table('bill_payments')->whereIn('bill_id', Bill::query()->whereIn('goods_receipt_note_id', $receiptIds)->select('id'))->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
        ];
    }
}
