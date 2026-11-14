<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Position;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationReferenceDataLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_hierarchical_organization_catalog_idempotently(): void
    {
        $options = new DemoSeedOptions(
            asOf: CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            scale: 'small',
            seed: 20261114,
            only: 'organization',
        );

        $runner = app(DemoSeedRunner::class);
        $runner->run($options);

        $departmentIds = Department::query()->orderBy('code')->pluck('id', 'code')->all();

        $this->assertCount(15, $departmentIds);
        $this->assertSame(37, Position::query()->count());
        $this->assertSame(
            $departmentIds['MAINT'],
            Department::query()->where('code', 'MOLD')->value('parent_id'),
        );
        $this->assertNull(Department::query()->where('code', 'EXEC')->value('parent_id'));
        $this->assertSame(
            0,
            Position::query()->whereNotNull('salary_grade')->count(),
            'The organization layer must not invent salary grades while pay policy is unresolved.',
        );

        $runner->run($options);

        $this->assertSame($departmentIds, Department::query()->orderBy('code')->pluck('id', 'code')->all());
        $this->assertCount(15, Department::all());
        $this->assertSame(37, Position::query()->count());
    }
}
