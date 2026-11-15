<?php

declare(strict_types=1);

namespace Tests\Feature\Loans;

use App\Common\Models\WorkflowDefinition;
use App\Common\Services\ApprovalService;
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

class CompanyLoanThresholdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ChartOfAccountsSeeder::class, WorkflowSeeder::class]);
    }

    public static function loanCases(): array
    {
        return [
            [LoanType::CompanyLoan->value, '5000.00'],
            [LoanType::CashAdvance->value, '2000.00'],
        ];
    }

    #[DataProvider('loanCases')]
    public function test_employee_loan_uses_only_hr_then_finance_approvals(string $loanType, string $principal): void
    {
        $employee = Employee::factory()->create(['basic_monthly_salary' => '100000.00', 'pay_type' => 'monthly']);
        $borrower = User::factory()->withRole('employee')->create(['employee_id' => $employee->id]);
        $response = $this->actingAs($borrower)->postJson('/api/v1/hr/self-service/loans', [
            'loan_type' => $loanType, 'amount' => $principal, 'periods' => 6,
            'reason' => 'Approval chain regression',
        ])->assertCreated();
        $loan = EmployeeLoan::findOrFail(EmployeeLoan::tryDecodeHash($response->json('data.id')));
        $records = $loan->approvalRecords;
        $this->assertSame(['hr_officer', 'finance_officer'], $records->pluck('role_slug')->all());
        $this->assertSame(2, $loan->approval_chain_size);
        $this->assertSame(['pending', 'pending'], $records->pluck('action')->all());
        $this->assertNull($loan->disbursement_journal_entry_id);

        $hr = User::factory()->withRole('hr_officer')->create();
        $finance = User::factory()->withRole('finance_officer')->create();

        $this->actingAs($finance)->patchJson("/api/v1/loans/{$loan->hash_id}/approve")->assertForbidden();
        $this->assertSame('pending', $records->first()->fresh()->action);
        $this->actingAs($hr)->getJson("/api/v1/loans/{$loan->hash_id}")->assertOk()->assertJsonPath('data.actions.can_approve', true);
        $this->actingAs($hr)->patchJson("/api/v1/loans/{$loan->hash_id}/approve")->assertOk();
        $this->assertSame(LoanStatus::Pending, $loan->fresh()->status);
        $this->assertNull($loan->fresh()->disbursement_journal_entry_id);
        $this->actingAs($finance)->patchJson("/api/v1/loans/{$loan->hash_id}/approve")->assertOk();
        $this->assertSame(LoanStatus::Active, $loan->fresh()->status);
        $this->assertTrue(app(ApprovalService::class)->isFullyApproved($loan->fresh()));
        $this->assertNotNull($loan->fresh()->disbursement_journal_entry_id);
        $this->assertSame($principal, (string) $loan->fresh()->balance);
    }

    public function test_migration_preserves_existing_approvals_and_cash_advances_and_supports_rollback(): void
    {
        $workflow = WorkflowDefinition::where('workflow_type', 'company_loan')->firstOrFail();
        $oldSteps = [
            ['order' => 1, 'role' => 'department_head', 'label' => 'Department Head'],
            ['order' => 2, 'role' => 'production_manager', 'label' => 'Manager'],
            ['order' => 3, 'role' => 'finance_officer', 'label' => 'Finance / Accounting'],
            ['order' => 4, 'role' => 'vice_president', 'label' => 'VP / Approver'],
        ];
        $workflow->update(['steps' => $oldSteps]);
        $employee = Employee::factory()->create(['basic_monthly_salary' => '20000.00']);
        $head = User::factory()->withRole('department_head')->create([
            'employee_id' => Employee::factory()->create(['department_id' => $employee->department_id])->id,
        ]);
        $loan = app(LoanService::class)->request($employee->id, LoanType::CompanyLoan, ['principal' => '5000.00', 'pay_periods' => 6]);
        app(LoanService::class)->approve($loan, $head);
        $before = $loan->approvalRecords()->get()->toArray();
        $cashAdvance = WorkflowDefinition::where('workflow_type', 'cash_advance')->firstOrFail()->toArray();

        $migration = require database_path('migrations/2026_10_10_000100_update_company_loan_approval_policy.php');
        $migration->up();
        $migration->up();
        $this->assertSame('hr_officer', $workflow->fresh()->steps[1]['role']);
        $this->assertSame('50000.00', $workflow->fresh()->steps[3]['threshold']);
        $this->assertSame($before, $loan->approvalRecords()->get()->toArray());
        $this->assertSame($cashAdvance, WorkflowDefinition::where('workflow_type', 'cash_advance')->firstOrFail()->toArray());

        $migration->down();
        $this->assertSame($oldSteps, $workflow->fresh()->steps);
        $this->assertSame($before, $loan->approvalRecords()->get()->toArray());
    }

    public function test_loan_options_expose_the_current_approval_chain_and_amount_limits(): void
    {
        $types = collect(app(LoanService::class)->types())->keyBy('value');
        $this->assertSame(['HR Officer', 'Finance Officer'], $types['company_loan']['approval_chain']);
        $this->assertSame('1000.00', $types['company_loan']['principal_min']);
        $this->assertSame('5000.00', $types['company_loan']['principal_max']);
        $this->assertSame(2, $types['company_loan']['approval_steps']);
        $this->assertNull($types['company_loan']['vp_approval_threshold']);
        $this->assertSame(['HR Officer', 'Finance Officer'], $types['cash_advance']['approval_chain']);
        $this->assertSame('500.00', $types['cash_advance']['principal_min']);
        $this->assertSame('2000.00', $types['cash_advance']['principal_max']);
        $this->assertNull($types['cash_advance']['vp_approval_threshold']);
    }
}
