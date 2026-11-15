<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Exceptions\BusinessRuleException;
use App\Common\Support\Money;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\B2B\Services\PortalAccessService;
use App\Modules\B2B\Services\PortalPasswordResetService;
use App\Modules\CRM\Enums\PriceRequestStatus;
use App\Modules\CRM\Enums\PricingMethod;
use App\Modules\CRM\Models\PriceAgreement;
use App\Modules\CRM\Models\PriceRequest;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Services\PriceNegotiationService;
use App\Modules\CRM\Services\ProductService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/** Creates cost-reviewed product prices and realistic customer negotiations. */
final class CrmPricingLayer extends DemoSeeder
{
    private const MARKER = 'DEMO-CRM-PRICING:';

    /** @var list<array{name:string,email_prefix:string}> */
    private const CUSTOMER_CONTACTS = [
        ['name' => 'Mikaela Santos', 'email_prefix' => 'mikaela.santos'],
        ['name' => 'Paolo Bautista', 'email_prefix' => 'paolo.bautista'],
        ['name' => 'Angelica Dizon', 'email_prefix' => 'angelica.dizon'],
        ['name' => 'Rafael Mendoza', 'email_prefix' => 'rafael.mendoza'],
        ['name' => 'Camille Reyes', 'email_prefix' => 'camille.reyes'],
        ['name' => 'Noel Garcia', 'email_prefix' => 'noel.garcia'],
        ['name' => 'Teresa Navarro', 'email_prefix' => 'teresa.navarro'],
        ['name' => 'Miguel Aquino', 'email_prefix' => 'miguel.aquino'],
        ['name' => 'Leah Mercado', 'email_prefix' => 'leah.mercado'],
        ['name' => 'Daniel Villanueva', 'email_prefix' => 'daniel.villanueva'],
        ['name' => 'Rina Flores', 'email_prefix' => 'rina.flores'],
        ['name' => 'Emmanuel Cruz', 'email_prefix' => 'emmanuel.cruz'],
    ];

    public function __construct(
        private readonly ProductService $products,
        private readonly PriceNegotiationService $negotiations,
        private readonly PortalAccessService $portalAccess,
        private readonly PortalPasswordResetService $passwordResets,
    ) {}

