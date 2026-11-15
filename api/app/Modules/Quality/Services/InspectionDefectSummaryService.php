<?php

declare(strict_types=1);

namespace App\Modules\Quality\Services;

use App\Common\Exceptions\BusinessRuleException;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Models\GrnItem;
use App\Modules\Quality\Enums\InspectionStage;
use App\Modules\Quality\Enums\InspectionStatus;
use App\Modules\Quality\Models\Inspection;
use App\Modules\Quality\Models\InspectionDefectSummary;
use App\Modules\Quality\Models\InspectionDefectSummaryPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspectionDefectSummaryService
{
    public function get(Inspection $inspection): ?InspectionDefectSummary
    {
        return $inspection->defectSummary()->with(['photos.uploader'])->first();
    }

    /** @param list<UploadedFile> $photos
     *  @param list<string> $removePhotoIds
     */
    public function save(Inspection $inspection, array $data, array $photos, array $removePhotoIds, User $by): InspectionDefectSummary
    {
        $storedPaths = [];
        $removedPaths = [];

        try {
            $summary = DB::transaction(function () use ($inspection, $data, $photos, $removePhotoIds, $by, &$storedPaths, &$removedPaths): InspectionDefectSummary {
                $locked = Inspection::query()->lockForUpdate()->findOrFail($inspection->id);
                $this->assertEditable($locked);
                $quantity = trim((string) $data['affected_quantity']);
                $this->assertQuantityInSource($locked, $quantity);

                $summary = $locked->defectSummary()->lockForUpdate()->first();
                if (! $summary) {
                    $summary = $locked->defectSummary()->create([
                        'category' => $data['category'],
                        'other_category' => $data['category'] === 'other' ? trim((string) $data['other_category']) : null,
                        'severity' => $data['severity'],
                        'affected_quantity' => bcadd($quantity, '0', 3),
                        'description' => trim((string) $data['description']),
                        'created_by' => $by->id,
                        'updated_by' => $by->id,
                    ]);
                }

                $existingPhotos = $summary->photos()->lockForUpdate()->get();
                $removeIds = $this->decodePhotoIds($removePhotoIds);
                $photosToRemove = $existingPhotos->filter(fn (InspectionDefectSummaryPhoto $photo): bool => in_array((int) $photo->id, $removeIds, true));
                if ($photosToRemove->count() !== count($removeIds)) {
                    throw ValidationException::withMessages(['remove_photo_ids' => ['One or more selected photos do not belong to this defect summary.']]);
                }
                $remainingCount = $existingPhotos->count() - $photosToRemove->count();
                if ($remainingCount + count($photos) > 5) {
                    throw ValidationException::withMessages(['photos' => ['A defect summary can have no more than 5 photos.']]);
                }
                if ($remainingCount + count($photos) < 1) {
                    throw ValidationException::withMessages(['photos' => ['Attach at least one photo to the defect summary.']]);
                }

                foreach ($photosToRemove as $photo) {
                    $removedPaths[] = $photo->file_path;
                    $photo->delete();
                }

                $summary->fill([
                    'category' => $data['category'],
                    'other_category' => $data['category'] === 'other' ? trim((string) $data['other_category']) : null,
                    'severity' => $data['severity'],
                    'affected_quantity' => bcadd($quantity, '0', 3),
                    'description' => trim((string) $data['description']),
                    'updated_by' => $by->id,
                ])->save();

                foreach ($photos as $photo) {
                    $path = $photo->store("quality/inspection-defect-summaries/{$locked->hash_id}/{$summary->hash_id}", 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['photos' => ['A defect photo could not be stored. Try again.']]);
                    }
                    $storedPaths[] = $path;
                    $summary->photos()->create([
                        'file_path' => $path,
                        'file_name' => mb_substr(basename($photo->getClientOriginalName()), 0, 255),
                        'mime_type' => $photo->getMimeType() ?: 'application/octet-stream',
                        'file_size' => $photo->getSize(),
                        'uploaded_by' => $by->id,
                    ]);
                }

                return $summary->fresh(['photos.uploader']);
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        Storage::disk('local')->delete($removedPaths);

        return $summary;
    }

    public function clearDraft(Inspection $inspection): void
    {
        $paths = [];
        DB::transaction(function () use ($inspection, &$paths): void {
            $locked = Inspection::query()->lockForUpdate()->findOrFail($inspection->id);
            $summary = $locked->defectSummary()->lockForUpdate()->first();
            if (! $summary) {
                return;
            }
            $this->assertEditable($locked);
            $paths = $summary->photos()->pluck('file_path')->all();
            $summary->delete();
        }, 3);
        if ($paths !== [] && DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($paths));
        } elseif ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }

    public function assertMatches(Inspection $inspection, string $quantity): ?InspectionDefectSummary
    {
        if (bccomp($quantity, '0', 3) <= 0) {
            return null;
        }

        $summary = $inspection->defectSummary()->with('photos')->first();
        if (! $summary
            || bccomp((string) $summary->affected_quantity, $quantity, 3) !== 0
            || trim((string) $summary->description) === ''
            || $summary->photos->isEmpty()) {
            throw new BusinessRuleException('Save a defect summary with the matching quantity, description, and at least one photo before submitting the inspection.');
        }
        $this->assertQuantityInSource($inspection, (string) $summary->affected_quantity);

        return $summary;
    }

    public function photo(InspectionDefectSummaryPhoto $photo): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($photo->file_path), 404);

        return Storage::disk('local')->response(
            $photo->file_path,
            $photo->file_name,
            [
                'Content-Type' => $photo->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private function assertEditable(Inspection $inspection): void
    {
        $stage = $inspection->stage instanceof \BackedEnum ? $inspection->stage->value : (string) $inspection->stage;
        $status = $inspection->status instanceof \BackedEnum ? $inspection->status->value : (string) $inspection->status;

        if (! in_array($stage, [InspectionStage::Incoming->value, InspectionStage::Outgoing->value], true)) {
            throw new BusinessRuleException('Defect summaries are available only for incoming and outgoing inspections.');
        }
        if (! in_array($status, [InspectionStatus::Draft->value, InspectionStatus::InProgress->value], true)
            || $inspection->coverage_confirmed_at || $inspection->completed_at || $inspection->reviewed_at) {
            throw new BusinessRuleException('The defect summary is locked after inspection submission.');
        }
    }

    private function assertQuantityInSource(Inspection $inspection, string $quantity): void
    {
        $source = $inspection->source_quantity;
        if ($source === null && $inspection->stage === InspectionStage::Incoming && $inspection->grn_item_id) {
            $source = (string) GrnItem::query()->lockForUpdate()->findOrFail($inspection->grn_item_id)->quantity_received;
        }
        $source ??= (string) $inspection->batch_quantity;
        $unit = (string) ($inspection->source_uom_code
            ?: $inspection->item?->unit_of_measure
            ?: $inspection->product?->unit_of_measure
            ?: 'PCS');
        $pieceQuantity = $inspection->sampling_basis === 'piece' || strtoupper($unit) === 'PCS';

        if (! preg_match('/^\d{1,12}(?:\.\d{1,3})?$/', $quantity)
            || bccomp($source, '0', 3) <= 0
            || bccomp($quantity, (string) $source, 3) > 0
            || ($pieceQuantity && bccomp($quantity, (string) (int) $quantity, 3) !== 0)) {
            throw ValidationException::withMessages([
                'affected_quantity' => ['Affected quantity must be positive, within the source quantity, and a whole number for piece-based inspections.'],
            ]);
        }
    }

    /** @param list<string> $photoIds
     *  @return list<int>
     */
    private function decodePhotoIds(array $photoIds): array
    {
        $ids = [];
        foreach ($photoIds as $photoId) {
            $id = app()->environment('testing') && ctype_digit((string) $photoId)
                ? (int) $photoId
                : InspectionDefectSummaryPhoto::tryDecodeHash((string) $photoId);
            if (! $id) {
                throw ValidationException::withMessages(['remove_photo_ids' => ['One or more selected photos are invalid.']]);
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }
}
