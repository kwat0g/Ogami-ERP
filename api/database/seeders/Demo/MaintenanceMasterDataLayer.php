<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Exceptions\BusinessRuleException;
use App\Common\Services\SettingsService;
use App\Modules\Auth\Models\User;
use App\Modules\Maintenance\Enums\MaintainableType;
use App\Modules\Maintenance\Enums\MaintenanceScheduleInterval;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Maintenance\Services\MaintenanceScheduleService;
use App\Modules\MRP\Models\Machine;
use App\Modules\MRP\Models\Mold;
use App\Modules\Quality\Models\CalibrationRecord;
use App\Modules\Quality\Services\CalibrationService;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Seeds application-backed PM schedules and a synthetic calibration register. */
final class MaintenanceMasterDataLayer extends DemoSeeder
{
    /** @var list<array{code: string, name: string, location: string, frequency_days: int, status: string}> */
    private const CALIBRATION_EQUIPMENT = [
        ['code' => 'CAL-TQ-001', 'name' => 'Digital torque wrench, 20–200 N·m', 'location' => 'Quality Lab / Torque Bench', 'frequency_days' => 365, 'status' => 'overdue'],
        ['code' => 'CAL-CAL-001', 'name' => 'Digital caliper, 0–300 mm', 'location' => 'Quality Lab / Gauge Cabinet A', 'frequency_days' => 365, 'status' => 'due'],
        ['code' => 'CAL-MIC-001', 'name' => 'Outside micrometer, 0–25 mm', 'location' => 'Quality Lab / Gauge Cabinet A', 'frequency_days' => 365, 'status' => 'active'],
        ['code' => 'CAL-SP-001', 'name' => 'Granite surface plate, 600 × 900 mm', 'location' => 'Quality Lab / Metrology Room', 'frequency_days' => 730, 'status' => 'retired'],
        ['code' => 'CAL-PG-001', 'name' => 'Digital pressure gauge, 0–250 bar', 'location' => 'Injection Hall / Maintenance Cage', 'frequency_days' => 365, 'status' => 'active'],
        ['code' => 'CAL-SC-001', 'name' => 'Bench scale, 0–30 kg', 'location' => 'Incoming QC / Inspection Bench', 'frequency_days' => 365, 'status' => 'active'],
        ['code' => 'CAL-HG-001', 'name' => 'Digital height gauge, 0–600 mm', 'location' => 'Quality Lab / Metrology Room', 'frequency_days' => 365, 'status' => 'due'],
        ['code' => 'CAL-BG-001', 'name' => 'Bore gauge, 18–35 mm', 'location' => 'Quality Lab / Gauge Cabinet B', 'frequency_days' => 365, 'status' => 'active'],
        ['code' => 'CAL-PG-002', 'name' => 'Pin gauge set, 1–10 mm', 'location' => 'Quality Lab / Gauge Cabinet B', 'frequency_days' => 365, 'status' => 'overdue'],
        ['code' => 'CAL-CMP-001', 'name' => 'Optical comparator, 300 mm screen', 'location' => 'Quality Lab / Metrology Room', 'frequency_days' => 365, 'status' => 'active'],
        ['code' => 'CAL-TMP-001', 'name' => 'Digital probe thermometer, −50–300 °C', 'location' => 'Process Engineering / Lab', 'frequency_days' => 365, 'status' => 'active'],
        ['code' => 'CAL-FM-001', 'name' => 'Air flow meter, 0–500 L/min', 'location' => 'Utility Room / Chiller Loop', 'frequency_days' => 365, 'status' => 'due'],
        ['code' => 'CAL-CMM-001', 'name' => 'CMM reference artifact, 300 mm', 'location' => 'Quality Lab / CMM Room', 'frequency_days' => 365, 'status' => 'active'],
        ['code' => 'CAL-PT-001', 'name' => 'Injection pressure transducer, 0–200 MPa', 'location' => 'Injection Hall / IMM-08', 'frequency_days' => 180, 'status' => 'active'],
        ['code' => 'CAL-TC-001', 'name' => 'Portable temperature calibrator, 0–600 °C', 'location' => 'Maintenance Shop / Instrument Cabinet', 'frequency_days' => 365, 'status' => 'retired'],
        ['code' => 'CAL-TR-001', 'name' => 'Thread ring gauge set, M4–M12', 'location' => 'Quality Lab / Gauge Cabinet C', 'frequency_days' => 365, 'status' => 'active'],
        ['code' => 'CAL-FG-001', 'name' => 'Push-pull force gauge, 0–500 N', 'location' => 'Quality Lab / Test Bench', 'frequency_days' => 365, 'status' => 'due'],
        ['code' => 'CAL-DI-001', 'name' => 'Dial test indicator, 0–1 mm', 'location' => 'Quality Lab / Gauge Cabinet C', 'frequency_days' => 365, 'status' => 'active'],
    ];

