<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Common\Support\Money;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\CRM\Enums\PriceRequestStatus;
use App\Modules\CRM\Models\PriceAgreement;
use App\Modules\CRM\Models\PriceRequest;
use App\Modules\CRM\Models\Product;
use Carbon\CarbonImmutable;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrmPricingLayerTest extends TestCase
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

    public function test_it_seeds_current_pricing_and_every_price_request_state_through_domain_services(): void
    {
        $asOf = CarbonImmutable::parse('2026-11-15', 'Asia/Manila');
        $options = new DemoSeedOptions(
            asOf: $asOf,
            scale: 'small',
            seed: 20261115,
            only: 'crm-pricing',
        );
        $runner = app(DemoSeedRunner::class);
        $originalMailDriver = config('mail.default');

        $runner->run($options);

        $this->assertSame($originalMailDriver, config('mail.default'));
        $this->assertSame(16, Product::query()->whereNotNull('base_selling_price')->whereNotNull('minimum_selling_price')->count());
        $this->assertSame(3, PriceAgreement::query()->count());
        $this->assertSame(3, CustomerPortalUser::query()->count());
        $this->assertTrue(CustomerPortalUser::query()->get()->every(fn (CustomerPortalUser $portalUser): bool => $portalUser->is_active
            && ! $portalUser->must_change_password
            && str_ends_with((string) $portalUser->email, '.ogami-demo.test')
            && Hash::check('Demo-Only-Pass-2026!', (string) $portalUser->password)));

        $requests = PriceRequest::query()->with('product')->orderBy('id')->get();
        $this->assertSame([
            'accepted' => 3,
            'customer_cancelled' => 1,
            'expired' => 1,
            'offered' => 1,
            'pending_finance' => 1,
            'rejected' => 1,
            'requested' => 1,
            'sales_rejected' => 1,
            'withdrawn' => 1,
        ], $requests->groupBy(static fn (PriceRequest $request): string => $request->status->value)
            ->map(static fn ($rows): int => $rows->count())
            ->sortKeys()
            ->all());

        $salesRejected = $requests->first(fn (PriceRequest $request): bool => str_starts_with((string) $request->notes, 'DEMO-CRM-PRICING:SALES-REJECTED'));
        $this->assertInstanceOf(PriceRequest::class, $salesRejected);
        $this->assertTrue(Money::lt((string) $salesRejected->requested_price, (string) $salesRejected->product->minimum_selling_price));
        $portalUserIds = CustomerPortalUser::query()->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $this->assertTrue($requests->filter(static fn (PriceRequest $request): bool => $request->requested_by !== null)
            ->every(static fn (PriceRequest $request): bool => in_array((int) $request->requested_by, $portalUserIds, true)));

        foreach ($requests as $request) {
            if ($request->status === PriceRequestStatus::Expired) {
                $this->assertLessThan($asOf->toDateString(), $request->created_at->toDateString());
            } else {
                $this->assertSame($asOf->toDateString(), $request->created_at->toDateString());
            }
            $this->assertStringContainsString('DEMO-CRM-PRICING:', $request->notes);
        }

        foreach (PriceAgreement::query()->with(['customer', 'product'])->get() as $agreement) {
            $this->assertLessThanOrEqual($asOf->toDateString(), $agreement->effective_from->toDateString());
            $this->assertGreaterThanOrEqual($asOf->toDateString(), $agreement->effective_to->toDateString());
            $request = PriceRequest::query()->where('agreement_id', $agreement->id)->firstOrFail();
            $this->assertSame(PriceRequestStatus::Accepted, $request->status);
            $this->assertNotNull($request->reviewed_at);
            $this->assertNotNull($request->responded_at);
            $this->assertGreaterThanOrEqual($request->created_at, $request->reviewed_at);
            $this->assertGreaterThanOrEqual($request->reviewed_at, $request->responded_at);
        }

        $financeUser = User::query()->whereHas('role', fn ($query) => $query->where('slug', 'finance_officer'))->firstOrFail();
        $this->assertTrue($financeUser->hasPermission('crm.price_agreements.approve'));

        $before = $this->fingerprint();
        $runner->run($options);
        $this->assertSame($before, $this->fingerprint());
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function fingerprint(): array
    {
        return [
            'products' => DB::table('products')->orderBy('id')->get(['id', 'base_selling_price', 'minimum_selling_price', 'created_at', 'updated_at'])->map(static fn (object $row): array => (array) $row)->all(),
            'agreements' => DB::table('product_price_agreements')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            'requests' => DB::table('price_requests')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            'portal_users' => DB::table('customer_portal_users')->orderBy('id')->get(['id', 'customer_id', 'name', 'email', 'is_active', 'is_authorized_signatory', 'must_change_password', 'password_changed_at', 'created_at', 'updated_at'])->map(static fn (object $row): array => (array) $row)->all(),
            'notifications' => DB::table('notifications')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            'audits' => DB::table('audit_logs')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
        ];
    }
}
