<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // New loan requests go to HR first, then Finance. Existing approval
        // records keep their submitted workflow snapshot and history.
        $this->updateDefinition('cash_advance', [
            ['order' => 1, 'role' => 'hr_officer', 'label' => 'HR Officer'],
            ['order' => 2, 'role' => 'finance_officer', 'label' => 'Finance Officer'],
        ]);
        $this->updateDefinition('company_loan', [
            ['order' => 1, 'role' => 'hr_officer', 'label' => 'HR Officer'],
            ['order' => 2, 'role' => 'finance_officer', 'label' => 'Finance Officer'],
        ]);
    }

    public function down(): void
    {
        $this->updateDefinition('cash_advance', [
            ['order' => 1, 'role' => 'department_head', 'label' => 'Department Head'],
            ['order' => 2, 'role' => 'finance_officer', 'label' => 'Finance / Accounting'],
            ['order' => 3, 'role' => 'vice_president', 'label' => 'VP / Approver'],
        ]);
        $this->updateDefinition('company_loan', [
            ['order' => 1, 'role' => 'department_head', 'label' => 'Department Head'],
            ['order' => 2, 'role' => 'hr_officer', 'label' => 'HR Officer'],
            ['order' => 3, 'role' => 'finance_officer', 'label' => 'Finance / Accounting'],
            ['order' => 4, 'role' => 'vice_president', 'label' => 'VP / Approver', 'threshold' => '50000.00'],
        ]);
    }

    /** @param array<int, array{order:int, role:string, label:string, threshold?:string}> $steps */
    private function updateDefinition(string $workflowType, array $steps): void
    {
        DB::table('workflow_definitions')->where('workflow_type', $workflowType)->update([
            'steps' => json_encode($steps, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
};