    public function __construct(
        private readonly MaintenanceScheduleService $schedules,
        private readonly CalibrationService $calibrations,
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'maintenance-master-data';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Maintenance', 'Quality'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['production-assets', 'workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf;
        $asOfDate = $asOf->toDateString();

        $context->at($asOf, function () use ($context, $asOf, $asOfDate): void {
            $maintenanceActor = $this->activeUserForRole('maintenance_supervisor');
            $qualityActor = $this->activeUserForRole('qc_inspector');

            $context->asUser($maintenanceActor, function () use ($context, $asOf): void {
                $machineIndex = 0;
                Machine::query()->orderBy('id')->chunkById(100, function ($machines) use ($context, $asOf, &$machineIndex): void {
                    foreach ($machines as $machine) {
                        $this->seedMachineSchedules($context, $asOf, $machine, $machineIndex++);
                    }
                });

                $moldIndex = 0;
                Mold::query()->orderBy('id')->chunkById(100, function ($molds) use ($context, $asOf, &$moldIndex): void {
                    foreach ($molds as $mold) {
                        $this->seedMoldSchedules($context, $asOf, $mold, $moldIndex++);
                    }
                });
            });

            $this->seedCalibrationRegister($context, $asOf, $asOfDate, $qualityActor);
        });
    }

    private function seedMachineSchedules(DemoSeedContext $context, CarbonImmutable $asOf, Machine $machine, int $index): void
    {
        $machineCode = (string) $machine->machine_code;
        $this->upsertSchedule($context, [
            'maintainable_type' => MaintainableType::Machine->value,
            'maintainable_id' => (int) $machine->getKey(),
            'description' => "Calendar PM: {$machineCode} hydraulic, heater and safety inspection [DEMO]",
            'interval_type' => MaintenanceScheduleInterval::Days->value,
            'interval_value' => 30,
            'estimated_duration_minutes' => 120,
            'is_active' => true,
            'last_performed_at' => $this->calendarLastPerformed($asOf, $index, 30)->toDateString(),
        ]);
        $this->upsertSchedule($context, [
            'maintainable_type' => MaintainableType::Machine->value,
            'maintainable_id' => (int) $machine->getKey(),
            'description' => "Usage PM: {$machineCode} screw and barrel inspection every 500 running hours [DEMO]",
            'interval_type' => MaintenanceScheduleInterval::Hours->value,
            'interval_value' => 500,
            'estimated_duration_minutes' => 180,
            'is_active' => true,
            'last_performed_at' => $asOf->subDays(14 + ($index % 18))->startOfDay()->toDateString(),
        ]);
    }

    private function seedMoldSchedules(DemoSeedContext $context, CarbonImmutable $asOf, Mold $mold, int $index): void
    {
        $moldCode = (string) $mold->mold_code;
        $this->upsertSchedule($context, [
            'maintainable_type' => MaintainableType::Mold->value,
            'maintainable_id' => (int) $mold->getKey(),
            'description' => "Calendar PM: {$moldCode} clean vents and inspect cooling channels [DEMO]",
            'interval_type' => MaintenanceScheduleInterval::Days->value,
            'interval_value' => 90,
            'estimated_duration_minutes' => 180,
            'is_active' => true,
            'last_performed_at' => $this->calendarLastPerformed($asOf, $index, 90)->toDateString(),
        ]);

        $shotInterval = (int) ($mold->maintenance_frequency_shots ?? $mold->max_shots_before_maintenance);
        if ($shotInterval < 1) {
            throw new BusinessRuleException("Demo PM seeding requires a positive shot-maintenance interval for mold {$moldCode}.");
        }

        $this->upsertSchedule($context, [
            'maintainable_type' => MaintainableType::Mold->value,
            'maintainable_id' => (int) $mold->getKey(),
            'description' => "Usage PM: {$moldCode} inspect and service at {$shotInterval} shots [DEMO]",
            'interval_type' => MaintenanceScheduleInterval::Shots->value,
            'interval_value' => $shotInterval,
            'estimated_duration_minutes' => 240,
            'is_active' => true,
            'last_performed_at' => $asOf->subDays(20 + ($index % 25))->startOfDay()->toDateString(),
        ], "Auto PM: {$mold->name} every {$shotInterval} shots");
    }

