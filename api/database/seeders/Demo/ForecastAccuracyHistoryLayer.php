<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Enums\SalesOrderStatus;
use App\Modules\CRM\Models\PriceAgreement;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\SalesOrder;
use App\Modules\CRM\Services\PriceAgreementService;
use App\Modules\CRM\Services\SalesOrderService;
use App\Modules\CRM\Support\PriceAgreementAuditContext;
use App\Modules\Forecasting\Models\DemandForecast;
use App\Modules\Forecasting\Services\ForecastingService;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Adds one service-backed historical demand point for forecast accuracy. */
final class ForecastAccuracyHistoryLayer extends DemoSeeder
{
    private const ORDER_MARKER = 'OGI-FORECAST-HISTORY:CONFIRMED-DEMAND-01';

    public function __construct(
        private readonly PriceAgreementService $priceAgreements,
        private readonly SalesOrderService $salesOrders,
        private readonly ForecastingService $forecasts,
    ) {}

    public function key(): string
    {
        return 'forecast-accuracy-history';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Forecasting', 'CRM', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return [];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $period = $asOf->subMonthNoOverflow()->startOfMonth();
        $periodEnd = $period->endOfMonth();
        $orderDate = $period->addDays(9);
        $deliveryDate = $periodEnd->subDays(2);

        $sales = User::query()->where('email', 'abiera.shiela@gmail.com')->first();
        $planner = User::query()->where('is_active', true)
            ->whereHas('role', static fn ($query) => $query->where('slug', 'ppc_head'))
            ->orderBy('id')->first();
        if (! $sales instanceof User || ! $sales->is_active || ! $sales->hasPermission('crm.sales_orders.create')
            || ! $sales->hasPermission('crm.sales_orders.confirm') || ! $planner instanceof User) {
            throw new RuntimeException('Forecast history requires the active Sales actor and a PPC planning user.');
        }

        $order = SalesOrder::query()->where('notes', self::ORDER_MARKER)->first();
        if (! $order instanceof SalesOrder) {
            [$customer, $product, $currentAgreement] = $this->sourcePair();
            $historicalAgreement = PriceAgreement::query()
                ->where('customer_id', $customer->id)
                ->where('product_id', $product->id)
                ->whereDate('effective_from', $period->toDateString())
                ->whereDate('effective_to', $periodEnd->toDateString())
                ->first();

            if (! $historicalAgreement instanceof PriceAgreement) {
                $historicalAgreement = $context->at($period->setTime(9, 0), fn () => PriceAgreementAuditContext::run(
                    (int) $sales->id,
                    'Historical sales sample used to demonstrate forecast accuracy.',
                    fn (): PriceAgreement => $this->priceAgreements->create([
                        'product_id' => (int) $product->id,
                        'customer_id' => (int) $customer->id,
                        'price' => (string) $currentAgreement->price,
                        'pricing_method' => $currentAgreement->pricing_method->value,
                        'tiers' => $currentAgreement->tiers,
                        'effective_from' => $period->toDateString(),
                        'effective_to' => $periodEnd->toDateString(),
                    ]),
                ));
            }

            $items = [[
                'product_id' => (int) $product->id,
                'quantity' => '500',
                'delivery_date' => $deliveryDate->toDateString(),
            ]];
            $quote = $context->at($orderDate->setTime(9, 0), fn (): array => $this->salesOrders->quoteOrder((int) $customer->id, $items));
            if (! is_string($quote['signature'] ?? null)) {
                throw new RuntimeException('Forecast history could not create a signed historical Sales order quote.');
            }

            $order = $context->at($orderDate->setTime(9, 15), fn () => $context->asUser($sales, fn (): SalesOrder => $this->salesOrders->create([
                'customer_id' => (int) $customer->id,
                'date' => $orderDate->toDateString(),
                'payment_method' => 'bank_transfer',
                'payment_terms_days' => (int) $customer->payment_terms_days,
                'notes' => self::ORDER_MARKER,
                'items' => $items,
                'quote_signature' => $quote['signature'],
                'submission_source' => 'internal',
            ], (int) $sales->id)));

            $context->at($orderDate->addDay()->setTime(10, 0), fn () => $context->asUser($sales, fn () => $this->salesOrders->confirm($order)));
            $order->refresh();
        }

        if ($order->status !== SalesOrderStatus::Confirmed || $order->items()->count() !== 1) {
            throw new RuntimeException('Forecast source sales order must remain one confirmed, single-line historical transaction.');
        }
        $line = $order->items()->first();
        $sourceMonth = $order->date?->month;
        $sourceYear = $order->date?->year;
        if ($line === null || $sourceMonth !== $period->month || $sourceYear !== $period->year
            || bccomp((string) $line->quantity, '500.00', 2) !== 0) {
            throw new RuntimeException('Forecast source sales order date or demand quantity drifted from the seeded history.');
        }

        $context->at($period->setTime(8, 0), function () use ($line, $order, $planner, $period, $context, $asOf): void {
            foreach ([(int) $order->customer_id, null] as $customerId) {
                $existing = DemandForecast::query()
                    ->where('product_id', $line->product_id)
                    ->where('customer_id', $customerId)
                    ->where('forecast_year', $period->year)
                    ->where('forecast_month', $period->month)
                    ->first();
                if ($existing instanceof DemandForecast) {
                    continue;
                }

                $this->forecasts->storeManual(
                    (int) $line->product_id,
                    $customerId,
                    (int) $period->year,
                    (int) $period->month,
                    550,
                    80,
                    $planner,
                );
            }
            $context->at($asOf->setTime(11, 0), function (): void {
                $this->forecasts->reconcileActuals();
            });
        });

        $forecast = DemandForecast::query()
            ->where('product_id', $line->product_id)
            ->whereNull('customer_id')
            ->where('forecast_year', $period->year)
            ->where('forecast_month', $period->month)
            ->first();
        if (! $forecast instanceof DemandForecast
            || bccomp((string) $forecast->actual_quantity, '500.00', 2) !== 0
            || bccomp((string) $forecast->variance, '-50.00', 2) !== 0) {
            throw new RuntimeException('Forecast history did not reconcile the historical forecast row to the confirmed Sales order.');
        }
    }

    /** @return array{Customer, Product, PriceAgreement} */
    private function sourcePair(): array
    {
        $current = PriceAgreement::query()
            ->whereDate('effective_to', '>=', now()->toDateString())
            ->whereHas('customer', static fn ($query) => $query->active())
            ->whereHas('product', static fn ($query) => $query->active())
            ->orderBy('customer_id')->orderBy('product_id')->first();
        if (! $current instanceof PriceAgreement) {
            throw new RuntimeException('Forecast history requires an active customer/product price agreement to anchor the historical reference price.');
        }

        $customer = Customer::query()->active()->find((int) $current->customer_id);
        $product = Product::query()->active()->find((int) $current->product_id);
        if (! $customer instanceof Customer || ! $product instanceof Product || ! $product->isAvailableToCustomer((int) $customer->id)) {
            throw new RuntimeException('Forecast history pricing reference is not available to the selected customer.');
        }

        return [$customer, $product, $current];
    }
}
