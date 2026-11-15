<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Common\DemoData\DemoSideEffectScope;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Models\RequestForQuote;
use App\Modules\Purchasing\Models\RfqDocument;
use App\Modules\Purchasing\Models\SupplierQuote;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\PurchasingHistoryLayer;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchasingHistoryLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
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

    public function test_it_creates_reconciled_pr_rfq_quote_and_po_workflows_through_domain_services(): void
    {
        $asOf = CarbonImmutable::parse('2026-11-15', 'Asia/Manila');
        $options = new DemoSeedOptions(
            asOf: $asOf,
            scale: 'small',
            seed: 20261115,
            only: 'purchasing-history',
        );
        $runner = app(DemoSeedRunner::class);

        $runner->run($options);

        $this->assertContains('draft', array_keys($this->demoStatuses(PurchaseRequest::query()->where('reason', 'like', 'DEMO-PURCHASING:%'))));
        foreach (['pending', 'approved', 'rejected', 'converted', 'cancelled'] as $status) {
            $this->assertArrayHasKey($status, $this->demoStatuses(PurchaseRequest::query()->where('reason', 'like', 'DEMO-PURCHASING:%')));
        }

        $demoRequestIds = PurchaseRequest::query()->where('reason', 'like', 'DEMO-PURCHASING:%')->select('id');
        $poStatuses = $this->demoStatuses(PurchaseOrder::query()->whereIn('purchase_request_id', $demoRequestIds));
        foreach (['draft', 'pending_approval', 'approved', 'sent', 'acknowledged', 'supplier_proposed', 'supplier_declined', 'cancelled'] as $status) {
            $this->assertArrayHasKey($status, $poStatuses);
        }

        $rfqStatuses = $this->demoStatuses(RequestForQuote::query()->where('title', 'like', 'DEMO-PURCHASING:%'));
        foreach (['draft', 'open', 'closed', 'awarded', 'cancelled'] as $status) {
            $this->assertArrayHasKey($status, $rfqStatuses);
        }

        $quoteStatuses = $this->demoStatuses(SupplierQuote::query()->where('notes', 'like', 'DEMO-PURCHASING:%'));
        foreach (['draft', 'submitted', 'awarded'] as $status) {
            $this->assertArrayHasKey($status, $quoteStatuses);
        }
        if (SupplierQuote::query()->where('notes', 'like', 'DEMO-PURCHASING:QUOTE:RFQ-AWARDED:ALTERNATE')->exists()) {
            $this->assertArrayHasKey('not_awarded', $quoteStatuses);
        }

        $quoteDocuments = RfqDocument::query()->where('document_type', 'quotation_pdf')->get();
        $this->assertCount(2, $quoteDocuments);
        $this->assertCount(2, $quoteDocuments->filter(static fn (RfqDocument $document): bool => $document->supplier_quote_id !== null));
        $this->assertTrue($quoteDocuments->every(static fn (RfqDocument $document): bool => Storage::disk('local')->exists($document->file_path)));
        $this->assertTrue($quoteDocuments->every(static fn (RfqDocument $document): bool => str_starts_with(
            Storage::disk('local')->get($document->file_path),
            '%PDF-1.4',
        )));

        $approvedPr = PurchaseRequest::query()->where('reason', 'DEMO-PURCHASING:PO-PR:PO-APPROVED')->firstOrFail();
        $approvedPo = PurchaseOrder::query()->where('purchase_request_id', $approvedPr->id)->firstOrFail();
        $this->assertSame('manual_required', DB::table('supplier_order_dispatches')->where('purchase_order_id', $approvedPo->id)->value('status'));

        $before = $this->fingerprint();
        app(DemoSideEffectScope::class)->run(fn () => app(PurchasingHistoryLayer::class)->run(
            (new DemoSeedContext($options))->forLayer('purchasing-history'),
        ));
        $this->assertSame($before, $this->fingerprint());
    }

    /** @param Builder<Model> $query
     * @return array<string, int>
     */
    private function demoStatuses($query): array
    {
        return $query->reorder()->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')->orderBy('status')->pluck('aggregate', 'status')
            ->map(static fn ($count): int => (int) $count)->all();
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function fingerprint(): array
    {
        return [
            'requests' => DB::table('purchase_requests')->where('reason', 'like', 'DEMO-PURCHASING:%')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'request_lines' => DB::table('purchase_request_items')->whereIn('purchase_request_id', PurchaseRequest::query()->where('reason', 'like', 'DEMO-PURCHASING:%')->select('id'))->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'orders' => DB::table('purchase_orders')->whereIn('purchase_request_id', PurchaseRequest::query()->where('reason', 'like', 'DEMO-PURCHASING:PO-PR:%')->select('id'))->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'order_lines' => DB::table('purchase_order_items')->whereIn('purchase_order_id', PurchaseOrder::query()->whereIn('purchase_request_id', PurchaseRequest::query()->where('reason', 'like', 'DEMO-PURCHASING:PO-PR:%')->select('id'))->select('id'))->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'rfqs' => DB::table('request_for_quotes')->where('title', 'like', 'DEMO-PURCHASING:%')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'quotes' => DB::table('supplier_quotes')->where('notes', 'like', 'DEMO-PURCHASING:%')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'quote_documents' => DB::table('rfq_documents')->where('document_type', 'quotation_pdf')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'supplier_responses' => DB::table('purchase_order_responses')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'dispatches' => DB::table('supplier_order_dispatches')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
            'audits' => DB::table('audit_logs')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all(),
        ];
    }
}
