<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Position;
use App\Modules\HR\Services\DepartmentService;
use App\Modules\HR\Services\PositionService;

final class OrganizationReferenceDataLayer extends DemoSeeder
{
    private const DEPARTMENTS = [
        ['code' => 'EXEC', 'name' => 'Executive', 'parent' => null],
        ['code' => 'HR', 'name' => 'Human Resources', 'parent' => 'EXEC'],
        ['code' => 'FIN', 'name' => 'Finance & Accounting', 'parent' => 'EXEC'],
        ['code' => 'PROD', 'name' => 'Production', 'parent' => 'EXEC'],
        ['code' => 'QC', 'name' => 'Quality Control', 'parent' => 'EXEC'],
        ['code' => 'WH', 'name' => 'Warehouse & Logistics', 'parent' => 'EXEC'],
        ['code' => 'PUR', 'name' => 'Purchasing & Procurement', 'parent' => 'EXEC'],
        ['code' => 'PPC', 'name' => 'Production Planning', 'parent' => 'EXEC'],
        ['code' => 'MAINT', 'name' => 'Maintenance & Engineering', 'parent' => 'EXEC'],
        ['code' => 'MOLD', 'name' => 'Mold Department', 'parent' => 'MAINT'],
        ['code' => 'IMPEX', 'name' => 'Import/Export', 'parent' => 'PUR'],
        ['code' => 'SALES', 'name' => 'Sales & Marketing', 'parent' => 'EXEC'],
        ['code' => 'CS', 'name' => 'Customer Service', 'parent' => 'EXEC'],
        ['code' => 'ADMIN', 'name' => 'Admin & General Affairs', 'parent' => 'EXEC'],
        ['code' => 'IT', 'name' => 'Information Technology', 'parent' => 'EXEC'],
    ];

    /** @var array<string, list<string>> */
    private const POSITIONS = [
        'EXEC' => ['Chairman', 'President', 'Vice President'],
        'HR' => ['HR Manager', 'Gen Admin Officer', 'HR Staff'],
        'FIN' => ['Accounting Officer', 'Accounting Staff'],
        'PROD' => ['Plant Manager', 'Production Manager', 'Production Head', 'Processing Head', 'Production Operator'],
        'QC' => ['QC/QA Manager', 'QC/QA Head', 'QC Inspector', 'Management System Head'],
        'WH' => ['Warehouse Head', 'Warehouse Staff', 'Driver'],
        'PUR' => ['Purchasing Officer', 'Purchasing Staff'],
        'PPC' => ['PPC Head', 'PPC Staff'],
        'MAINT' => ['Maintenance Head', 'Maintenance Technician'],
        'MOLD' => ['Mold Manager', 'Mold Technician'],
        'IMPEX' => ['ImpEx Officer', 'ImpEx Staff'],
        'SALES' => ['Sales Officer', 'Sales Manager'],
        'CS' => ['Customer Service Officer', 'Customer Service Manager'],
        'ADMIN' => ['Admin Staff'],
        'IT' => ['System Administrator', 'IT Staff'],
    ];

    public function __construct(
        private readonly DepartmentService $departments,
        private readonly PositionService $positions,
    ) {}

    public function key(): string
    {
        return 'organization';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['HR'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return [];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $context->at($context->options->asOf, function () use ($context): void {
            $departmentIds = [];

            foreach (self::DEPARTMENTS as $definition) {
                $parentCode = $definition['parent'];
                $parentId = $parentCode === null ? null : ($departmentIds[$parentCode] ?? null);

                if ($parentCode !== null && $parentId === null) {
                    throw new \LogicException("Organization parent {$parentCode} must be seeded before {$definition['code']}.");
                }

                $department = $this->departmentFor($definition['code']);
                if ($department === null) {
                    $department = $this->departments->create([
                        'code' => $definition['code'],
                        'name' => $definition['name'],
                        'parent_id' => $parentId,
                        'is_active' => true,
                    ]);
                } else {
                    if ($department->trashed()) {
                        $this->departments->restore($department);
                        $department->refresh();
                    }

                    $changes = [];
                    if ($department->name !== $definition['name']) {
                        $changes['name'] = $definition['name'];
                    }
                    if ((int) ($department->parent_id ?? 0) !== (int) ($parentId ?? 0)) {
                        $changes['parent_id'] = $parentId;
                    }
                    if (! $department->is_active) {
                        $changes['is_active'] = true;
                    }

                    if ($changes !== []) {
                        $department = $this->departments->update($department, $changes);
                    }
                }

                $departmentIds[$definition['code']] = (int) $department->getKey();
                $context->records()->remember('organization.department.'.$definition['code'], $department);
            }

            foreach (self::POSITIONS as $departmentCode => $titles) {
                $departmentId = $departmentIds[$departmentCode];

                foreach ($titles as $title) {
                    $position = Position::withTrashed()
                        ->where('department_id', $departmentId)
                        ->where('title', $title)
                        ->first();

                    if ($position === null) {
                        $position = $this->positions->create([
                            'title' => $title,
                            'department_id' => $departmentId,
                        ]);
                    } elseif ($position->trashed()) {
                        $this->positions->restore($position);
                        $position->refresh();
                    }

                    $key = 'organization.position.'.$departmentCode.'.'.strtolower(str_replace(' ', '-', $title));
                    $context->records()->remember($key, $position);
                }
            }
        });
    }

    private function departmentFor(string $code): ?Department
    {
        return Department::withTrashed()->where('code', $code)->first();
    }
}
