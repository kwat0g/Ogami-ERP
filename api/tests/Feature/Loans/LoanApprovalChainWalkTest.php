<?php

declare(strict_types=1);

namespace Tests\Feature\Loans;

use App\Common\Services\ApprovalService;
use App\Common\Services\SettingsService;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\Loans\Enums\LoanStatus;
use App\Modules\Loans\Enums\LoanType;
use App\Modules\Loans\Models\EmployeeLoan;
use App\Modules\Loans\Services\LoanService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoanApprovalChainWalkTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ChartOfAccountsSeeder::class, WorkflowSeeder::class]);

        $settings = app(SettingsService::class);
        foreach (['company_loan', 'cash_advance'] as $type) {
            $settings->set("loans.{$type}.annual_interest_rate", '0', 'loans');
            $settings->set("loans.{$type}.max_salary_multiplier", '1.0', 'loans');
        }
        $settings->set('loans.max_pay_periods', '60', 'loans');

        $this->service = app(LoanService::class);
    }

    public static function loanCases(): array
    {
        return [
            [LoanType::CompanyLoan->value, '5000.00'],
            [LoanType::CashAdvance->value, '2000.00'],
        ];
    }

    private function userWithRole(string $slug, ?int $employeeId = null): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('slug', $slug)->value('id'),
            'employee_id' => $employeeId ?? Employee::factory()->create()->id,
        ]);
    }

    private function pendingLoanFor(Employee $employee, LoanType $type, string $principal): EmployeeLoan
    {
        return $this->service->request(
            $employee->id,
            $type,
            ['principal' => $principal, 'pay_periods' => 5, 'purpose' => 'Approval chain test'],
        )->fresh(['employee']);
    }

    #[DataProvider('loanCases')]
    public function test_each_new_loan_type_uses_hr_then_finance_and_finance_completes_it(string $loanType, string $principal): void
    {
        $requester = Employee::factory()->create(['basic_monthly_salary' => '100000.00', 'pay_type' => 'monthly']);
        $loan = $this->pendingLoanFor($requester, LoanType::from($loanType), $principal);
        $records = $loan->approvalRecords;

        $this->assertSame(['hr_officer', 'finance_officer'], $records->pluck('role_slug')->all());
        $this->assertSame(['pending', 'pending'], $records->pluck('action')->all());
        $this->assertSame(2, $loan->approval_chain_size);

        $hr = $this->userWithRole('hr_officer');
        $finance = $this->userWithRole('finance_officer');

        $this->actingAs($finance)
            ->patchJson("/api/v1/loans/{$loan->hash_id}/approve")
            ->assertForbidden();
        $this->assertSame('pending', $records->first()->fresh()->action);

        $this->actingAs($hr)
            ->patchJson("/api/v1/loans/{$loan->hash_id}/approve")
            ->assertOk();
        $this->assertSame(LoanStatus::Pending, $loan->fresh()->status);
        $this->assertSame(['approved', 'pending'], $loan->approvalRecords()->orderBy('step_order')->pluck('action')->all());

        $this->actingAs($finance)
            ->patchJson("/api/v1/loans/{$loan->hash_id}/approve")
            ->assertOk();
        $this->assertTrue(app(ApprovalService::class)->isFullyApproved($loan->fresh()));
        $this->assertSame(LoanStatus::Active, $loan->fresh()->status);
        $this->assertNotNull($loan->fresh()->disbursement_journal_entry_id);
    }

    public function test_loan_detail_actions_follow_the_hr_then_finance_step(): void
    {
        $requester = Employee::factory()->create(['basic_monthly_salary' => '10000.00']);
        $loan = $this->pendingLoanFor($requester, LoanType::CashAdvance, '2000.00');
        $hr = $this->userWithRole('hr_officer');
        $finance = $this->userWithRole('finance_officer');

        $this->actingAs($hr)
            ->getJson("/api/v1/loans/{$loan->hash_id}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_approve', true);
        $this->actingAs($finance)
            ->getJson("/api/v1/loans/{$loan->hash_id}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_approve', false);

        $this->actingAs($hr)->patchJson("/api/v1/loans/{$loan->hash_id}/approve")->assertOk();
        $this->actingAs($hr)
            ->getJson("/api/v1/loans/{$loan->hash_id}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_approve', false);
        $this->actingAs($finance)
            ->getJson("/api/v1/loans/{$loan->hash_id}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_approve', true);
    }

    public function test_hr_submitter_cannot_approve_their_own_loan_request(): void
    {
        $employee = Employee::factory()->create(['basic_monthly_salary' => '10000.00']);
        $submitter = $this->userWithRole('hr_officer', $employee->id);
        $loan = $this->pendingLoanFor($employee, LoanType::CompanyLoan, '5000.00');

        $this->actingAs($submitter)
            ->patchJson("/api/v1/loans/{$loan->hash_id}/approve")
            ->assertForbidden();

        $peerHr = $this->userWithRole('hr_officer');
        $this->actingAs($peerHr)
            ->patchJson("/api/v1/loans/{$loan->hash_id}/approve")
            ->assertOk();
    }
}
