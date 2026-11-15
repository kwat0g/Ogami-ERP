<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Models\Product;
use App\Modules\Quality\Models\InspectionSpec;
use App\Modules\Quality\Models\InspectionSpecItem;
use App\Modules\Quality\Models\InspectionSpecRevision;
use App\Modules\Quality\Models\NcrTemplate;
use Carbon\CarbonImmutable;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QualityInspectionReferencesLayerTest extends TestCase
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

    public function test_it_seeds_synthetic_product_inspection_specs_and_ncr_templates_idempotently(): void
    {
        config(['inspection.checklist_capture_enabled' => true]);
        $asOf = '2026-11-15';
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse($asOf, 'Asia/Manila'),
            scale: 'small',
            seed: 20261115,
            only: 'quality-inspection-specs',
        );
        $runner = app(DemoSeedRunner::class);

        $runner->run($options);

        $products = Product::query()->whereIn('part_number', [
            'WB-001', 'PC-001', 'RC-001', 'BB-001', 'BU-001', 'CH-001', 'FC-001', 'MH-001',
            'HS-001', 'AD-001', 'TC-001', 'HR-001', 'WB-002', 'PC-002', 'RC-002', 'BC-001',
        ])->orderBy('part_number')->get();
        $specs = InspectionSpec::query()->whereIn('product_id', $products->modelKeys())
            ->with(['items', 'currentRevision'])
            ->orderBy('product_id')
            ->get();
        $revisions = InspectionSpecRevision::query()->whereIn('inspection_spec_id', $specs->modelKeys())->get();
        $items = InspectionSpecItem::query()->whereIn('inspection_spec_id', $specs->modelKeys())->orderBy('inspection_spec_id')->orderBy('sort_order')->get();
        $templates = NcrTemplate::query()->where('name', 'like', 'DEMO —%')->orderBy('name')->get();
        $qualityActor = User::query()->whereHas('role', fn ($query) => $query->where('slug', 'qc_inspector'))->firstOrFail();

        $this->assertCount(16, $products);
        $this->assertCount(16, $specs);
        $this->assertCount(16, $revisions);
        $this->assertCount(80, $items);
        $this->assertCount(8, $templates);
        $this->assertSame(['customer_complaint', 'inspection_fail'], $templates->pluck('source')->unique()->sort()->values()->all());
        $this->assertEqualsCanonicalizing(['low', 'medium', 'high', 'critical'], $templates->pluck('severity')->unique()->all());
        $this->assertSame(1, $templates->where('is_active', false)->count());
        $this->assertGreaterThan(0, $templates->whereNotNull('product_id')->count());
        $this->assertGreaterThan(0, $templates->whereNull('product_id')->count());

        foreach ($specs as $spec) {
            $this->assertTrue($spec->is_active);
            $this->assertSame(1, $spec->version);
            $this->assertSame($qualityActor->id, $spec->created_by);
            $this->assertSame($asOf, $spec->created_at->toDateString());
            $this->assertSame($asOf, $spec->updated_at->toDateString());
            $this->assertSame('measurement', $spec->currentRevision->capture_method);
            $this->assertSame(5, $spec->items->count());
            $this->assertSame($qualityActor->id, $spec->currentRevision->created_by);
            $this->assertSame($asOf, $spec->currentRevision->created_at->toDateString());
        }

        $representativeProduct = $products->firstWhere('part_number', 'WB-001');
        $representativeSpec = $specs->firstWhere('product_id', $representativeProduct?->id);
        $sourceDimensions = $representativeProduct?->specifications['dimensions'] ?? [];
        $this->assertNotNull($representativeProduct);
        $this->assertNotNull($representativeSpec);
        $this->assertMatchesRegularExpression('/±\s*(\d+(?:\.\d+)?)/u', (string) ($representativeProduct->specifications['tolerances'] ?? ''));
        preg_match('/±\s*(\d+(?:\.\d+)?)/u', (string) $representativeProduct->specifications['tolerances'], $toleranceMatch);
        $sourceTolerance = (float) ($toleranceMatch[1] ?? 0);
        foreach ([
            'length' => 'Overall length',
            'width' => 'Overall width',
            'height' => 'Overall height',
        ] as $dimension => $parameterName) {
            $item = $representativeSpec->items->firstWhere('parameter_name', $parameterName);
            $nominal = (float) $sourceDimensions[$dimension];
            $this->assertNotNull($item);
            $this->assertSame(number_format($nominal, 4, '.', ''), $item->nominal_value);
            $this->assertSame(number_format($nominal - $sourceTolerance, 4, '.', ''), $item->tolerance_min);
            $this->assertSame(number_format($nominal + $sourceTolerance, 4, '.', ''), $item->tolerance_max);
        }

        $this->assertTrue($items->every(fn (InspectionSpecItem $item): bool => trim((string) $item->acceptance_criterion) !== ''
            && $item->created_at->toDateString() === $asOf
            && in_array($item->parameter_type->value, ['dimensional', 'visual', 'functional'], true)));
        $this->assertTrue($templates->every(fn (NcrTemplate $template): bool => $template->created_by === $qualityActor->id
            && $template->created_at->toDateString() === $asOf
            && $template->updated_at->toDateString() === $asOf
            && str_contains((string) $template->notes, 'Synthetic demo')));

        $before = [
            DB::table('inspection_specs')->orderBy('id')->get()->toArray(),
            DB::table('inspection_spec_revisions')->orderBy('id')->get()->toArray(),
            DB::table('inspection_spec_items')->orderBy('id')->get()->toArray(),
            DB::table('ncr_templates')->orderBy('id')->get()->toArray(),
            DB::table('audit_logs')->whereIn('model_type', [
                (new InspectionSpec)->getMorphClass(),
                (new InspectionSpecRevision)->getMorphClass(),
                (new InspectionSpecItem)->getMorphClass(),
                (new NcrTemplate)->getMorphClass(),
            ])->orderBy('id')->get()->toArray(),
        ];
        $runner->run($options);
        $after = [
            DB::table('inspection_specs')->orderBy('id')->get()->toArray(),
            DB::table('inspection_spec_revisions')->orderBy('id')->get()->toArray(),
            DB::table('inspection_spec_items')->orderBy('id')->get()->toArray(),
            DB::table('ncr_templates')->orderBy('id')->get()->toArray(),
            DB::table('audit_logs')->whereIn('model_type', [
                (new InspectionSpec)->getMorphClass(),
                (new InspectionSpecRevision)->getMorphClass(),
                (new InspectionSpecItem)->getMorphClass(),
                (new NcrTemplate)->getMorphClass(),
            ])->orderBy('id')->get()->toArray(),
        ];

        $this->assertEquals($before, $after);
    }
}
