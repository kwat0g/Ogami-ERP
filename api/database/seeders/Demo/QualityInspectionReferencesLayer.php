<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Exceptions\BusinessRuleException;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Models\Product;
use App\Modules\Quality\Enums\NcrSeverity;
use App\Modules\Quality\Enums\NcrSource;
use App\Modules\Quality\Models\InspectionSpec;
use App\Modules\Quality\Models\InspectionSpecItem;
use App\Modules\Quality\Models\NcrTemplate;
use App\Modules\Quality\Services\InspectionSpecService;
use App\Modules\Quality\Services\NcrTemplateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/** Seeds synthetic product inspection specifications and reusable NCR templates. */
final class QualityInspectionReferencesLayer extends DemoSeeder
{
    private const SPEC_NOTES = 'Baseline inspection limits derived from product master dimensions; verify against the released customer drawing before production use.';

    /** @var list<string> */
    private const PART_NUMBERS = [
        'WB-001', 'PC-001', 'RC-001', 'BB-001', 'BU-001', 'CH-001', 'FC-001', 'MH-001',
        'HS-001', 'AD-001', 'TC-001', 'HR-001', 'WB-002', 'PC-002', 'RC-002', 'BC-001',
    ];

    /**
     * @var list<array{name: string, source: string, severity: string, part_number: ?string, defect_description: string, active: bool}>
     */
    private const NCR_TEMPLATES = [
        ['name' => 'Incoming resin discoloration', 'source' => 'inspection_fail', 'severity' => 'high', 'part_number' => null, 'defect_description' => 'Incoming resin color is outside the approved material reference range.', 'active' => true],
        ['name' => 'Insert plating contamination', 'source' => 'inspection_fail', 'severity' => 'medium', 'part_number' => null, 'defect_description' => 'Metal insert plating shows visible contamination or corrosion.', 'active' => true],
        ['name' => 'Parting-line flash', 'source' => 'inspection_fail', 'severity' => 'medium', 'part_number' => 'WB-001', 'defect_description' => 'Flash is present at the parting line of the wiper bushing.', 'active' => true],
        ['name' => 'Critical enclosure crack', 'source' => 'inspection_fail', 'severity' => 'critical', 'part_number' => 'BC-001', 'defect_description' => 'A crack is visible at a battery-cover retention feature.', 'active' => true],
        ['name' => 'Connector latch retention', 'source' => 'inspection_fail', 'severity' => 'high', 'part_number' => 'CH-001', 'defect_description' => 'Connector latch retention is below the approved acceptance limit.', 'active' => true],
        ['name' => 'Customer appearance complaint', 'source' => 'customer_complaint', 'severity' => 'low', 'part_number' => 'MH-001', 'defect_description' => 'Customer reports a visible surface blemish on the mirror housing.', 'active' => true],
        ['name' => 'Customer connector fit concern', 'source' => 'customer_complaint', 'severity' => 'critical', 'part_number' => 'CH-001', 'defect_description' => 'Customer reports a connector fit concern requiring containment review.', 'active' => true],
        ['name' => 'Obsolete revision or label mismatch', 'source' => 'customer_complaint', 'severity' => 'low', 'part_number' => null, 'defect_description' => 'Packaging carries an obsolete part revision or an incorrect label.', 'active' => false],
        ['name' => 'Short shot at terminal wall', 'source' => 'inspection_fail', 'severity' => 'medium', 'part_number' => 'PC-001', 'defect_description' => 'Terminal wall fill is incomplete on the connector cover.', 'active' => true],
        ['name' => 'Customer-reported fit variation', 'source' => 'customer_complaint', 'severity' => 'high', 'part_number' => 'FC-001', 'defect_description' => 'Customer reports variable snap-fit engagement on the fuse-box cover.', 'active' => true],
        ['name' => 'Supplier material contamination', 'source' => 'inspection_fail', 'severity' => 'critical', 'part_number' => null, 'defect_description' => 'Incoming lot contains foreign material and requires supplier containment.', 'active' => true],
        ['name' => 'Customer packaging quantity concern', 'source' => 'customer_complaint', 'severity' => 'low', 'part_number' => 'TC-001', 'defect_description' => 'Customer reports a mismatch between labeled and counted clip quantity.', 'active' => true],
        ['name' => 'Dimensional check outside tolerance', 'source' => 'inspection_fail', 'severity' => 'high', 'part_number' => 'BC-001', 'defect_description' => 'A measured enclosure dimension is outside its inspection limit.', 'active' => true],
        ['name' => 'Customer field crack report', 'source' => 'customer_complaint', 'severity' => 'critical', 'part_number' => 'FC-001', 'defect_description' => 'Customer reports a field crack requiring risk and containment assessment.', 'active' => true],
        ['name' => 'Mold deposit burn mark', 'source' => 'inspection_fail', 'severity' => 'medium', 'part_number' => 'AD-001', 'defect_description' => 'Burn mark is observed near the air-duct connector gate.', 'active' => true],
        ['name' => 'Repeat customer appearance report', 'source' => 'customer_complaint', 'severity' => 'low', 'part_number' => 'RC-001', 'defect_description' => 'Customer reports a recurring appearance variation on a relay cover.', 'active' => true],
    ];

