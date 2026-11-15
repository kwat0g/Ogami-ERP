<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Services\SettingsService;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Models\Product;
use App\Modules\Dashboard\Models\KpiDefinition;
use App\Modules\Dashboard\Services\KpiSnapshotService;
use App\Modules\Forecasting\Services\ForecastingService;
use App\Modules\Purchasing\Services\SupplierPerformanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\KpiDefinitionSeeder;
use RuntimeException;

/** Adds operator-entered planning forecasts and source-computed KPI history. */
final class AnalyticsForecastLayer extends DemoSeeder
{
    public function __construct(
        private readonly ForecastingService $forecasts,
        private readonly KpiSnapshotService $kpis,
        private readonly SupplierPerformanceService $supplierPerformance,
        private readonly SettingsService $settings,
        private readonly KpiDefinitionSeeder $definitions,
    ) {}

    public function key(): string
    {
        return 'analytics-forecasts';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Forecasting', 'Analytics', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        // Compute source-backed KPI snapshots only after the order-to-cash and
        // receiving flows have produced their deliveries and supplier receipts.
        return ['order-to-cash-fulfillment', 'purchasing-receiving-history'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila');
        $products = Product::query()->active()->with('portalCustomers')->orderBy('id')->get();
        if ($products->isEmpty()) {
            throw new RuntimeException('Analytics forecasts require active CRM products.');
        }

        $definitionCount = KpiDefinition::query()->count();
        if ($definitionCount === 0) {
            // Use the app's KPI catalog and leave all system settings untouched.
            $this->definitions->run();
        } elseif ($definitionCount < 11) {
            throw new RuntimeException("KPI baseline is partial ({$definitionCount}/11 definitions); restore the system KPI catalog before seeding analytics.");
        }

        $planner = User::query()
            ->where('is_active', true)
            ->whereHas('role', static fn ($query) => $query->where('slug', 'ppc_head'))
            ->orderBy('id')
            ->first();

        $horizon = $this->settings->requiredInt('forecasting.default_horizon_months', 1, 12);
        $start = CarbonImmutable::instance($asOf)->startOfMonth()->addMonthNoOverflow();
        $periods = [];
        for ($offset = 0; $offset < $horizon; $offset++) {
            $periods[] = $start->addMonthsNoOverflow($offset);
        }

        $context->at($asOf->endOfDay(), function () use ($products, $periods, $planner, $asOf): void {
            foreach ($products as $product) {
                $customers = $product->portalCustomers->sortBy('id')->values();
                if ($customers->isEmpty()) {
                    throw new RuntimeException("Product {$product->part_number} has no customer catalog mapping for its demand forecast.");
                }

                foreach ($periods as $period) {
                    $total = 0.0;
                    $confidenceTotal = 0.0;
                    foreach ($customers as $customer) {
                        // Stable SKU/customer pattern, rounded to practical order lots.
                        $base = 350 + (((int) $product->id * 167 + (int) $customer->id * 83) % 2100);
                        $seasonalFactor = match ((int) $period->month) {
                            12 => 1.10,
                            1 => 0.92,
                            default => 1.0,
                        };
                        $quantity = (float) (round(($base * $seasonalFactor) / 50) * 50);
                        $confidence = (float) (72 + (((int) $product->id * 13 + (int) $customer->id * 7) % 20));

                        $this->forecasts->storeManual(
                            (int) $product->id,
                            (int) $customer->id,
                            (int) $period->year,
                            (int) $period->month,
                            $quantity,
                            $confidence,
                            $planner,
                        );
                        $total += $quantity;
                        $confidenceTotal += $confidence;
                    }

                    // The all-customer row reconciles exactly to its customer plans.
                    $this->forecasts->storeManual(
                        (int) $product->id,
                        null,
                        (int) $period->year,
                        (int) $period->month,
                        $total,
                        $confidenceTotal / $customers->count(),
                        $planner,
                    );
                }
            }

            $supplierScores = $this->supplierPerformance->recomputeAll((int) $asOf->year, (int) $asOf->month);
            if ($supplierScores['failed'] !== []) {
                $first = $supplierScores['failed'][0];
                throw new RuntimeException("Supplier performance snapshot failed for vendor {$first['vendor_id']}: {$first['error']}");
            }

            // Only periods with source observations persist a snapshot; no KPI values are invented.
            $definitions = KpiDefinition::query()->active()->orderBy('display_order')->get();
            $firstMonth = now()->toImmutable()->startOfMonth()->subMonthsNoOverflow(18);
            $lastMonth = now()->toImmutable()->startOfMonth();
            for ($month = $firstMonth; $month->lte($lastMonth); $month = $month->addMonthNoOverflow()) {
                foreach ($definitions as $definition) {
                    $this->kpis->computeKpi($definition, $month->year, $month->month);
                }
            }
        });
    }
}
