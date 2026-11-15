<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\Auth\Models\User;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\MRP\Models\Machine;
use App\Modules\MRP\Models\Mold;
use App\Modules\Quality\Models\CalibrationRecord;
use Carbon\CarbonImmutable;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceMasterDataLayerTest extends TestCase
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

    public function test_it_seeds_calendar_and_usage_pm_plans_and_calibration_register_from_current_services(): void
    {
        $asOf = '2026-11-14';
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse($asOf, 'Asia/Manila'),
            scale: 'small',
            seed: 20261114,
            only: 'maintenance-master-data',
        );
        $runner = app(DemoSeedRunner::class);

        $runner->run($options);

        $machines = Machine::query()->orderBy('machine_code')->get();
        $molds = Mold::query()->orderBy('mold_code')->get();
        $schedules = MaintenanceSchedule::query()->orderBy('id')->get();
        $calibrations = CalibrationRecord::query()->orderBy('equipment_code')->get();
        $maintenanceActor = User::query()->whereHas('role', fn ($query) => $query->where('slug', 'maintenance_supervisor'))->firstOrFail();
        $qualityActor = User::query()->whereHas('role', fn ($query) => $query->where('slug', 'qc_inspector'))->firstOrFail();

        $this->assertCount($machines->count() * 2 + $molds->count() * 2, $schedules);
        $this->assertNotEmpty($machines);
        $this->assertNotEmpty($molds);
        $this->assertSame($machines->count(), $schedules->filter(fn (MaintenanceSchedule $schedule): bool => $schedule->maintainable_type->value === 'machine' && $schedule->interval_type->value === 'hours')->count());
        $this->assertSame($machines->count(), $schedules->filter(fn (MaintenanceSchedule $schedule): bool => $schedule->maintainable_type->value === 'machine' && $schedule->interval_type->value === 'days')->count());
        $this->assertSame($molds->count(), $schedules->filter(fn (MaintenanceSchedule $schedule): bool => $schedule->maintainable_type->value === 'mold' && $schedule->interval_type->value === 'shots')->count());
        $this->assertSame($molds->count(), $schedules->filter(fn (MaintenanceSchedule $schedule): bool => $schedule->maintainable_type->value === 'mold' && $schedule->interval_type->value === 'days')->count());
        $this->assertGreaterThan(0, $schedules->filter(fn (MaintenanceSchedule $schedule): bool => $schedule->interval_type->value === 'days' && $schedule->next_due_at?->lt(CarbonImmutable::parse($asOf, 'Asia/Manila')))->count());
        $this->assertGreaterThan(0, $schedules->filter(fn (MaintenanceSchedule $schedule): bool => $schedule->interval_type->value === 'days' && $schedule->next_due_at?->gte(CarbonImmutable::parse($asOf, 'Asia/Manila')))->count());
        $this->assertTrue($schedules->every(fn (MaintenanceSchedule $schedule): bool => $schedule->created_at->toDateString() === $asOf
            && $schedule->updated_at->toDateString() === $asOf
            && $schedule->is_active));

        $this->assertCount(6, $calibrations);
        $this->assertEqualsCanonicalizing(['active', 'due', 'overdue', 'retired'], $calibrations->pluck('status')->map(fn ($status) => $status->value)->unique()->all());
        $this->assertTrue($calibrations->every(fn (CalibrationRecord $record): bool => $record->created_at->toDateString() === $asOf
            && $record->updated_at->toDateString() === $asOf
            && ($record->last_calibration_date === null || $record->last_calibration_date->toDateString() <= $asOf)));

        $scheduleAudit = DB::table('audit_logs')
            ->where('model_type', (new MaintenanceSchedule)->getMorphClass())
            ->whereIn('model_id', $schedules->modelKeys())
            ->where('user_id', $maintenanceActor->id)
            ->whereIn('action', ['created', 'updated'])
            ->get();
        $calibrationAudit = DB::table('audit_logs')
            ->where('model_type', (new CalibrationRecord)->getMorphClass())
            ->whereIn('model_id', $calibrations->modelKeys())
            ->where('action', 'created')
            ->get();

        $this->assertSame($schedules->count(), $scheduleAudit->pluck('model_id')->unique()->count());
        $this->assertTrue($scheduleAudit->every(fn ($row): bool => (int) $row->user_id === (int) $maintenanceActor->id
            && $row->actor_type === 'user'
            && CarbonImmutable::parse($row->created_at)->toDateString() === $asOf));
        $this->assertCount($calibrations->count(), $calibrationAudit);
        $this->assertTrue($calibrationAudit->every(fn ($row): bool => (int) $row->user_id === (int) $qualityActor->id
            && $row->actor_type === 'user'
            && CarbonImmutable::parse($row->created_at)->toDateString() === $asOf));

        $before = [
            DB::table('maintenance_schedules')->orderBy('id')->get()->toArray(),
            DB::table('calibration_records')->orderBy('id')->get()->toArray(),
            DB::table('audit_logs')->whereIn('model_type', [
                (new MaintenanceSchedule)->getMorphClass(),
                (new CalibrationRecord)->getMorphClass(),
            ])->orderBy('id')->get()->toArray(),
        ];
        $runner->run($options);
        $after = [
            DB::table('maintenance_schedules')->orderBy('id')->get()->toArray(),
            DB::table('calibration_records')->orderBy('id')->get()->toArray(),
            DB::table('audit_logs')->whereIn('model_type', [
                (new MaintenanceSchedule)->getMorphClass(),
                (new CalibrationRecord)->getMorphClass(),
            ])->orderBy('id')->get()->toArray(),
        ];

        $this->assertEquals($before, $after);
    }
}
