<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\CRM\Models\CustomerProductWarrantyOption;
use App\Modules\CRM\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPartnerCatalogLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_safe_customer_and_vendor_catalogs_idempotently(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            scale: 'small',
            seed: 20261114,
            only: 'business-partners',
        );

        $runner = app(DemoSeedRunner::class);
        $runner->run($options);

        $this->assertSame(3, Customer::query()->count());
        $this->assertSame(3, Vendor::query()->count());
        $this->assertSame(3, Customer::query()->where('is_active', true)->count());
        $this->assertSame(3, Vendor::query()->where('is_active', true)->count());

        $customers = Customer::query()->with(['portalProducts', 'warrantyOptions'])->orderBy('code')->get();
        foreach ($customers as $customer) {
            $this->assertMatchesRegularExpression('/\A[a-z0-9.]+@gmail\.com\z/', (string) $customer->email);
            $this->assertNotFalse(filter_var($customer->email, FILTER_VALIDATE_EMAIL));
            $this->assertMatchesRegularExpression('/\ACUS-2026-\d{4}\z/', (string) $customer->code);
            $this->assertSame('2026-11-14', $customer->created_at->toDateString());
            $this->assertNull($customer->tin, 'Demo customer records must not contain government tax IDs.');
            $this->assertNotEmpty($customer->portalProducts);
            $this->assertNotEmpty($customer->warrantyOptions);
            $this->assertCount($customer->portalProducts->count(), $customer->warrantyOptions);
            $this->assertStringStartsWith('+63 000 555 ', (string) $customer->phone);
            $this->assertTrue($customer->portalProducts->every(fn (Product $product): bool => $product->isAvailableToCustomer((int) $customer->id)));
        }

        foreach (Vendor::query()->get() as $vendor) {
            $this->assertMatchesRegularExpression('/\A[a-z0-9.]+@gmail\.com\z/', (string) $vendor->email);
            $this->assertNotFalse(filter_var($vendor->email, FILTER_VALIDATE_EMAIL));
            $this->assertSame('2026-11-14', $vendor->created_at->toDateString());
            $this->assertNull($vendor->tin, 'Demo vendor records must not contain government tax IDs.');
            $this->assertStringStartsWith('+63 000 555 ', (string) $vendor->phone);
        }

        $customerCodes = Customer::query()->orderBy('code')->pluck('code')->all();
        $productAccessCount = \DB::table('customer_product_access')->count();
        $warrantyCount = CustomerProductWarrantyOption::query()->count();
        $runner->run($options);

        $this->assertSame(3, Customer::query()->count());
        $this->assertSame(3, Vendor::query()->count());
        $this->assertSame($customerCodes, Customer::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame($productAccessCount, \DB::table('customer_product_access')->count());
        $this->assertSame($warrantyCount, CustomerProductWarrantyOption::query()->count());
    }

    public function test_demo_profile_has_approved_plan_volume_and_inactive_partners(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            scale: 'demo',
            seed: 20261114,
            only: 'business-partners',
        );

        app(DemoSeedRunner::class)->run($options);

        $this->assertSame(10, Customer::query()->count());
        $this->assertSame(10, Customer::query()->where('is_active', true)->count());
        $this->assertSame(6, Vendor::query()->count());
        $this->assertSame(6, Vendor::query()->where('is_active', true)->count());

        foreach (Customer::query()->get(['email', 'tin']) as $customer) {
            $this->assertNotFalse(filter_var($customer->email, FILTER_VALIDATE_EMAIL));
            $this->assertStringEndsWith('@gmail.com', (string) $customer->email);
            $this->assertNull($customer->tin);
        }
        foreach (Vendor::query()->get(['email', 'tin']) as $vendor) {
            $this->assertNotFalse(filter_var($vendor->email, FILTER_VALIDATE_EMAIL));
            $this->assertStringEndsWith('@gmail.com', (string) $vendor->email);
            $this->assertNull($vendor->tin);
        }
    }
}
