<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Common\Models\ApprovalDelegation;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Purchasing\Enums\SupplierListingStatus;
use App\Modules\Purchasing\Events\SupplierCatalogChanged;
use App\Modules\Purchasing\Models\ApprovedSupplier;
use App\Modules\Purchasing\Models\SupplierItemListing;
use Carbon\CarbonImmutable;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierListingLayerTest extends TestCase
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

    public function test_it_seeds_supplier_listing_states_and_is_idempotent_through_domain_services(): void
    {
        $unrelatedCategory = ItemCategory::factory()->create([
            'name' => 'E2E Adjustment Fixture Category',
            'item_type' => 'raw_material',
        ]);
        $unrelatedItem = Item::factory()->create([
            'code' => 'E2E-ADJ-fixture',
            'name' => 'Existing E2E adjustment fixture',
            'category_id' => $unrelatedCategory->id,
            'item_type' => 'raw_material',
            'standard_cost' => '100.0000',
        ]);
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            scale: 'small',
            seed: 20261114,
            only: 'supplier-listings',
        );
        $runner = app(DemoSeedRunner::class);

        $runner->run($options);

        $this->assertSame(0, SupplierItemListing::query()->where('item_id', $unrelatedItem->id)->count());
        $listings = SupplierItemListing::query()->orderBy('supplier_item_code')->get();
        $this->assertCount(25, $listings);
        $this->assertSame([
            'approved' => 22,
            'pending' => 1,
            'rejected' => 1,
            'superseded' => 1,
        ], $listings->groupBy(static fn (SupplierItemListing $listing): string => $listing->status->value)
            ->map(static fn ($group): int => $group->count())
            ->sortKeys()
            ->all());
        $this->assertSame(22, ApprovedSupplier::query()->count());

        $reviewer = User::query()->where('email', 'agnas.shiernalhen@gmail.com')->firstOrFail();
        $olderRevision = $listings->firstWhere('supplier_item_code', 'D-INABATA-RM-001-R1');
        $currentRevision = $listings->firstWhere('supplier_item_code', 'D-INABATA-RM-001-R2');
        $rejected = $listings->firstWhere('supplier_item_code', 'D-INABATA-RM-003-REJECTED');
        $pending = $listings->firstWhere('supplier_item_code', 'D-INABATA-RM-005-LIVE');

        $this->assertSame(SupplierListingStatus::Superseded, $olderRevision->status);
        $this->assertSame(SupplierListingStatus::Approved, $currentRevision->status);
        $this->assertSame($reviewer->id, $olderRevision->reviewed_by);
        $this->assertSame($reviewer->id, $currentRevision->reviewed_by);
        $this->assertSame(SupplierListingStatus::Rejected, $rejected->status);
        $this->assertSame($reviewer->id, $rejected->reviewed_by);
        $this->assertSame(SupplierListingStatus::Pending, $pending->status);
        $this->assertNull($pending->reviewed_by);
        $this->assertLessThanOrEqual('2026-11-14', $pending->submitted_at->toDateString());
        foreach ([$olderRevision, $currentRevision, $rejected] as $reviewedListing) {
            $this->assertSame($reviewedListing->submitted_at->toDateString(), $reviewedListing->created_at->toDateString());
            $this->assertGreaterThan($reviewedListing->submitted_at, $reviewedListing->reviewed_at);
            $this->assertGreaterThanOrEqual($reviewedListing->reviewed_at, $reviewedListing->updated_at);
            $this->assertLessThanOrEqual('2026-11-14', $reviewedListing->reviewed_at->toDateString());
        }
        $this->assertSame($pending->submitted_at->toDateString(), $pending->created_at->toDateString());
        $this->assertSame($pending->submitted_at->toDateString(), $pending->updated_at->toDateString());

        $item = Item::query()->where('code', 'RM-001')->firstOrFail();
        $vendor = Vendor::query()->where('name', 'Inabata Philippines Inc.')->firstOrFail();
        $approved = ApprovedSupplier::query()->where('item_id', $item->id)->where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertSame($currentRevision->id, $approved->supplier_listing_id);
        $this->assertSame('123.60', (string) $approved->last_price);
        $this->assertSame(49, DB::table('event_outbox')->where('event_type', SupplierCatalogChanged::class)->count());
        $actualReviewerIds = DB::table('notifications')
            ->where('type', 'supplier_listing_submitted')
            ->where('notifiable_type', User::class)
            ->whereRaw("data::jsonb ->> 'subject_id' = ?", [(string) $pending->id])
            ->orderBy('notifiable_id')
            ->pluck('notifiable_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $expectedReviewerIds = ApprovalDelegation::approvalAudience('purchasing.supplier_listings.review')
            ->filter(static fn (User $user): bool => $user->hasPermission('purchasing.supplier_listings.view'))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        sort($actualReviewerIds);
        $this->assertNotEmpty($actualReviewerIds);
        $this->assertSame($expectedReviewerIds, $actualReviewerIds);

        $before = [
            'listings' => DB::table('supplier_item_listings')->orderBy('id')->get()->toArray(),
            'approved_suppliers' => DB::table('approved_suppliers')->orderBy('id')->get()->toArray(),
            'catalog_events' => DB::table('event_outbox')->where('event_type', SupplierCatalogChanged::class)->orderBy('id')->get()->toArray(),
            'notices' => DB::table('notifications')->where('type', 'supplier_listing_submitted')->orderBy('id')->get()->toArray(),
        ];

        $runner->run($options);

        $after = [
            'listings' => DB::table('supplier_item_listings')->orderBy('id')->get()->toArray(),
            'approved_suppliers' => DB::table('approved_suppliers')->orderBy('id')->get()->toArray(),
            'catalog_events' => DB::table('event_outbox')->where('event_type', SupplierCatalogChanged::class)->orderBy('id')->get()->toArray(),
            'notices' => DB::table('notifications')->where('type', 'supplier_listing_submitted')->orderBy('id')->get()->toArray(),
        ];
        $this->assertEquals($before, $after);
    }
}
