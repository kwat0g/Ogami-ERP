<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GovernmentScheduleVersionEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @param array<int, string> $slugs */
    private function userWithPermissions(array $slugs): User
    {
        $role = Role::query()->create([
            'name' => 'Government schedule reviewer '.uniqid(),
            'slug' => 'gov_schedule_'.uniqid(),
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('slug', $slugs)->pluck('id')->all());

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_import_requires_source_metadata_and_publish_requires_independent_reviewer(): void
    {
        $uploader = $this->userWithPermissions([
            'payroll.gov_tables.manage',
            'admin.gov_tables.manage',
        ]);
        $reviewer = $this->userWithPermissions(['admin.gov_tables.manage']);
        $csv = UploadedFile::fake()->createWithContent('sss-2026.csv', implode("\n", [
            'bracket_min,bracket_max,ee_amount,er_amount,effective_date',
            '0,999999.99,100,200,2026-01-01',
        ]));

        $this->actingAs($uploader)
            ->post('/api/v1/gov-tables/sss/import', ['csv' => $csv], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_title', 'source_url']);

        $csv = UploadedFile::fake()->createWithContent('sss-2026.csv', implode("\n", [
            'bracket_min,bracket_max,ee_amount,er_amount,effective_date',
            '0,999999.99,100,200,2026-01-01',
        ]));
        $import = $this->actingAs($uploader)
            ->post('/api/v1/gov-tables/sss/import', [
                'csv' => $csv,
                'source_title' => 'Official SSS contribution schedule',
                'source_url' => 'https://www.sss.gov.ph/contribution-schedule',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version.row_count', 1)
            ->assertJsonPath('data.version.source_title', 'Official SSS contribution schedule')
            ->assertJsonPath('data.version.source_file_sha256', hash('sha256', "bracket_min,bracket_max,ee_amount,er_amount,effective_date\n0,999999.99,100,200,2026-01-01"))
            ->assertJsonMissingPath('data.version.imported_by')
            ->json('data.version.id');

        $this->actingAs($uploader)
            ->postJson("/api/v1/admin/gov-tables/versions/{$import}/publish", ['confirm_completeness' => true])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A different authorized reviewer must publish this imported schedule.');

        $this->actingAs($reviewer)
            ->postJson("/api/v1/admin/gov-tables/versions/{$import}/publish", ['confirm_completeness' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.completeness_confirmed', true)
            ->assertJsonPath('data.publisher.id', $reviewer->hash_id);

        $this->actingAs($reviewer)
            ->getJson('/api/v1/admin/gov-tables/versions?agency=sss')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'published')
            ->assertJsonPath('data.0.can_publish', false);
    }
}
