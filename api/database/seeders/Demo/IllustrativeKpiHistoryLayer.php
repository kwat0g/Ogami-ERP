<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Dashboard\Enums\KpiDirection;
use App\Modules\Dashboard\Enums\KpiStatus;
use App\Modules\Dashboard\Enums\KpiTrend;
use App\Modules\Dashboard\Models\KpiDefinition;
use App\Modules\Dashboard\Models\KpiSnapshot;
use Database\Seeders\KpiDefinitionSeeder;
use RuntimeException;

/** Fills trend-only KPI history from a documented illustrative fixture. */
final class IllustrativeKpiHistoryLayer extends DemoSeeder
{
    private const FIXTURE = 'fixtures/illustrative-kpi-history.json';
    private const EXPECTED_DEFINITIONS = 11;

    public function __construct(private readonly KpiDefinitionSeeder $definitions) {}

    public function key(): string
    {
        return 'illustrative-kpi-history';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Analytics', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return [];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $fixturePath = database_path('seeders/Demo/'.self::FIXTURE);
        $contents = file_get_contents($fixturePath);
        if ($contents === false) {
            throw new RuntimeException("Illustrative KPI history fixture is missing: {$fixturePath}");
        }

        try {
            $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Illustrative KPI history fixture is invalid JSON.', previous: $exception);
        }

        $months = (int) ($fixture['months'] ?? 0);
        $offsets = $fixture['offsets'] ?? null;
        $anomalies = $fixture['anomalies'] ?? [];
        if (($fixture['version'] ?? null) !== 1 || $months !== 18 || ! is_array($offsets)) {
            throw new RuntimeException('Illustrative KPI history fixture must define version 1 and exactly 18 periods.');
        }

        $definitionCount = KpiDefinition::query()->count();
        if ($definitionCount === 0) {
            $this->definitions->run();
            $definitionCount = KpiDefinition::query()->count();
        }
        if ($definitionCount < self::EXPECTED_DEFINITIONS) {
            throw new RuntimeException("KPI baseline is partial ({$definitionCount}/".self::EXPECTED_DEFINITIONS."); restore the system KPI catalog before seeding illustrative history.");
        }

        $definitions = KpiDefinition::query()->active()->orderBy('code')->get();
        $missing = $definitions->pluck('code')->reject(fn (string $code): bool => isset($offsets[$code]))->values()->all();
        if ($missing !== []) {
            throw new RuntimeException('Illustrative KPI fixture has no series for: '.implode(', ', $missing));
        }

        $firstMonth = $context->options->asOf->setTimezone('Asia/Manila')->startOfMonth()->subMonthsNoOverflow($months - 1);
        $lastMonth = $context->options->asOf->setTimezone('Asia/Manila')->startOfMonth();
        $snapshots = KpiSnapshot::query()
            ->whereIn('definition_id', $definitions->pluck('id'))
            ->get()
            ->keyBy(fn (KpiSnapshot $snapshot): string => $this->snapshotKey($snapshot));

        foreach ($definitions as $definition) {
            if ($definition->target_value === null) {
                throw new RuntimeException("KPI {$definition->code} has no target value; illustrative history cannot derive its status.");
            }
            $series = $offsets[$definition->code];
            if (! is_array($series) || count($series) !== $months) {
                throw new RuntimeException("KPI {$definition->code} must have exactly {$months} offset values.");
            }

            $previous = null;
            for ($index = 0; $index < $months; $index++) {
                $period = $firstMonth->addMonthsNoOverflow($index);
                $key = $this->periodKey($period->year, $period->month);
                $delta = $series[$index] ?? null;
                if (! is_numeric($delta)) {
                    throw new RuntimeException("KPI {$definition->code} has a non-numeric offset at fixture index {$index}.");
                }

                $value = $this->boundedValue($definition, (float) $definition->target_value + (float) $delta);
                $existing = $snapshots->get($this->snapshotKeyFor($definition->id, $period->year, $period->month));
                if ($existing instanceof KpiSnapshot) {
                    $this->assertExistingSnapshot($existing, $value, (float) $definition->target_value, $definition->code, $key);
                    $previous = (float) $existing->actual_value;

                    continue;
                }

                $computedAt = $period->endOfMonth()->setTime(16, 0);
                $asOf = $context->options->asOf->setTimezone('Asia/Manila');
                if ($computedAt->gt($asOf)) {
                    $computedAt = $asOf;
                }
                $breakdown = [
                    'source' => 'illustrative_history',
                    'fixture_version' => (int) $fixture['version'],
                ];
                $anomaly = $anomalies[$definition->code][(string) $index] ?? null;
                if (is_string($anomaly) && $anomaly !== '') {
                    $breakdown['planted_issue'] = $anomaly;
                }

                $snapshot = $context->at($computedAt, fn (): KpiSnapshot => KpiSnapshot::query()->create([
                    'definition_id' => $definition->id,
                    'period_year' => $period->year,
                    'period_month' => $period->month,
                    'actual_value' => number_format($value, 4, '.', ''),
                    'target_value' => number_format((float) $definition->target_value, 4, '.', ''),
                    'previous_value' => $previous === null ? null : number_format($previous, 4, '.', ''),
                    'trend' => $this->trend($value, $previous)->value,
                    'status' => $this->status($value, $definition)->value,
                    'breakdown' => $breakdown,
                    'computed_at' => $computedAt->toDateTimeString(),
                ]));
                $snapshots->put($this->snapshotKey($snapshot), $snapshot);
                $previous = $value;
            }
        }
    }

