<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Controllers;

use App\Modules\Payroll\Enums\ContributionAgency;
use App\Modules\Payroll\Models\GovernmentContributionTable;
use App\Modules\Payroll\Models\GovernmentScheduleVersion;
use App\Modules\Payroll\Requests\ImportGovTableRequest;
use App\Modules\Payroll\Requests\PublishGovScheduleVersionRequest;
use App\Modules\Payroll\Requests\UpdateGovTableBracketRequest;
use App\Modules\Payroll\Resources\GovernmentScheduleVersionResource;
use App\Modules\Payroll\Resources\GovernmentTableResource;
use App\Modules\Payroll\Services\GovernmentContributionTableImportService;
use App\Modules\Payroll\Services\GovernmentContributionTableService;
use App\Modules\Payroll\Services\GovernmentScheduleVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class GovernmentTableController
{
    public function __construct(private readonly GovernmentContributionTableService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'agency' => ['required', Rule::in(ContributionAgency::values())],
        ]);

        $rows = $this->service->list($request->string('agency')->value());

        return GovernmentTableResource::collection($rows);
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'agencies' => array_map(static fn (ContributionAgency $agency): array => [
                'value' => $agency->value,
                'label' => $agency->label(),
            ], ContributionAgency::cases()),
        ]]);
    }

    public function versions(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'agency' => ['required', Rule::in(ContributionAgency::values())],
        ]);

        $versions = GovernmentScheduleVersion::query()
            ->with(['importer:id,name', 'publisher:id,name'])
            ->where('agency', $request->string('agency')->value())
            ->orderByDesc('effective_date')
            ->orderByDesc('version_number')
            ->get();

        return GovernmentScheduleVersionResource::collection($versions);
    }

    public function publish(
        PublishGovScheduleVersionRequest $request,
        GovernmentScheduleVersion $version,
        GovernmentScheduleVersionService $publisher,
    ): JsonResponse {
        $published = $publisher->publish(
            $version,
            $request->user(),
            (bool) $request->validated('confirm_completeness'),
        );

        return response()->json([
            'data' => (new GovernmentScheduleVersionResource($published->load(['importer:id,name', 'publisher:id,name'])))->resolve(),
        ]);
    }

    public function update(UpdateGovTableBracketRequest $request, GovernmentContributionTable $govTable): GovernmentTableResource
    {
        return new GovernmentTableResource($this->service->update($govTable, $request->validated()));
    }

    public function deactivate(GovernmentContributionTable $govTable): GovernmentTableResource
    {
        return new GovernmentTableResource($this->service->deactivate($govTable));
    }

    public function activate(GovernmentContributionTable $govTable): GovernmentTableResource
    {
        return new GovernmentTableResource($this->service->activate($govTable));
    }

    public function destroy(GovernmentContributionTable $govTable): JsonResponse
    {
        $this->service->delete($govTable);

        return response()->json(null, 204);
    }

    public function restore(GovernmentContributionTable $govTable): JsonResponse
    {
        $this->service->restore($govTable);

        return response()->json(['message' => 'Government contribution table restored.']);
    }

    /**
     * T1.8 — Upload + import a contribution table CSV.
     */
    public function import(
        ImportGovTableRequest $request,
        string $agency,
        GovernmentContributionTableImportService $importer,
    ): JsonResponse {
        $enum = ContributionAgency::tryFrom($agency);
        if (! $enum) {
            return response()->json(['message' => 'Unknown agency.'], 422);
        }

        $file = $request->file('csv');
        $result = $importer->importFromPath(
            $enum,
            $file->getRealPath(),
            $request->validated('source_title'),
            $request->validated('source_url'),
            $request->user()?->id,
            $file->getClientOriginalName(),
        );

        if ($result['version_id'] !== null) {
            $version = GovernmentScheduleVersion::query()->findOrFail($result['version_id']);
            $result['version'] = (new GovernmentScheduleVersionResource($version->load(['importer:id,name', 'publisher:id,name'])))->resolve();
            $result['version_id'] = $version->hash_id;
        }

        return response()->json(['data' => $result]);
    }
}
