<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Common\Services\SettingsService;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Quality\Models\ItemQualityPlan;
use Carbon\CarbonImmutable;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QualityPlanLayerTest extends TestCase
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

    public function test_it_seeds_configured_incoming_plan_baselines_idempotently_with_audit_trail(): void
    {
        $asOf = '2026-11-14';
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse($asOf, 'Asia/Manila'),
            scale: 'small',
            seed: 20261114,
            only: 'quality-plans',
        );
        $runner = app(DemoSeedRunner::class);

        $runner->run($options);

        $settings = app(SettingsService::class);
        $eligibleTypes = $settings->get('quality.rollout.eligible_item_types', []);
        $sampleSize = $settings->requiredInt('quality.rollout.fixed_sample_size', 1, 1000);
        $items = Item::query()->active()->whereIn('item_type', $eligibleTypes)->with('category:id,name')->orderBy('code')->get();
        $plans = ItemQualityPlan::query()->whereIn('item_id', $items->modelKeys())->orderBy('item_id')->get();
        $automationRoles = $settings->get('system.automation.actor_roles', []);
        $actor = User::query()->where('is_active', true)
            ->whereHas('role', fn ($role) => $role->whereIn('slug', $automationRoles))
            ->orderBy('id')->firstOrFail();

        $this->assertNotEmpty($items);
        $this->assertCount($items->count(), $plans);
        $this->assertGreaterThan(0, $items->filter(fn (Item $item): bool => $item->category?->name === 'Resins')->count());
        $this->assertGreaterThan(0, $items->filter(fn (Item $item): bool => $item->category?->name !== 'Resins')->count());
        $auditRows = DB::table('audit_logs')
            ->where('model_type', (new ItemQualityPlan)->getMorphClass())
            ->whereIn('model_id', $plans->modelKeys())
            ->where('action', 'created')
            ->orderBy('model_id')
            ->get()
            ->keyBy(static fn ($row): int => (int) $row->model_id);
        $this->assertCount($plans->count(), $auditRows);

        foreach ($items as $item) {
            $plan = $plans->firstWhere('item_id', $item->id);
            $template = $item->category?->name === 'Resins' ? 'resin' : 'general';

            $this->assertNotNull($plan);
            $this->assertNull($plan->vendor_id);
            $this->assertSame('incoming', $plan->stage);
            $this->assertSame('fixed', $plan->sampling_method);
            $this->assertSame($sampleSize, $plan->fixed_sample_size);
            $this->assertNull($plan->aql_level);
            $this->assertSame(1, $plan->version);
            $this->assertTrue($plan->is_active);
            $this->assertSame($asOf, $plan->effective_from->toDateString());
            $this->assertSame($actor->id, $plan->created_by);
            $this->assertSame($asOf, $plan->created_at->toDateString());
            $this->assertSame($settings->get("quality.rollout.template.{$template}"), $plan->parameters);
            $this->assertStringContainsString('Synthetic demo', $plan->notes);

            $audit = $auditRows->get((int) $plan->id);
            $this->assertNotNull($audit);
            $this->assertSame($actor->id, (int) $audit->user_id);
            $this->assertSame('user', $audit->actor_type);
            $this->assertSame($asOf, CarbonImmutable::parse($audit->created_at)->toDateString());
        }

        $before = DB::table('item_quality_plans')->orderBy('id')->get()->toArray();
        $auditBefore = DB::table('audit_logs')
            ->where('model_type', (new ItemQualityPlan)->getMorphClass())
            ->whereIn('model_id', $plans->modelKeys())
            ->orderBy('model_id')
            ->get()
            ->toArray();
        $runner->run($options);
        $after = DB::table('item_quality_plans')->orderBy('id')->get()->toArray();
        $auditAfter = DB::table('audit_logs')
            ->where('model_type', (new ItemQualityPlan)->getMorphClass())
            ->whereIn('model_id', $plans->modelKeys())
            ->orderBy('model_id')
            ->get()
            ->toArray();

        $this->assertEquals($before, $after);
        $this->assertEquals($auditBefore, $auditAfter);
    }
}