    private function boundedValue(KpiDefinition $definition, float $value): float
    {
        if ($definition->unit->value === 'percentage' && $definition->code !== 'budget_utilization') {
            return max(0.0, min(100.0, $value));
        }

        return max(0.01, $value);
    }

    private function trend(float $value, ?float $previous): KpiTrend
    {
        if ($previous === null || abs($value - $previous) < max(abs($previous) * 0.01, 0.01)) {
            return KpiTrend::Flat;
        }

        return $value > $previous ? KpiTrend::Up : KpiTrend::Down;
    }

    private function status(float $value, KpiDefinition $definition): KpiStatus
    {
        $target = (float) $definition->target_value;
        $warning = $definition->warning_threshold === null ? null : (float) $definition->warning_threshold;
        if ($definition->direction === KpiDirection::HigherIsBetter) {
            if ($value >= $target) {
                return KpiStatus::OnTarget;
            }

            return $warning !== null && $value >= $warning ? KpiStatus::Warning : KpiStatus::OffTarget;
        }
        if ($value <= $target) {
            return KpiStatus::OnTarget;
        }

        return $warning !== null && $value <= $warning ? KpiStatus::Warning : KpiStatus::OffTarget;
    }

    private function assertExistingSnapshot(KpiSnapshot $snapshot, float $expected, float $target, string $code, string $period): void
    {
        if (($snapshot->breakdown['source'] ?? null) !== 'illustrative_history') {
            // Preserve any source-computed snapshot already present.
            return;
        }

        if (abs((float) $snapshot->actual_value - $expected) > 0.0001
            || abs((float) $snapshot->target_value - $target) > 0.0001
            || (int) ($snapshot->breakdown['fixture_version'] ?? 0) !== 1) {
            throw new RuntimeException("Illustrative KPI snapshot {$code} {$period} has drifted from fixture version 1.");
        }
    }

    private function snapshotKey(KpiSnapshot $snapshot): string
    {
        return $this->snapshotKeyFor((int) $snapshot->definition_id, (int) $snapshot->period_year, (int) $snapshot->period_month);
    }

    private function snapshotKeyFor(int $definitionId, int $year, int $month): string
    {
        return $definitionId.':'.$this->periodKey($year, $month);
    }

    private function periodKey(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }
}
