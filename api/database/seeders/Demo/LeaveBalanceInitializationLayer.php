<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\HR\Models\Employee;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\LeaveBalanceService;
use RuntimeException;

/** Initializes the app-defined leave entitlements for the active demo workforce. */
final class LeaveBalanceInitializationLayer extends DemoSeeder
{
    public function __construct(private readonly LeaveBalanceService $balances) {}

    public function key(): string
    {
        return 'hr-leave-balances';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['HR', 'Leave', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        if (! LeaveType::query()->where('is_active', true)->exists()) {
            throw new RuntimeException('HR leave balances require active app leave types; run the system LeaveTypeSeeder first.');
        }

        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->setTime(23, 59, 59);
        $context->at($asOf, function (): void {
            Employee::query()
                ->active()
                ->orderBy('id')
                ->chunkById(100, function ($employees): void {
                    foreach ($employees as $employee) {
                        $this->balances->seedProratedFor($employee);
                    }
                });
        });
    }
}