    public function key(): string
    {
        return 'crm-pricing';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['CRM', 'B2B', 'Accounting', 'Auth', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['bom-routing-catalog', 'business-partners', 'workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $sales = $this->account($context, 'sales.officer@accounts.ogami-demo.test', 'crm.price_agreements.propose');
        $finance = $this->account($context, 'finance.officer@accounts.ogami-demo.test', 'crm.price_agreements.approve');
        $portalAdministrator = $this->account($context, 'system.admin@accounts.ogami-demo.test', 'b2b.portal_access.manage');
        if ((int) $sales->id === (int) $finance->id) {
            throw new RuntimeException('CRM pricing requires separate Sales and Finance users for its proposal and review steps.');
        }

        $catalog = Product::query()->active()->orderBy('part_number')->get();
        if ($catalog->isEmpty()) {
            throw new RuntimeException('CRM pricing requires the active finished-product catalog and its costed BOMs.');
        }
        $this->ensureSellingPrices($context, $catalog, $sales, $asOf);

        $customers = Customer::query()->active()->orderBy('code')->get();
        if ($customers->count() < 3) {
            throw new RuntimeException('CRM price-request scenarios require at least three active demo customers.');
        }
        $portalUsers = $this->ensurePortalUsers($context, $customers, $portalAdministrator, $asOf);
        $pairs = $this->customerProductPairs($customers, $catalog, $portalUsers);

        foreach ($customers as $index => $customer) {
            [$product, $portalUser] = $pairs[$customer->id];
            $this->ensureAcceptedAgreement($context, $customer, $product, $portalUser, $sales, $finance, $asOf, (int) $index);
        }

        $firstCustomer = $customers->values()->get(0);
        $secondCustomer = $customers->values()->get(1);
        $thirdCustomer = $customers->values()->get(2);
        if (! $firstCustomer instanceof Customer || ! $secondCustomer instanceof Customer || ! $thirdCustomer instanceof Customer) {
            throw new RuntimeException('CRM pricing did not resolve the three required active demo customers.');
        }
        [$firstProduct] = $pairs[$firstCustomer->id];
        $firstPortal = $portalUsers[(int) $firstCustomer->id];
        $this->ensureTerminalState($context, 'CUSTOMER-REJECTED', PriceRequestStatus::Rejected, $firstCustomer, $firstProduct, $firstPortal, $sales, $finance, $asOf);
        $this->ensureTerminalState($context, 'SALES-REJECTED', PriceRequestStatus::SalesRejected, $firstCustomer, $firstProduct, $firstPortal, $sales, $finance, $asOf);
        $this->ensureTerminalState($context, 'CUSTOMER-CANCELLED', PriceRequestStatus::CustomerCancelled, $firstCustomer, $firstProduct, $firstPortal, $sales, $finance, $asOf);
        $this->ensureTerminalState($context, 'SALES-WITHDRAWN', PriceRequestStatus::Withdrawn, $firstCustomer, $firstProduct, $firstPortal, $sales, $finance, $asOf);
        $this->ensureTerminalState($context, 'OFFER-EXPIRED', PriceRequestStatus::Expired, $firstCustomer, $firstProduct, $firstPortal, $sales, $finance, $asOf);

        [$secondProduct] = $pairs[$secondCustomer->id];
        $this->ensureLiveOfferedRequest($context, $secondCustomer, $secondProduct, $portalUsers[$secondCustomer->id], $sales, $finance, $asOf);

        [$thirdProduct] = $pairs[$thirdCustomer->id];
        $this->ensureLiveCustomerRequest($context, $thirdCustomer, $thirdProduct, $portalUsers[$thirdCustomer->id], $asOf);

        $this->ensureLiveFinanceProposal($context, $firstCustomer, $firstProduct, $sales, $asOf);
    }

    /** @param Collection<int, Product> $catalog */
    private function ensureSellingPrices(DemoSeedContext $context, Collection $catalog, User $sales, CarbonImmutable $asOf): void
    {
        $context->at($asOf, fn () => $context->withoutNotifications(function () use ($context, $catalog, $sales): void {
            foreach ($catalog as $product) {
                $review = $this->negotiations->reviewCost($product);
                if ($review['incomplete']) {
                    throw new BusinessRuleException("Product {$product->part_number} cannot receive a demo selling price until its manufacturing cost is complete.");
                }

                $minimum = Money::mul((string) $review['cost'], '1.20');
                $base = Money::mul((string) $review['cost'], '1.50');
                if ($product->minimum_selling_price !== null || $product->base_selling_price !== null) {
                    if ((string) $product->minimum_selling_price !== $minimum || (string) $product->base_selling_price !== $base) {
                        throw new RuntimeException("Seed-owned selling prices for {$product->part_number} differ from the documented CRM pricing assumptions.");
                    }

                    continue;
                }

                $context->asUser($sales, fn () => $this->products->updateSellingPrices($product, $sales, [
                    'minimum_selling_price' => $minimum,
                    'base_selling_price' => $base,
                ]));
            }
        }));
    }

    /** @param Collection<int, Customer> $customers
     * @return array<int, CustomerPortalUser>
     */
    private function ensurePortalUsers(DemoSeedContext $context, Collection $customers, User $administrator, CarbonImmutable $asOf): array
    {
        $password = (string) config('demo.credentials.password', '');
        if ($password === '') {
            throw new RuntimeException('DEMO_ACCOUNT_PASSWORD must be configured before creating customer pricing accounts.');
        }

        $users = [];
        foreach ($customers as $index => $customer) {
            $contact = self::CUSTOMER_CONTACTS[$index % count(self::CUSTOMER_CONTACTS)];
            $email = $contact['email_prefix'].'.'.Str::slug((string) $customer->code, '.').'@portal.ogami-demo.test';
            $portalUser = CustomerPortalUser::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($portalUser instanceof CustomerPortalUser) {
                if ($portalUser->trashed()
                    || (int) $portalUser->customer_id !== (int) $customer->id
                    || ! $portalUser->is_active
                    || ! $portalUser->is_authorized_signatory
                    || $portalUser->must_change_password
                    || ! Hash::check($password, (string) $portalUser->getRawOriginal('password'))) {
                    throw new RuntimeException("Seed-owned customer portal account {$email} has drifted; restore the demo snapshot before reseeding.");
                }
            } else {
                $portalUser = $context->at($asOf, fn (): CustomerPortalUser => DB::transaction(function () use ($context, $customer, $contact, $email, $administrator, $password): CustomerPortalUser {
                    $request = Request::create('/demo-data/crm-pricing', 'POST', server: [
                        'HTTP_HOST' => 'localhost',
                        'HTTP_USER_AGENT' => 'Ogami demo data seeder',
                        'REMOTE_ADDR' => '127.0.0.1',
                    ]);
                    $invited = $context->asUser($administrator, fn (): CustomerPortalUser => $this->portalAccess->inviteCustomer(
                        $customer,
                        $contact['name'],
                        $email,
                        $administrator,
                        $request,
                        isAuthorizedSignatory: true,
                    ));
                    $invitation = $this->passwordResets->issueInvitationToken('customer', $invited);
                    $this->passwordResets->reset('customer', $invitation['token'], $password, $request);

                    return $invited->fresh();
                }));
            }

            $context->records()->remember('crm.pricing.portal-user.'.$customer->code, $portalUser);
            $users[(int) $customer->id] = $portalUser;
        }

        return $users;
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Product>  $catalog
     * @param  array<int, CustomerPortalUser>  $portalUsers
     * @return array<int, array{0: Product, 1: CustomerPortalUser}>
     */
    private function customerProductPairs(Collection $customers, Collection $catalog, array $portalUsers): array
    {
        $pairs = [];
        foreach ($customers as $customer) {
            $product = $catalog->first(fn (Product $candidate): bool => $candidate->isAvailableToCustomer((int) $customer->id));
            if (! $product instanceof Product) {
                throw new RuntimeException("Active customer {$customer->code} has no active customer-catalog product for its price agreement.");
            }
            $portalUser = $portalUsers[(int) $customer->id] ?? null;
            if (! $portalUser instanceof CustomerPortalUser || (int) $portalUser->customer_id !== (int) $customer->id) {
                throw new RuntimeException("Active customer {$customer->code} has no provisioned portal pricing contact.");
            }
            $pairs[(int) $customer->id] = [$product, $portalUser];
        }

        return $pairs;
    }

    private function ensureAcceptedAgreement(
        DemoSeedContext $context,
        Customer $customer,
        Product $product,
        CustomerPortalUser $portal,
        User $sales,
        User $finance,
        CarbonImmutable $asOf,
        int $index,
    ): void {
        $marker = self::MARKER.'ACCEPTED:'.$customer->code;
        $existing = $this->findRequest($marker);
        if ($existing) {
            $this->assertRequest($existing, PriceRequestStatus::Accepted, $customer, $product);
            if (! $existing->agreement_id || ! PriceAgreement::query()->whereKey($existing->agreement_id)->exists()) {
                throw new RuntimeException("Accepted pricing request {$marker} has no resulting agreement.");
            }
            $context->records()->remember('crm.pricing.request.accepted.'.$customer->code, $existing);

            return;
        }

        $cost = (string) $this->negotiations->reviewCost($product)['cost'];
        $requestedAt = $asOf->addHours(1)->addMinutes($index * 20);
        $offeredAt = $requestedAt->addMinutes(3);
        $approvedAt = $offeredAt->addMinutes(5);
        $respondedAt = $approvedAt->addMinutes(4);
        $offer = $this->offerTerms($product, $marker, $asOf, $asOf->addMonthsNoOverflow(12), tiered: $index % 2 === 0);

        $request = $context->withoutNotifications(function () use ($context, $portal, $product, $marker, $cost, $requestedAt, $offeredAt, $approvedAt, $respondedAt, $offer, $sales, $finance): PriceRequest {
            $row = $context->at($requestedAt, fn (): PriceRequest => $this->negotiations->request($portal, [
                'product_id' => (int) $product->id,
                'quantity' => 500,
                'requested_price' => Money::mul($cost, '1.40'),
                'notes' => $marker.' synthetic volume-pricing request; illustrative terms only.',
            ]));
            $row = $context->at($offeredAt, fn (): PriceRequest => $context->asUser($sales, fn (): PriceRequest => $this->negotiations->offer($row, $sales, $offer, (int) $row->version)));
            $row = $context->at($approvedAt, fn (): PriceRequest => $context->asUser($finance, fn (): PriceRequest => $this->negotiations->approve($row, $finance, [
                'notes' => 'Reviewed against the current demo BOM cost and illustrative selling-price floor.',
                'version' => (int) $row->version,
                'cost_signature' => $this->negotiations->reviewCost($product)['signature'],
            ])));

            return $context->at($respondedAt, fn (): PriceRequest => $this->negotiations->respond($row, $portal, 'accept', (int) $row->version));
        });

        $this->assertRequest($request, PriceRequestStatus::Accepted, $customer, $product);
        if ((int) $request->proposed_by === (int) $request->reviewed_by) {
            throw new RuntimeException("Pricing request {$marker} was not reviewed by a different Finance user.");
        }
        $context->records()->remember('crm.pricing.request.accepted.'.$customer->code, $request);
    }

    private function ensureTerminalState(
        DemoSeedContext $context,
        string $scenario,
        PriceRequestStatus $target,
        Customer $customer,
        Product $product,
        CustomerPortalUser $portal,
        User $sales,
        User $finance,
        CarbonImmutable $asOf,
    ): void {
        $marker = self::MARKER.$scenario;
        $existing = $this->findRequest($marker);
        if ($existing) {
            $this->assertRequest($existing, $target, $customer, $product);
            $context->records()->remember('crm.pricing.request.'.Str::slug($scenario), $existing);

            return;
        }

        $cost = (string) $this->negotiations->reviewCost($product)['cost'];
        $scenarioAt = $asOf->addHours(5)->addMinutes(match ($scenario) {
            'CUSTOMER-REJECTED' => 0,
            'SALES-REJECTED' => 20,
            'CUSTOMER-CANCELLED' => 40,
            'SALES-WITHDRAWN' => 60,
            default => 80,
        });
        $notes = $marker.' illustrative negotiation history for the CRM demo.';

        $request = $context->withoutNotifications(function () use ($context, $target, $customer, $product, $portal, $sales, $finance, $asOf, $scenarioAt, $cost, $notes): PriceRequest {
            if ($target === PriceRequestStatus::Withdrawn) {
                $row = $context->at($scenarioAt, fn (): PriceRequest => $context->asUser($sales, fn (): PriceRequest => $this->negotiations->offer(null, $sales, [
                    'customer_id' => (int) $customer->id,
                    'product_id' => (int) $product->id,
                    ...$this->offerTerms($product, $notes, $scenarioAt, $scenarioAt->addMonthsNoOverflow(2)),
                ])));

                return $context->at($scenarioAt->addMinutes(4), fn (): PriceRequest => $context->asUser($sales, fn (): PriceRequest => $this->negotiations->withdraw($row, $sales, 'Customer volume assumptions changed.', (int) $row->version)));
            }

            if ($target === PriceRequestStatus::Expired) {
                $offerAt = $asOf->subDays(35);
                $end = $asOf->subDay();
                $row = $context->at($offerAt, fn (): PriceRequest => $context->asUser($sales, fn (): PriceRequest => $this->negotiations->offer(null, $sales, [
                    'customer_id' => (int) $customer->id,
                    'product_id' => (int) $product->id,
                    ...$this->offerTerms($product, $notes, $offerAt, $end),
                ])));

                $context->at($asOf, fn () => $this->negotiations->expire());
                $expired = $row->fresh();
                if (! $expired instanceof PriceRequest) {
                    throw new RuntimeException("CRM expired-request scenario {$notes} disappeared during expiry processing.");
                }

                return $expired;
            }

            $requestedAt = $scenarioAt;
            $requestedPriceFactor = $target === PriceRequestStatus::SalesRejected ? '1.10' : '1.35';
            $row = $context->at($requestedAt, fn (): PriceRequest => $this->negotiations->request($portal, [
                'product_id' => (int) $product->id,
                'quantity' => 250,
                'requested_price' => Money::mul($cost, $requestedPriceFactor),
                'notes' => $notes,
            ]));

            if ($target === PriceRequestStatus::SalesRejected) {
                return $context->at($scenarioAt->addMinutes(4), fn (): PriceRequest => $context->asUser($sales, fn (): PriceRequest => $this->negotiations->rejectBySales($row, $sales, 'Requested price is below the illustrative price floor.', (int) $row->version)));
            }

            if ($target === PriceRequestStatus::CustomerCancelled) {
                return $context->at($scenarioAt->addMinutes(4), fn (): PriceRequest => $this->negotiations->cancelByCustomer($row, $portal, (int) $row->version));
            }

            if ($target !== PriceRequestStatus::Rejected) {
                throw new RuntimeException("Unsupported customer pricing terminal state {$target->value}.");
            }

            $offer = $this->offerTerms($product, $notes, $asOf, $asOf->addMonthsNoOverflow(2));
            $row = $context->at($scenarioAt->addMinutes(3), fn (): PriceRequest => $context->asUser($sales, fn (): PriceRequest => $this->negotiations->offer($row, $sales, $offer, (int) $row->version)));
            $row = $context->at($scenarioAt->addMinutes(6), fn (): PriceRequest => $context->asUser($finance, fn (): PriceRequest => $this->negotiations->approve($row, $finance, [
                'notes' => 'Reviewed against the costed demo BOM.',
                'version' => (int) $row->version,
                'cost_signature' => $this->negotiations->reviewCost($product)['signature'],
            ])));

            return $context->at($scenarioAt->addMinutes(9), fn (): PriceRequest => $this->negotiations->respond($row, $portal, 'reject', (int) $row->version));
        });

        $this->assertRequest($request, $target, $customer, $product);
        $context->records()->remember('crm.pricing.request.'.Str::slug($scenario), $request);
    }

    private function ensureLiveOfferedRequest(
        DemoSeedContext $context,
        Customer $customer,
        Product $product,
        CustomerPortalUser $portal,
        User $sales,
        User $finance,
        CarbonImmutable $asOf,
    ): void {
        $marker = self::MARKER.'LIVE-CUSTOMER-OFFER';
        $existing = $this->findRequest($marker);
        if ($existing) {
            $this->assertRequest($existing, PriceRequestStatus::Offered, $customer, $product);
            $context->records()->remember('crm.pricing.request.live-offered', $existing);

            return;
        }

        $requestAt = $asOf->addHours(8);
        $request = $context->at($requestAt, fn (): PriceRequest => $this->negotiations->request($portal, [
            'product_id' => (int) $product->id,
            'quantity' => 500,
            'requested_price' => Money::mul((string) $this->negotiations->reviewCost($product)['cost'], '1.40'),
            'notes' => $marker.' awaiting the customer decision on reviewed volume terms.',
        ]));
        $offer = $this->offerTerms($product, (string) $request->notes, $asOf->addDays(30), $asOf->addMonthsNoOverflow(9), tiered: true);
        $request = $context->at($requestAt->addMinutes(5), fn (): PriceRequest => $context->asUser($sales, fn (): PriceRequest => $this->negotiations->offer($request, $sales, $offer, (int) $request->version)));
        $request = $context->at($requestAt->addMinutes(15), fn (): PriceRequest => $context->asUser($finance, fn (): PriceRequest => $this->negotiations->approve($request, $finance, [
            'notes' => 'Reviewed against the current demo BOM cost and illustrative selling-price floor.',
            'version' => (int) $request->version,
            'cost_signature' => $this->negotiations->reviewCost($product)['signature'],
        ])));

        $this->assertRequest($request, PriceRequestStatus::Offered, $customer, $product);
        $context->records()->remember('crm.pricing.request.live-offered', $request);
    }

    private function ensureLiveCustomerRequest(
        DemoSeedContext $context,
        Customer $customer,
        Product $product,
        CustomerPortalUser $portal,
        CarbonImmutable $asOf,
    ): void {
        $marker = self::MARKER.'LIVE-CUSTOMER-REQUEST';
        $existing = $this->findRequest($marker);
        if ($existing) {
            $this->assertRequest($existing, PriceRequestStatus::Requested, $customer, $product);
            $context->records()->remember('crm.pricing.request.live-requested', $existing);

            return;
        }

        $request = $context->at($asOf->addHours(9), fn (): PriceRequest => $this->negotiations->request($portal, [
            'product_id' => (int) $product->id,
            'quantity' => 250,
            'requested_price' => Money::mul((string) $this->negotiations->reviewCost($product)['cost'], '1.35'),
            'notes' => $marker.' awaiting a Sales response to the customer request.',
        ]));
        $this->assertRequest($request, PriceRequestStatus::Requested, $customer, $product);
        $context->records()->remember('crm.pricing.request.live-requested', $request);
    }

    private function ensureLiveFinanceProposal(
        DemoSeedContext $context,
        Customer $customer,
        Product $product,
        User $sales,
        CarbonImmutable $asOf,
    ): void {
        $marker = self::MARKER.'LIVE-FINANCE-REVIEW';
        $existing = $this->findRequest($marker);
        if ($existing) {
            $this->assertRequest($existing, PriceRequestStatus::PendingFinance, $customer, $product);
            $context->records()->remember('crm.pricing.request.live-finance', $existing);

            return;
        }

        $proposalAt = $asOf->addHours(10);
        $request = $context->at($proposalAt, fn (): PriceRequest => $context->asUser($sales, fn (): PriceRequest => $this->negotiations->offer(null, $sales, [
            'customer_id' => (int) $customer->id,
            'product_id' => (int) $product->id,
            ...$this->offerTerms($product, $marker.' awaiting Finance cost review.', $asOf->addDays(30), $asOf->addMonthsNoOverflow(9)),
        ])));
        $this->assertRequest($request, PriceRequestStatus::PendingFinance, $customer, $product);
        $context->records()->remember('crm.pricing.request.live-finance', $request);
    }

    /** @return array{price:string,pricing_method:string,tiers:?list<array{min_qty:int,unit_price:string}>,effective_from:string,effective_to:string,notes:string} */
    private function offerTerms(
        Product $product,
        string $notes,
        CarbonImmutable $start,
        CarbonImmutable $end,
        bool $tiered = false,
    ): array {
        $cost = (string) $this->negotiations->reviewCost($product)['cost'];

        return [
            'price' => Money::mul($cost, '1.50'),
            'pricing_method' => $tiered ? PricingMethod::Tiered->value : PricingMethod::Flat->value,
            'tiers' => $tiered ? [
                ['min_qty' => 100, 'unit_price' => Money::mul($cost, '1.45')],
                ['min_qty' => 500, 'unit_price' => Money::mul($cost, '1.40')],
            ] : null,
            'effective_from' => $start->toDateString(),
            'effective_to' => $end->toDateString(),
            'notes' => $notes,
        ];
    }

    private function findRequest(string $marker): ?PriceRequest
    {
        return PriceRequest::query()->where('notes', $marker.' synthetic volume-pricing request; illustrative terms only.')
            ->orWhere('notes', 'like', $marker.'%')
            ->first();
    }

    private function assertRequest(PriceRequest $request, PriceRequestStatus $status, Customer $customer, Product $product): void
    {
        if ($request->status !== $status
            || (int) $request->customer_id !== (int) $customer->id
            || (int) $request->product_id !== (int) $product->id) {
            throw new RuntimeException("Seed-owned price request {$request->notes} no longer matches its expected {$status->value} customer/product state.");
        }
    }

    private function account(DemoSeedContext $context, string $email, string $permission): User
    {
        $key = 'workforce.user.account.'.$email;
        if (! $context->records()->has($key)) {
            throw new ModelNotFoundException("CRM pricing requires workforce account {$email}; run workforce-identities first.");
        }
        $user = User::query()->find((int) $context->records()->id($key));
        if (! $user instanceof User || ! $user->is_active || ! $user->hasPermission($permission)) {
            throw new RuntimeException("CRM pricing workforce account {$email} must be active and hold {$permission}.");
        }

        return $user;
    }
}