    public function __construct(
        private readonly InspectionSpecService $specs,
        private readonly NcrTemplateService $templates,
    ) {}

    public function key(): string
    {
        return 'quality-inspection-specs';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Quality'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['finished-product-catalog', 'workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $context->at($context->options->asOf, function () use ($context): void {
            $products = Product::query()
                ->whereIn('part_number', self::PART_NUMBERS)
                ->orderBy('part_number')
                ->get();
            $productsByPartNumber = $products->keyBy('part_number');

            foreach (self::PART_NUMBERS as $partNumber) {
                if (! $productsByPartNumber->has($partNumber)) {
                    throw new RuntimeException("Quality inspection reference seeding requires demo product {$partNumber}; run the finished-product catalog first.");
                }
            }

            $actor = $this->qualityActor();
            $context->asUser($actor, function () use ($context, $productsByPartNumber, $actor): void {
                foreach (self::PART_NUMBERS as $partNumber) {
                    /** @var Product $product */
                    $product = $productsByPartNumber->get($partNumber);
                    $items = $this->inspectionItems($product);
                    $spec = InspectionSpec::withTrashed()
                        ->where('product_id', $product->id)
                        ->with(['items', 'currentRevision'])
                        ->first();

                    if ($spec === null || ! $this->matchesInspectionSpec($spec, $items)) {
                        $spec = $this->specs->upsertForProduct(
                            (int) $product->id,
                            $items,
                            (int) $actor->id,
                            self::SPEC_NOTES,
                            'measurement',
                        );
                    }

                    $context->records()->remember('quality.inspection-spec.'.$partNumber, $spec);
                }

                $this->seedNcrTemplates($context, $productsByPartNumber, $actor);
            });
        });
    }

    /** @return list<array<string, mixed>> */
    private function inspectionItems(Product $product): array
    {
        $specification = $product->specifications;
        $dimensions = is_array($specification) && is_array($specification['dimensions'] ?? null)
            ? $specification['dimensions']
            : [];
        $unit = $dimensions['unit'] ?? null;
        $tolerances = is_array($specification) ? (string) ($specification['tolerances'] ?? '') : '';

        if (! is_string($unit) || trim($unit) === ''
            || ! preg_match('/±\s*(\d+(?:\.\d+)?)/u', $tolerances, $matches)) {
            throw new BusinessRuleException("Demo product {$product->part_number} is missing source dimensions, units, or the illustrative tolerance used for its inspection reference.");
        }

        $tolerance = (float) $matches[1];
        if ($tolerance <= 0) {
            throw new BusinessRuleException("Demo product {$product->part_number} has a non-positive illustrative inspection tolerance.");
        }

        $items = [];
        foreach ([
            'length' => 'Overall length',
            'width' => 'Overall width',
            'height' => 'Overall height',
        ] as $key => $label) {
            $nominal = $dimensions[$key] ?? null;
            if (! is_numeric($nominal)) {
                throw new BusinessRuleException("Demo product {$product->part_number} is missing its synthetic {$key} dimension.");
            }

            $value = (float) $nominal;
            $minimum = $value - $tolerance;
            $maximum = $value + $tolerance;
            $items[] = [
                'parameter_name' => $label,
                'parameter_type' => 'dimensional',
                'unit_of_measure' => $unit,
                'nominal_value' => $this->decimal($value),
                'tolerance_min' => $this->decimal($minimum),
                'tolerance_max' => $this->decimal($maximum),
                'acceptance_criterion' => 'Confirm the released customer drawing before production use.',
                'is_critical' => $key === 'length',
                'sort_order' => count($items) * 10,
                'notes' => 'Dimensional reference derived from product master data; not an approved customer specification.',
            ];
        }

        $finish = is_array($specification) ? trim((string) ($specification['finish'] ?? '')) : '';
        $items[] = [
            'parameter_name' => 'Appearance and surface condition',
            'parameter_type' => 'visual',
            'acceptance_criterion' => 'Check for flash, cracks, burns, short shots, and visible contamination. Finish reference: '.($finish !== '' ? $finish : 'as-molded appearance').'; verify against the current customer standard.',
            'is_critical' => false,
            'sort_order' => 40,
            'notes' => 'Validate the appearance limit with Quality and the customer.',
        ];
        $items[] = [
            'parameter_name' => 'Fit and retention function',
            'parameter_type' => 'functional',
            'acceptance_criterion' => 'Verify engagement using a fixture and limit approved by Engineering before production use.',
            'is_critical' => true,
            'sort_order' => 50,
            'notes' => 'Use only the current customer-approved fixture and acceptance limit.',
        ];

        return $items;
    }