    /** @param array<string, mixed> $data */
    private function upsertSchedule(DemoSeedContext $context, array $data, ?string $legacyMoldShotDescription = null): void
    {
        $query = MaintenanceSchedule::withTrashed()
            ->where('maintainable_type', $data['maintainable_type'])
            ->where('maintainable_id', $data['maintainable_id'])
            ->where('interval_type', $data['interval_type']);

        $schedule = (clone $query)->where('description', $data['description'])->first();
        if ($schedule === null && $legacyMoldShotDescription !== null) {
            $schedule = (clone $query)->where('description', $legacyMoldShotDescription)->first();
        }

        if ($schedule?->trashed()) {
            $this->schedules->restore($schedule);
            $schedule = $schedule->fresh();
        }

        if ($schedule === null) {
            $schedule = $this->schedules->create($data);
        } else {
            $schedule = $this->schedules->update($schedule, $data);
        }

        $key = $data['maintainable_type'] === MaintainableType::Machine->value ? 'machine' : 'mold';
        $context->records()->remember('maintenance.schedule.'.$key.'.'.$data['maintainable_id'].'.'.$data['interval_type'], $schedule);
    }

    private function seedCalibrationRegister(
        DemoSeedContext $context,
        CarbonImmutable $asOf,
        string $asOfDate,
        User $actor,
    ): void {
        $dueWindow = $this->settings->requiredInt('quality.calibration.due_window_days', 0, 65535);
        $count = match ($context->options->scale) {
            'small' => 6,
            'demo' => 12,
            'full' => 18,
        };

        foreach (array_slice(self::CALIBRATION_EQUIPMENT, 0, $count) as $definition) {
            $next = match ($definition['status']) {
                'overdue' => $asOf->subDays(1 + ($this->equipmentIndex($definition['code']) % 5)),
                'due' => $asOf->addDays(intdiv($dueWindow, 2)),
                'retired' => $asOf->subDays(45),
                default => $asOf->addDays($dueWindow + 45),
            };
            $last = $next->subDays($definition['frequency_days']);
            $status = $definition['status'];
            $data = [
                'equipment_code' => $definition['code'],
                'name' => $definition['name'],
                'location' => $definition['location'],
                'last_calibration_date' => $last->toDateString(),
                'next_calibration_date' => $next->toDateString(),
                'frequency_days' => $definition['frequency_days'],
                'status' => $status,
                'responsible' => 'QC Inspector',
                'remarks' => "Synthetic demo measuring equipment; schedule status is evaluated by the application as of {$asOfDate}.",
            ];

            $record = CalibrationRecord::query()->where('equipment_code', $definition['code'])->first();
            $record = $context->asUser($actor, fn (): CalibrationRecord => $record === null
                ? $this->calibrations->create($data)
                : $this->calibrations->update($record, $data));
            $context->records()->remember('quality.calibration.'.$definition['code'], $record);
        }
    }

    private function calendarLastPerformed(CarbonImmutable $asOf, int $index, int $intervalDays): CarbonImmutable
    {
        $nextDue = match ($index % 8) {
            0 => $asOf->subDays(3),
            1 => $asOf->addDays(2),
            default => $asOf->addDays(12 + ($index % 35)),
        };

        return $nextDue->subDays($intervalDays)->startOfDay();
    }

    private function activeUserForRole(string $roleSlug): User
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
            ->orderBy('id')
            ->first()
            ?? throw new RuntimeException("Demo maintenance master data requires an active {$roleSlug} actor.");
    }

    private function equipmentIndex(string $code): int
    {
        foreach (self::CALIBRATION_EQUIPMENT as $index => $definition) {
            if ($definition['code'] === $code) {
                return $index;
            }
        }

        return 0;
    }
}
