<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedOptions;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Purchasing\Events\SupplierCatalogChanged;
use App\Modules\Purchasing\Services\SupplierListingService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoHistoricalNotificationSuppressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingsSeeder::class]);
    }

    public function test_historical_supplier_submission_keeps_catalog_event_but_suppresses_its_inbox_notice(): void
    {
        $reviewer = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'purchasing_officer')->value('id'),
        ]);
        $vendor = Vendor::factory()->create();
        $historicalItem = Item::factory()->create(['is_active' => true]);
        $pendingItem = Item::factory()->create(['is_active' => true]);
        $service = app(SupplierListingService::class);

        $currentListing = $service->submit($vendor->id, [
            'item_id' => $historicalItem->id,
            'supplier_item_code' => 'SUP-CURRENT-01',
            'price' => '100.00',
            'lead_time_days' => 5,
        ]);
        $this->assertSame('pending', $currentListing->status->value);
        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $reviewer->id)
            ->count());

        $context = new DemoSeedContext(new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-05-01', 'Asia/Manila'),
            scale: 'small',
            seed: 20260501,
        ));
        $historicalListing = $context->withoutNotifications(fn () => $context->at(
            $context->options->asOf,
            fn () => $service->submit($vendor->id, [
                'item_id' => $pendingItem->id,
                'supplier_item_code' => 'SUP-HISTORY-01',
                'price' => '95.00',
                'lead_time_days' => 7,
            ]),
        ));

        $this->assertSame('pending', $historicalListing->status->value);
        $this->assertSame('2026-05-01', $historicalListing->submitted_at->toDateString());
        $this->assertSame('2026-05-01', $historicalListing->created_at->toDateString());
        $this->assertDatabaseCount('supplier_item_listings', 2);
        $this->assertDatabaseHas('supplier_item_listings', [
            'id' => $historicalListing->id,
            'status' => 'pending',
        ]);
        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $reviewer->id)
            ->count());
        $this->assertSame(2, DB::table('event_outbox')
            ->where('event_type', SupplierCatalogChanged::class)
            ->count());
    }
}
