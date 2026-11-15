<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Auth\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * REC-01 — seed the known Segregation-of-Duties conflicts. Payroll compute and
 * approval are intentionally omitted because small organizations may have one
 * authorized Finance Officer complete the lifecycle; every step remains
 * permission-gated and individually audited.
 */
class SodConflictRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'code'     => 'po_create_vs_approve',
                'name'     => 'Create PO vs Approve PO',
                'a'        => 'purchasing.po.create',
                'b'        => 'purchasing.po.approve',
                'severity' => 'high',
                'rationale'=> 'One user raising a purchase order and approving it can commit spend unchecked.',
            ],
            [
                'code'     => 'vendor_create_vs_po_approve',
                'name'     => 'Manage Vendors vs Approve PO',
                'a'        => 'accounting.vendors.manage',
                'b'        => 'purchasing.po.approve',
                'severity' => 'high',
                'rationale'=> 'Onboarding a supplier and approving spend to it enables self-dealing / fake vendors.',
            ],
            [
                'code'     => 'pr_create_vs_po_approve',
                'name'     => 'Create PR vs Approve PO',
                'a'        => 'purchasing.pr.create',
                'b'        => 'purchasing.po.approve',
                'severity' => 'medium',
                'rationale'=> 'Requesting a purchase and approving the resulting order bypasses the intended review chain.',
            ],
            [
                'code'     => 'je_create_vs_post',
                'name'     => 'Create JE vs Post JE',
                'a'        => 'accounting.journal.create',
                'b'        => 'accounting.journal.post',
                'severity' => 'high',
                'rationale'=> 'Drafting and posting a journal entry lets one user move money to the GL with no checker.',
            ],
            [
                'code'     => 'salary_request_vs_act',
                'name'     => 'Request Salary Adjustment vs Approve it',
                'a'        => 'hr.salary_adjustments.request',
                'b'        => 'hr.salary_adjustments.act',
                'severity' => 'high',
                'rationale'=> 'Requesting and approving a pay change is the primary payroll-fraud path.',
            ],
        ];

        $slugToId = Permission::query()->pluck('id', 'slug');

        foreach ($rules as $r) {
            $aId = $slugToId[$r['a']] ?? null;
            $bId = $slugToId[$r['b']] ?? null;
            if ($aId === null || $bId === null) {
                // Permission not present in this environment — skip rather than fail the seed.
                continue;
            }

            DB::table('sod_conflict_rules')->updateOrInsert(
                ['code' => $r['code']],
                [
                    'name'            => $r['name'],
                    'permission_a_id' => $aId,
                    'permission_b_id' => $bId,
                    'severity'        => $r['severity'],
                    'rationale'       => $r['rationale'],
                    'active'          => true,
                    'updated_at'      => now(),
                    'created_at'      => now(),
                ],
            );
        }

        // Keep the former conflict row for audit history, but stop reporting
        // it as active under the supported single-officer payroll workflow.
        DB::table('sod_conflict_rules')->where('code', 'payroll_compute_vs_approve')->update([
            'active' => false,
            'rationale' => 'Inactive: authorized users may complete multiple payroll lifecycle stages in a single-officer workflow. Each action still requires its permission and creates an audit record.',
            'updated_at' => now(),
        ]);
    }
}
