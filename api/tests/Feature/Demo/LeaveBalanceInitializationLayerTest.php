<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedOptions;
use App\Modules\HR\Models\Employee;
use App\Modules\Leave\Models\EmployeeLeaveBalance;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\LeaveBalanceInitializationLayer;
use Database\Seeders\LeaveTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LeaveBalanceInitializationLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeaveTypeSeeder::class);

        $connection = (string) config('database.default');
        $host = (string) config("database.connections.{$connection}.host");
        config([
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test'],
            'demo.seeding.allowed_hosts' => [$host],
        ]);
    }

    public function test_it_initializes_current_year_balances_through_the_service_and_preserves_consumption_on_replay(): void
    {
        $employee = Employee::factory()->create(['date_hired' => '2025-04-12']);
        $options = new DemoSeedOptions(CarbonImmutable::parse('2026-11-15', 'Asia/Manila'), 'small', 20261115);
        $layer = app(LeaveBalanceInitializationLayer::class);
        $context = (new DemoSeedContext($options))->forLayer($layer->key());

        $layer->run($context);

        $types = LeaveType::query()->where('is_active', true)->get();
        $this->assertCount(8, $types);
        $this->assertSame(8, EmployeeLeaveBalance::query()->where('employee_id', $employee->id)->where('year', 2026)->count());
        $balance = EmployeeLeaveBalance::query()->where('employee_id', $employee->id)->where('year', 2026)->firstOrFail();
        $this->assertSame((string) $types->firstWhere('id', $balance->leave_type_id)->default_balance, (string) $balance->total_credits);
        $this->assertSame('2026-11-15', $balance->created_at->toDateString());

        app(LeaveBalanceService::class)->consume($employee->id, $balance->leave_type_id, 2026, 1.0);
        $layer->run($context);

        $balance->refresh();
        $this->assertSame(8, EmployeeLeaveBalance::query()->where('employee_id', $employee->id)->where('year', 2026)->count());
        $this->assertSame('1.0', (string) $balance->used);
        $this->assertSame(bcsub((string) $balance->total_credits, '1.0', 1), (string) $balance->remaining);
    }
}
