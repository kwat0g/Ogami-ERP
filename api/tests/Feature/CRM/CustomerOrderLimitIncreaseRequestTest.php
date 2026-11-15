<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Modules\Accounting\Models\Customer;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\CRM\Models\Product;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerOrderLimitIncreaseRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(RolePermissionSeeder::class);
    }

    private function portalBuyer(): CustomerPortalUser
    {
        return CustomerPortalUser::create([
            'customer_id' => Customer::factory()->create()->id,
            'name' => 'Buyer',
            'email' => uniqid().'@test.local',
            'password' => 'password',
            'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', 'system_admin')->value('id')]);
    }

    private function productWithLimit(CustomerPortalUser $buyer): Product
    {
        $product = Product::factory()->create(['portal_visibility' => 'standard']);
        DB::table('customer_product_access')->insert([
            'customer_id' => $buyer->customer_id,
            'product_id' => $product->id,
            'minimum_order_quantity' => '5.000',
            'maximum_order_quantity' => '20.000',
        ]);

        return $product;
    }

    public function test_customer_requests_a_distinct_limit_change_without_changing_the_active_limit(): void
    {
        $buyer = $this->portalBuyer();
        $product = $this->productWithLimit($buyer);

        $response = $this->actingAs($buyer, 'customer_portal')->postJson('/api/v1/b2b/customer/order-limit-increase-requests', [
            'product_id' => $product->hash_id,
            'requested_maximum_quantity' => 35,
            'customer_notes' => 'Our monthly replenishment is increasing.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.current_maximum_quantity', '20.000')
            ->assertJsonPath('data.requested_maximum_quantity', '35.000');

        $this->assertDatabaseHas('customer_order_limit_increase_requests', [
            'customer_id' => $buyer->customer_id,
            'product_id' => $product->id,
            'status' => 'requested',
        ]);
        $this->assertSame('20.000', DB::table('customer_product_access')
            ->where('customer_id', $buyer->customer_id)->where('product_id', $product->id)
            ->value('maximum_order_quantity'));
    }

    public function test_sales_approval_applies_the_new_maximum_and_preserves_the_minimum(): void
    {
        $buyer = $this->portalBuyer();
        $product = $this->productWithLimit($buyer);
        $requestId = $this->actingAs($buyer, 'customer_portal')->postJson('/api/v1/b2b/customer/order-limit-increase-requests', [
            'product_id' => $product->hash_id,
            'requested_maximum_quantity' => 35,
            'customer_notes' => 'Please raise our order cap.',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->staff(), 'web')->postJson('/api/v1/crm/order-limit-increase-requests/'.$requestId.'/decision', [
            'decision' => 'approve',
            'version' => 1,
            'notes' => 'Approved after reviewing the account agreement.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertSame('35.000', DB::table('customer_product_access')
            ->where('customer_id', $buyer->customer_id)->where('product_id', $product->id)
            ->value('maximum_order_quantity'));
        $this->assertSame('5.000', DB::table('customer_product_access')
            ->where('customer_id', $buyer->customer_id)->where('product_id', $product->id)
            ->value('minimum_order_quantity'));
    }

    public function test_stale_limit_request_cannot_overwrite_a_cap_changed_since_submission(): void
    {
        $buyer = $this->portalBuyer();
        $product = $this->productWithLimit($buyer);
        $requestId = $this->actingAs($buyer, 'customer_portal')->postJson('/api/v1/b2b/customer/order-limit-increase-requests', [
            'product_id' => $product->hash_id,
            'requested_maximum_quantity' => 35,
            'customer_notes' => 'Please raise our order cap.',
        ])->assertCreated()->json('data.id');
        DB::table('customer_product_access')->where('customer_id', $buyer->customer_id)
            ->where('product_id', $product->id)->update(['maximum_order_quantity' => '25.000']);

        $this->actingAs($this->staff(), 'web')->postJson('/api/v1/crm/order-limit-increase-requests/'.$requestId.'/decision', [
            'decision' => 'approve',
            'version' => 1,
            'notes' => 'Approved after reviewing the account agreement.',
        ])->assertUnprocessable();

        $this->assertSame('25.000', DB::table('customer_product_access')
            ->where('customer_id', $buyer->customer_id)->where('product_id', $product->id)
            ->value('maximum_order_quantity'));
    }

    public function test_a_limit_increase_request_must_exceed_the_current_cap(): void
    {
        $buyer = $this->portalBuyer();
        $product = $this->productWithLimit($buyer);

        $this->actingAs($buyer, 'customer_portal')->postJson('/api/v1/b2b/customer/order-limit-increase-requests', [
            'product_id' => $product->hash_id,
            'requested_maximum_quantity' => 20,
            'customer_notes' => 'Please review.',
        ])->assertUnprocessable()->assertJsonValidationErrors('requested_maximum_quantity');

        $this->assertDatabaseCount('customer_order_limit_increase_requests', 0);
    }
}
