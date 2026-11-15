<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Exceptions\BusinessRuleException;
use App\Common\Services\SettingsService;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Quality\Models\ItemQualityPlan;
use App\Modules\Quality\Services\ItemQualityPlanService;
use RuntimeException;

/** Publishes configured baseline incoming-inspection plans for eligible items. */
final class QualityPlanLayer extends DemoSeeder
{
    private const NOTES = 'Baseline generated from the application quality-rollout settings. QC should confirm supplier certificates and released item specifications before production use.';

    public function __construct(
        private readonly ItemQualityPlanService $plans,
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'quality-plans';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Quality'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['inventory-catalog', 'workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->toDateString();

        $context->at($context->options->asOf, function () use ($context, $asOf): void {
            $eligibleTypes = $this->eligibleItemTypes();
            $sampleSize = $this->settings->requiredInt('quality.rollout.fixed_sample_size', 1, 1000);
            $actor = $this->automationActor();
            $eligibleCount = 0;
            $createdCount = 0;
            $existingCount = 0;

            Item::query()
                ->active()
                ->whereIn('item_type', $eligibleTypes)
                ->with('category:id,name')
                ->orderBy('id')
                ->chunkById(100, function ($items) use ($context, $asOf, $sampleSize, $actor, &$eligibleCount, &$createdCount, &$existingCount): void {
                    foreach ($items as $item) {
                        $eligibleCount++;

                        $current = ItemQualityPlan::query()
                            ->where('item_id', $item->id)
                            ->whereNull('vendor_id')
                            ->effective($asOf)
                            ->orderByDesc('version')
                            ->first();

                        if ($current !== null) {
                            $context->records()->remember('quality.plan.'.$item->code, $current);
                            $existingCount++;

                            continue;
                        }

                        $future = ItemQualityPlan::query()
                            ->where('item_id', $item->id)
                            ->whereNull('vendor_id')
                            ->where('is_active', true)
                            ->whereDate('effective_from', '>', $asOf)
                            ->orderByDesc('version')
                            ->first();

                        if ($future !== null) {
                            throw new BusinessRuleException("Cannot backdate demo quality coverage for {$item->code}: an active default revision starts after {$asOf}.");
                        }

                        $template = $item->category?->name === 'Resins' ? 'resin' : 'general';
                        $parameters = $this->settings->get("quality.rollout.template.{$template}", []);
                        if (! is_array($parameters) || $parameters === []) {
                            throw new BusinessRuleException("Demo quality-plan seeding requires a valid quality.rollout.template.{$template} setting.");
                        }

                        $plan = $context->asUser($actor, fn (): ItemQualityPlan => $this->plans->createRevision($item, [
                            'sampling_method' => 'fixed',
                            'fixed_sample_size' => $sampleSize,
                            'parameters' => $parameters,
                            'effective_from' => $asOf,
                            'notes' => self::NOTES,
                        ], $actor));

                        $context->records()->remember('quality.plan.'.$item->code, $plan);
                        $createdCount++;
                    }
                });

            if ($eligibleCount === 0) {
                throw new RuntimeException('Demo quality-plan seeding found no active items matching quality.rollout.eligible_item_types.');
            }

            $this->command?->info("Incoming quality plans: {$createdCount} created; {$existingCount} already covered across {$eligibleCount} eligible items.");
        });
    }

    /** @return list<string> */
    private function eligibleItemTypes(): array
    {
        $configured = $this->settings->get('quality.rollout.eligible_item_types', null);
        if (! is_array($configured) || $configured === []) {
            throw new BusinessRuleException('Demo quality-plan seeding requires a non-empty quality.rollout.eligible_item_types setting.');
        }

        $validTypes = ItemType::values();
        $types = [];
        foreach ($configured as $type) {
            if (! is_string($type) || ! in_array($type, $validTypes, true)) {
                throw new BusinessRuleException('Demo quality-plan seeding found an unsupported item type in quality.rollout.eligible_item_types.');
            }
            $types[$type] = $type;
        }

        return array_values($types);
    }

    private function automationActor(): User
    {
        $roles = $this->settings->get('system.automation.actor_roles', []);
        $roles = is_array($roles)
            ? array_values(array_filter($roles, static fn ($role): bool => is_string($role) && trim($role) !== ''))
            : [];

        if ($roles === []) {
            throw new BusinessRuleException('Demo quality-plan seeding requires configured system.automation.actor_roles.');
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $roles))
            ->orderBy('id')
            ->first()
            ?? throw new BusinessRuleException('Demo quality-plan seeding requires an active configured automation actor.');
    }
}
