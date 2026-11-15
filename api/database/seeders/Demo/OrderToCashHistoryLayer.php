<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Enums\SalesOrderStatus;
use App\Modules\CRM\Models\PriceAgreement;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\SalesOrder;
use App\Modules\CRM\Services\SalesOrderService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Creates one live Sales draft parked before confirmation starts MRP. */
final class OrderToCashHistoryLayer extends DemoSeeder
{
    private const MARKER = 'DEMO-O2C-LIVE:SO-DRAFT-0001';

    public function __construct(private readonly SalesOrderService $salesOrders) {}

    public function key(): string
    {
        return 'order-to-cash-history';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['CRM', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['crm-pricing'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $sales = $this->salesActor();
        $orderDay = $this->priorBusinessDay($asOf);
        $deliveryDay = $this->businessDay($asOf, 10);
        [$agreement, $customer] = $this->pricingPair($this->businessDay($asOf, 10));
        $product = Product::query()->active()->find((int) $agreement->product_id);
        if (! $product instanceof Product || ! $product->isAvailableToCustomer((int) $customer->id)) {
            throw new RuntimeException('Order-to-cash history needs an active, customer-visible product with an applicable price agreement.');
        }

        $items = [[
            'product_id' => (int) $product->id,
            'quantity' => '500',
            'delivery_date' => $deliveryDay->toDateString(),
        ]];
        $quote = $context->at($orderDay->setTime(9, 0), fn (): array => $this->salesOrders->quoteOrder((int) $customer->id, $items));
        if (! is_string($quote['signature'] ?? null) || strlen($quote['signature']) !== 64) {
            throw new RuntimeException('Order-to-cash history could not obtain the current Sales order quote signature.');
        }

        $matches = SalesOrder::query()->where('notes', self::MARKER)->limit(2)->get();
        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple sales orders use the O2C seed marker; reconcile duplicates before reseeding.');
        }
        $existing = $matches->first();
        if ($existing instanceof SalesOrder) {
            $this->assertExpectedOrder($existing, $customer, $product, $agreement, $sales, $orderDay, $deliveryDay, $quote);
            $context->records()->remember('o2c.sales.draft-ready-to-confirm', $existing);

            return;
        }

        $order = $context->at($orderDay->setTime(9, 15), fn (): SalesOrder => $context->asUser($sales, fn (): SalesOrder => $this->salesOrders->create([
            'customer_id' => (int) $customer->id,
            'date' => $orderDay->toDateString(),
            'payment_method' => 'bank_transfer',
            'payment_terms_days' => (int) $customer->payment_terms_days,
            'notes' => self::MARKER,
            'items' => $items,
            'quote_signature' => $quote['signature'],
            'submission_source' => 'internal',
        ], (int) $sales->id)));
        $this->assertExpectedOrder($order, $customer, $product, $agreement, $sales, $orderDay, $deliveryDay, $quote);

        $context->records()->remember('o2c.sales.draft-ready-to-confirm', $order);
        $context->records()->remember('o2c.sales.draft.customer', $customer);
        $context->records()->remember('o2c.sales.draft.product', $product);
    }

    /** @return array{PriceAgreement, Customer} */
    private function pricingPair(CarbonImmutable $deliveryDate): array
    {
        $agreement = PriceAgreement::query()
            ->activeOn($deliveryDate)
            ->whereHas('customer', static fn ($query) => $query->active())
            ->whereHas('product', static fn ($query) => $query->active())
            ->orderBy('customer_id')
            ->orderBy('product_id')
            ->first();
        if (! $agreement instanceof PriceAgreement) {
            throw new RuntimeException('Order-to-cash history requires an active CRM price agreement for the requested delivery date.');
        }

        $customer = Customer::query()->active()->find((int) $agreement->customer_id);
        if (! $customer instanceof Customer) {
            throw new RuntimeException('Order-to-cash history requires an active customer for its approved price agreement.');
        }

        return [$agreement, $customer];
    }

    private function assertExpectedOrder(
        SalesOrder $order,
        Customer $customer,
        Product $product,
        PriceAgreement $agreement,
        User $sales,
        CarbonImmutable $orderDay,
        CarbonImmutable $deliveryDay,
        array $quote,
    ): void {
        if ($order->status !== SalesOrderStatus::Draft
            || $order->submission_source?->value !== 'internal'
            || $order->notes !== self::MARKER
            || (int) $order->customer_id !== (int) $customer->id
            || (int) $order->created_by !== (int) $sales->id
            || $order->date?->toDateString() !== $orderDay->toDateString()
            || (int) $order->payment_terms_days !== (int) $customer->payment_terms_days
            || $order->payment_method !== 'bank_transfer'
            || $order->created_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') !== $orderDay->setTime(9, 15)->format('Y-m-d H:i:s')
            || $order->items()->count() !== 1) {
            throw new RuntimeException('Seed-owned O2C sales order has drifted from its expected draft-ready-to-confirm state.');
        }

        $line = $order->items()->first();
        $expectedLine = $quote['items'][0] ?? null;
        if ($line === null || ! is_array($expectedLine)
            || (int) $line->product_id !== (int) $product->id
            || bccomp((string) $line->quantity, '500.00', 2) !== 0
            || $line->delivery_date?->toDateString() !== $deliveryDay->toDateString()
            || (int) $line->price_agreement_id !== (int) $agreement->id
            || bccomp((string) $line->unit_price, (string) $expectedLine['unit_price'], 2) !== 0
            || bccomp((string) $line->total, (string) $expectedLine['total'], 2) !== 0
            || bccomp((string) $order->subtotal, (string) $quote['subtotal'], 2) !== 0
            || bccomp((string) $order->vat_amount, (string) $quote['vat_amount'], 2) !== 0
            || bccomp((string) $order->total_amount, (string) $quote['total_amount'], 2) !== 0
            || bccomp(bcadd((string) $order->subtotal, (string) $order->vat_amount, 2), (string) $order->total_amount, 2) !== 0) {
            throw new RuntimeException('Seed-owned O2C Sales order totals no longer reconcile with its line.');
        }

        $auditExists = DB::table('audit_logs')
            ->where('action', 'created')
            ->where('actor_type', 'user')
            ->where('user_id', $sales->id)
            ->where('model_type', $order->getMorphClass())
            ->where('model_id', $order->id)
            ->where('created_at', $orderDay->setTime(9, 15)->format('Y-m-d H:i:s'))
            ->exists();
        if (! $auditExists) {
            throw new RuntimeException('Seed-owned O2C sales order is missing its controlled Sales audit event.');
        }
    }

    private function salesActor(): User
    {
        $sales = User::query()->where('email', 'sales.officer@accounts.ogami-demo.test')->first();
        if (! $sales instanceof User || ! $sales->is_active
            || ! $sales->hasPermission('crm.sales_orders.create')
            || ! $sales->hasPermission('crm.sales_orders.confirm')
            || ! $sales->hasPermission('crm.sales_orders.view')) {
            throw new RuntimeException('Order-to-cash history requires the active Sales demo actor with order create, view, and confirmation permissions.');
        }

        return $sales;
    }

    private function businessDay(CarbonImmutable $asOf, int $offset): CarbonImmutable
    {
        $day = $asOf->setTimezone('Asia/Manila')->startOfDay();
        $remaining = $offset;
        while ($remaining > 0) {
            $day = $day->addDay();
            if (! $day->isWeekend() && ! $this->isHoliday($day)) {
                $remaining--;
            }
        }

        return $day;
    }

    private function priorBusinessDay(CarbonImmutable $asOf): CarbonImmutable
    {
        $day = $asOf->setTimezone('Asia/Manila')->startOfDay();
        while ($day->isWeekend() || $this->isHoliday($day)) {
            $day = $day->subDay();
        }

        return $day;
    }

    private function isHoliday(CarbonImmutable $day): bool
    {
        return Holiday::query()->whereDate('date', $day->toDateString())->exists()
            || Holiday::query()->where('is_recurring', true)
                ->whereRaw("to_char(date, 'MM-DD') = ?", [$day->format('m-d')])->exists();
    }
}
