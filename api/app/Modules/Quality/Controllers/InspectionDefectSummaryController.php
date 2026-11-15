<?php

declare(strict_types=1);

namespace App\Modules\Quality\Controllers;

use App\Modules\Quality\Models\Inspection;
use App\Modules\Quality\Models\InspectionDefectSummaryPhoto;
use App\Modules\Quality\Requests\SaveInspectionDefectSummaryRequest;
use App\Modules\Quality\Resources\InspectionDefectSummaryResource;
use App\Modules\Quality\Services\InspectionDefectSummaryService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspectionDefectSummaryController
{
    public function __construct(private readonly InspectionDefectSummaryService $service) {}

    public function show(Inspection $inspection): JsonResponse
    {
        $summary = $this->service->get($inspection);

        return response()->json([
            'data' => $summary ? (new InspectionDefectSummaryResource($summary))->resolve(request()) : null,
        ]);
    }

    public function save(SaveInspectionDefectSummaryRequest $request, Inspection $inspection): JsonResponse
    {
        $data = $request->validated();
        $summary = $this->service->save(
            $inspection,
            $data,
            $request->file('photos', []),
            $data['remove_photo_ids'] ?? [],
            $request->user(),
        );

        return (new InspectionDefectSummaryResource($summary))->response();
    }

    public function destroy(Inspection $inspection): JsonResponse
    {
        $this->service->clearDraft($inspection);

        return response()->json(['data' => null]);
    }

    public function photo(InspectionDefectSummaryPhoto $photo): StreamedResponse
    {
        return $this->service->photo($photo);
    }
}
