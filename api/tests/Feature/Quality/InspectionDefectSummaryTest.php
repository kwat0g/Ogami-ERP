<?php

declare(strict_types=1);

namespace Tests\Feature\Quality;

use App\Modules\Accounting\Models\Vendor;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Models\Product;
use App\Modules\Inventory\Models\GoodsReceiptNote;
use App\Modules\Inventory\Models\GrnItem;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Production\Models\WorkOrderOutput;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderItem;
use App\Modules\Quality\Models\Inspection;
use App\Modules\Quality\Models\InspectionMeasurement;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InspectionDefectSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_incoming_and_outgoing_inspections_can_store_one_summary_with_multiple_private_photos(): void
    {
        $inspector = User::factory()->withRole('qc_inspector')->create(['is_active' => true]);
        [$incoming] = $this->incomingInspection($inspector);
        $outgoing = $this->outgoingInspection($inspector);

        foreach ([$incoming, $outgoing] as $inspection) {
            $this->actingAs($inspector)->put($this->summaryUrl($inspection), [
                'category' => 'surface_damage',
                'severity' => 'major',
                'affected_quantity' => 2,
                'description' => 'Two pieces have a cracked edge.',
                'photos' => [UploadedFile::fake()->image('defect-one.jpg'), UploadedFile::fake()->image('defect-two.png')],
            ])->assertSuccessful()->assertJsonPath('data.affected_quantity', '2.000');

            $summary = $this->actingAs($inspector)->getJson($this->summaryUrl($inspection))
                ->assertSuccessful()->assertJsonCount(2, 'data.photos')->json('data');
            $this->assertSame('Two pieces have a cracked edge.', $summary['description']);
            $this->assertArrayNotHasKey('file_path', $summary['photos'][0]);
            $photoId = $summary['photos'][0]['id'];
            $this->actingAs($inspector)->get("/api/v1/quality/defect-summary-photos/{$photoId}/file")
                ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame(1, $inspection->fresh()->defectSummary()->count());
        }
    }

    public function test_positive_quantity_requires_description_and_at_least_one_photo(): void
    {
        $inspector = User::factory()->withRole('qc_inspector')->create(['is_active' => true]);
        [$inspection] = $this->incomingInspection($inspector);

        $this->actingAs($inspector)->put($this->summaryUrl($inspection), [
            'category' => 'surface_damage', 'severity' => 'minor', 'affected_quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('description');

        $this->actingAs($inspector)->put($this->summaryUrl($inspection), [
            'category' => 'surface_damage', 'severity' => 'minor', 'affected_quantity' => 1,
            'description' => 'One cracked piece.',
        ])->assertUnprocessable()->assertJsonValidationErrors('photos');
    }

    public function test_summary_rejects_invalid_or_excessive_photos_and_quantity_above_source(): void
    {
        $inspector = User::factory()->withRole('qc_inspector')->create(['is_active' => true]);
        [$inspection] = $this->incomingInspection($inspector);
        $payload = ['category' => 'surface_damage', 'severity' => 'minor', 'affected_quantity' => 21,
            'description' => 'Cracked finish on received material.'];

        $this->actingAs($inspector)->put($this->summaryUrl($inspection), $payload + [
            'photos' => [UploadedFile::fake()->image('too-large.jpg')->size(10_241)],
        ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');

        $this->actingAs($inspector)->put($this->summaryUrl($inspection), [
            ...$payload,
            'affected_quantity' => 21,
            'photos' => [UploadedFile::fake()->image('defect.jpg')],
        ])->assertUnprocessable()->assertJsonValidationErrors('affected_quantity');

        $this->actingAs($inspector)->put($this->summaryUrl($inspection), [
            ...$payload,
            'affected_quantity' => 1,
            'photos' => [UploadedFile::fake()->createWithContent('not-an-image.pdf', '%PDF-1.4')],
        ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');

        $this->actingAs($inspector)->put($this->summaryUrl($inspection), [
            ...$payload,
            'affected_quantity' => 1,
            'photos' => array_map(fn ($number) => UploadedFile::fake()->image("defect-{$number}.jpg"), range(1, 6)),
        ])->assertUnprocessable()->assertJsonValidationErrors('photos');
    }

    public function test_pcs_quantity_must_be_whole_even_when_sampling_basis_is_lot(): void
    {
        $inspector = User::factory()->withRole('qc_inspector')->create(['is_active' => true]);
        [$inspection] = $this->incomingInspection($inspector);
        $inspection->forceFill(['sampling_basis' => 'lot', 'source_uom_code' => 'PCS'])->saveQuietly();

        $this->actingAs($inspector)->put($this->summaryUrl($inspection), [
            'category' => 'surface_damage',
            'severity' => 'minor',
            'affected_quantity' => '1.500',
            'description' => 'The affected pieces have the same surface mark.',
            'photos' => [UploadedFile::fake()->image('defect.jpg')],
        ])->assertUnprocessable()->assertJsonValidationErrors('affected_quantity');
    }

    public function test_non_quality_manager_cannot_view_or_change_a_summary(): void
    {
        $inspector = User::factory()->withRole('qc_inspector')->create(['is_active' => true]);
        [$inspection] = $this->incomingInspection($inspector);
        $employee = User::factory()->withRole('employee')->create(['is_active' => true]);

        $this->actingAs($employee)->getJson($this->summaryUrl($inspection))->assertForbidden();
        $this->actingAs($employee)->put($this->summaryUrl($inspection), [])->assertForbidden();
    }

    public function test_inspector_can_clear_an_unsent_summary(): void
    {
        $inspector = User::factory()->withRole('qc_inspector')->create(['is_active' => true]);
        [$inspection] = $this->incomingInspection($inspector);
        $this->actingAs($inspector)->put($this->summaryUrl($inspection), [
            'category' => 'surface_damage', 'severity' => 'minor', 'affected_quantity' => 1,
            'description' => 'One cracked piece.', 'photos' => [UploadedFile::fake()->image('defect.png')],
        ])->assertSuccessful();

        $this->actingAs($inspector)->deleteJson($this->summaryUrl($inspection))->assertSuccessful();
        $this->assertDatabaseMissing('inspection_defect_summaries', ['inspection_id' => $inspection->id]);
    }

    public function test_incoming_quantity_result_requires_a_summary_and_accepts_one_shared_description(): void
    {
        $inspector = User::factory()->withRole('qc_inspector')->create(['is_active' => true]);
        [$inspection] = $this->incomingInspection($inspector);
        $this->addVisualRequirement($inspection);
        $resultUrl = $this->resultUrl($inspection);

        $this->actingAs($inspector)->postJson($resultUrl, [
            'expected_result_version' => 0,
            'quantity_result' => ['defective_quantity' => '2', 'descriptions' => []],
        ])->assertUnprocessable()->assertJsonPath('message', fn (string $message) => str_contains($message, 'summary'));

        $this->actingAs($inspector)->put($this->summaryUrl($inspection), [
            'category' => 'surface_damage', 'severity' => 'major', 'affected_quantity' => 2,
            'description' => 'Two pieces have a cracked edge.',
            'photos' => [UploadedFile::fake()->image('cracked-edge.jpg')],
        ])->assertSuccessful();

        $this->actingAs($inspector)->postJson($resultUrl, [
            'expected_result_version' => 0,
            'quantity_result' => ['defective_quantity' => '2', 'descriptions' => []],
        ])->assertSuccessful()->assertJsonPath('data.problem_description', 'Two pieces have a cracked edge.');
        $this->assertSame(0, $inspection->fresh()->defectivePieces()->count());
    }

    public function test_quantity_result_rejects_summary_count_mismatch_and_clears_summary_on_zero(): void
    {
        $inspector = User::factory()->withRole('qc_inspector')->create(['is_active' => true]);
        [$inspection] = $this->incomingInspection($inspector);
        $this->addVisualRequirement($inspection);
        $resultUrl = $this->resultUrl($inspection);

        $this->actingAs($inspector)->put($this->summaryUrl($inspection), [
            'category' => 'surface_damage', 'severity' => 'minor', 'affected_quantity' => 3,
            'description' => 'Three pieces have a cracked edge.',
            'photos' => [UploadedFile::fake()->image('cracked-edge.jpg')],
        ])->assertSuccessful();

        $this->actingAs($inspector)->postJson($resultUrl, [
            'expected_result_version' => 0,
            'quantity_result' => ['defective_quantity' => '2', 'descriptions' => []],
        ])->assertUnprocessable()->assertJsonPath('message', fn (string $message) => str_contains($message, 'summary'));
        $this->assertSame('draft', $inspection->fresh()->status->value);

        $this->actingAs($inspector)->postJson($resultUrl, [
            'expected_result_version' => 0,
            'quantity_result' => ['defective_quantity' => '0', 'descriptions' => []],
        ])->assertSuccessful()->assertJsonPath('data.recorded_result', 'passed');
        $this->assertDatabaseMissing('inspection_defect_summaries', ['inspection_id' => $inspection->id]);
    }

    public function test_legacy_incoming_quantity_result_remains_reviewable_without_a_summary(): void
    {
        $inspector = User::factory()->withRole('qc_inspector')->create(['is_active' => true]);
        [$inspection] = $this->incomingInspection($inspector);
        $this->addVisualRequirement($inspection);
        $inspection->forceFill([
            'status' => 'awaiting_review', 'recorded_result' => 'failed', 'proposed_result' => 'failed',
            'sample_defect_count' => 2, 'inspected_quantity' => 20, 'coverage_confirmed_at' => now(),
            'result_version' => 1, 'problem_description' => 'Two pieces have cracks.',
            'quantity_result' => ['defective_quantity' => '2.000', 'source_quantity' => '20.000',
                'unit' => $inspection->source_uom_code, 'descriptions' => ['Cracked', 'Cracked']],
        ])->save();
        $reviewer = User::factory()->withRole('qc_inspector')->create(['is_active' => true]);

        $this->actingAs($reviewer)->patchJson('/api/v1/quality/inspections/'.$inspection->hash_id.'/review', [
            'expected_result_version' => 1, 'decision' => 'failed', 'remarks' => 'Legacy evidence checked.',
        ])->assertSuccessful()->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseMissing('inspection_defect_summaries', ['inspection_id' => $inspection->id]);
    }

    private function incomingInspection(User $inspector): array
    {
        $product = Product::factory()->create();
        $item = Item::factory()->create();
        $location = WarehouseLocation::factory()->create();
        $po = PurchaseOrder::factory()->create();
        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id, 'item_id' => $item->id,
            'description' => $item->name, 'quantity' => '20.000', 'unit' => $item->unit_of_measure,
            'unit_price' => '10.00', 'total' => '200.00', 'quantity_received' => '20.000',
        ]);
        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-SUMMARY-'.strtoupper(substr(uniqid(), -6)),
            'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id ?? Vendor::factory()->create()->id,
            'received_date' => today()->toDateString(), 'received_by' => $inspector->id, 'status' => 'pending_qc',
        ]);
        $grnItem = GrnItem::create([
            'goods_receipt_note_id' => $grn->id, 'purchase_order_item_id' => $poItem->id,
            'item_id' => $item->id, 'location_id' => $location->id,
            'quantity_received' => '20.000', 'quantity_accepted' => '0.000', 'unit_cost' => '10.0000',
        ]);
        $inspection = Inspection::create([
            'inspection_number' => 'QC-SUMMARY-'.strtoupper(substr(uniqid(), -6)),
            'stage' => 'incoming', 'status' => 'draft', 'product_id' => $product->id,
            'item_id' => $item->id, 'entity_type' => 'grn', 'entity_id' => $grn->id,
            'grn_item_id' => $grnItem->id, 'batch_quantity' => 20, 'sample_size' => 5,
            'source_quantity' => '20.000', 'source_uom_code' => $item->unit_of_measure,
            'accept_count' => 0, 'reject_count' => 1, 'defect_count' => 0,
            'inspector_id' => $inspector->id,
        ]);

        return [$inspection, $grnItem];
    }

    private function outgoingInspection(User $inspector): Inspection
    {
        $product = Product::factory()->create(['unit_of_measure' => 'PCS']);
        $workOrder = WorkOrder::factory()->create(['product_id' => $product->id, 'created_by' => $inspector->id,
            'quantity_target' => 20, 'quantity_produced' => 20, 'quantity_good' => 20, 'status' => 'completed']);
        $output = WorkOrderOutput::create(['work_order_id' => $workOrder->id, 'recorded_by' => $inspector->id,
            'recorded_at' => now(), 'good_count' => 20, 'reject_count' => 0, 'batch_code' => 'SUMMARY-20']);

        return Inspection::create([
            'inspection_number' => 'QC-OUT-SUMMARY-'.strtoupper(substr(uniqid(), -6)),
            'stage' => 'outgoing', 'status' => 'draft', 'product_id' => $product->id,
            'entity_type' => 'work_order', 'entity_id' => $workOrder->id, 'work_order_output_id' => $output->id,
            'batch_quantity' => 20, 'sample_size' => 5, 'accept_count' => 0, 'reject_count' => 1,
            'defect_count' => 0, 'inspector_id' => $inspector->id,
        ]);
    }

    private function summaryUrl(Inspection $inspection): string
    {
        return "/api/v1/quality/inspections/{$inspection->hash_id}/defect-summary";
    }

    private function resultUrl(Inspection $inspection): string
    {
        return "/api/v1/quality/inspections/{$inspection->hash_id}/batch-result";
    }

    private function addVisualRequirement(Inspection $inspection): void
    {
        InspectionMeasurement::create([
            'inspection_id' => $inspection->id, 'sample_index' => 1,
            'parameter_name' => 'Visual inspection', 'parameter_type' => 'visual',
            'acceptance_criterion' => 'No cracks or surface damage', 'is_critical' => false,
        ]);
    }
}