    /** @param list<array<string, mixed>> $expectedItems */
    private function matchesInspectionSpec(InspectionSpec $spec, array $expectedItems): bool
    {
        $revision = $spec->currentRevision;
        if ($spec->trashed() || ! $spec->is_active || $spec->notes !== self::SPEC_NOTES
            || $revision === null || $revision->capture_method !== 'measurement'
            || $revision->notes !== self::SPEC_NOTES) {
            return false;
        }

        $actualItems = $spec->items->map(fn (InspectionSpecItem $item): array => [
            'parameter_name' => $item->parameter_name,
            'parameter_type' => $item->parameter_type?->value,
            'unit_of_measure' => $item->unit_of_measure,
            'nominal_value' => $this->decimalOrNull($item->nominal_value),
            'tolerance_min' => $this->decimalOrNull($item->tolerance_min),
            'tolerance_max' => $this->decimalOrNull($item->tolerance_max),
            'acceptance_criterion' => $item->acceptance_criterion,
            'is_critical' => (bool) $item->is_critical,
            'sort_order' => (int) $item->sort_order,
            'notes' => $item->notes,
        ])->values()->all();
        $expected = array_map(fn (array $item): array => [
            'parameter_name' => $item['parameter_name'],
            'parameter_type' => $item['parameter_type'],
            'unit_of_measure' => $item['unit_of_measure'] ?? null,
            'nominal_value' => $this->decimalOrNull($item['nominal_value'] ?? null),
            'tolerance_min' => $this->decimalOrNull($item['tolerance_min'] ?? null),
            'tolerance_max' => $this->decimalOrNull($item['tolerance_max'] ?? null),
            'acceptance_criterion' => $item['acceptance_criterion'],
            'is_critical' => (bool) $item['is_critical'],
            'sort_order' => (int) $item['sort_order'],
            'notes' => $item['notes'] ?? null,
        ], $expectedItems);

        return $actualItems === $expected;
    }

    /** @param Collection<string, Product> $productsByPartNumber */
    private function seedNcrTemplates(DemoSeedContext $context, Collection $productsByPartNumber, User $actor): void
    {
        $count = match ($context->options->scale) {
            'small' => 8,
            'demo' => 12,
            'full' => 16,
        };

        foreach (array_slice(self::NCR_TEMPLATES, 0, $count) as $definition) {
            if (! in_array($definition['source'], NcrSource::values(), true)
                || ! in_array($definition['severity'], NcrSeverity::values(), true)) {
                throw new BusinessRuleException('Demo NCR template contains a source or severity outside the current application enums.');
            }

            $productId = null;
            if ($definition['part_number'] !== null) {
                $product = $productsByPartNumber->get($definition['part_number']);
                if (! $product instanceof Product) {
                    throw new RuntimeException("Demo NCR template requires product {$definition['part_number']}.");
                }
                $productId = (int) $product->id;
            }

            $data = [
                'name' => $definition['name'],
                'source' => $definition['source'],
                'severity' => $definition['severity'],
                'product_id' => $productId,
                'defect_description' => $definition['defect_description'],
                'notes' => 'Review applicability against current customer, product, and supplier requirements before use.',
                'is_active' => $definition['active'],
            ];
            $template = NcrTemplate::query()->where('name', $definition['name'])->first();

            if ($template === null) {
                $template = $this->templates->create($data, $actor);
                if (! $definition['active']) {
                    $template = $this->templates->deactivate($template);
                }
            } elseif (! $this->matchesTemplate($template, $data)) {
                $template = $this->templates->update($template, $data);
            }

            $context->records()->remember('quality.ncr-template.'.Str::slug($definition['name']), $template);
        }
    }

    /** @param array<string, mixed> $expected */
    private function matchesTemplate(NcrTemplate $template, array $expected): bool
    {
        return $template->source === $expected['source']
            && $template->severity === $expected['severity']
            && (int) $template->product_id === (int) ($expected['product_id'] ?? 0)
            && $template->defect_description === $expected['defect_description']
            && $template->notes === $expected['notes']
            && (bool) $template->is_active === (bool) $expected['is_active'];
    }

    private function decimal(float $value): string
    {
        return number_format($value, 4, '.', '');
    }

    private function decimalOrNull(mixed $value): ?string
    {
        return $value === null ? null : $this->decimal((float) $value);
    }

    private function qualityActor(): User
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', 'qc_inspector'))
            ->orderBy('id')
            ->first()
            ?? throw new RuntimeException('Quality inspection reference seeding requires an active qc_inspector actor.');
    }
}
