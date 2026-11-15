<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Models\ApprovalDelegation;
use App\Common\Support\Money;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Purchasing\Enums\SupplierListingStatus;
use App\Modules\Purchasing\Models\ApprovedSupplier;
use App\Modules\Purchasing\Models\SupplierItemListing;
use App\Modules\Purchasing\Services\ApprovedSupplierService;
use App\Modules\Purchasing\Services\SupplierListingService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

/**
 * Seeds supplier offer submissions and reviews through the current purchasing
 * service, retaining its qualification, audit, notification and outbox paths.
 *
 * Quotes are illustrative and calculated from the demo item's standard cost;
 * they are not vendor quotations or purchasing commitments.
 */
final class SupplierListingLayer extends DemoSeeder
{
    /** @var array<string, list<string>> */
    private const CATEGORY_SOURCES = [
        'Resins' => [
            'Inabata Philippines Inc.',
        ],
        'Colorants' => [
            'Inabata Philippines Inc.',
        ],
        'Process Consumables' => [
            'Inabata Philippines Inc.',
        ],
        'Metal Inserts' => [
            'Philippine Nakamura',
            'Aikawa Philippines Inc.',
        ],
        'Packaging' => [
            'Aikawa Philippines Inc.',
            'Junlec Printing Supply',
            'Tanigawa Seisakusho',
            'Dyna Pack & Malinta',
        ],
    ];

    /** @var array<string, array{code:string,price_factor:string,lead_offset:int}> */
    private const VENDOR_QUOTES = [
        'Inabata Philippines Inc.' => ['code' => 'INABATA', 'price_factor' => '1.03', 'lead_offset' => -1],
        'Philippine Nakamura' => ['code' => 'NAKAMURA', 'price_factor' => '1.04', 'lead_offset' => 2],
        'Aikawa Philippines Inc.' => ['code' => 'AIKAWA', 'price_factor' => '1.02', 'lead_offset' => 0],
        'Junlec Printing Supply' => ['code' => 'JUNLEC', 'price_factor' => '1.01', 'lead_offset' => -1],
        'Tanigawa Seisakusho' => ['code' => 'TANIGAWA', 'price_factor' => '1.05', 'lead_offset' => 2],
        'Dyna Pack & Malinta' => ['code' => 'DYNAPACK', 'price_factor' => '1.04', 'lead_offset' => 1],
    ];

    public function __construct(
        private readonly SupplierListingService $listings,
        private readonly ApprovedSupplierService $approvedSuppliers,
    ) {}

    public function key(): string
    {
        return 'supplier-listings';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Purchasing', 'B2B', 'Dashboard', 'Auth'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['business-partners', 'workforce-identities'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $reviewerId = $context->records()->id('workforce.user.account.agnas.shiernalhen@gmail.com');
        $reviewer = User::query()->find((int) $reviewerId);
        if (! $reviewer instanceof User
            || ! ApprovalDelegation::canActForPermission($reviewer, 'purchasing.supplier_listings.review')) {
            throw new RuntimeException('A valid Purchasing Officer reviewer is missing; seed workforce-identities and RolePermissionSeeder first.');
        }

        $items = Item::query()
            ->with('category')
            ->where('is_active', true)
            ->whereIn('item_type', [
                ItemType::RawMaterial->value,
                ItemType::Packaging->value,
            ])
            ->whereHas('category', fn (Builder $query) => $query->whereIn('name', array_keys(self::CATEGORY_SOURCES)))
            ->orderBy('code')
            ->get();
        if ($items->isEmpty()) {
            throw new RuntimeException('The active raw-material, packaging and spare-parts catalog is empty.');
        }

        $sourcePairs = $this->sourcePairs($context, $items);
        $itemsByCode = $items->keyBy('code');
        $primaryVendorsByCategory = [];
        foreach ($sourcePairs as $pair) {
            $category = (string) $pair['item']->category?->name;
            $primaryVendorsByCategory[$category] ??= $pair['vendor'];
        }

        $primaryResinVendor = Vendor::query()->where('name', 'Inabata Philippines Inc.')->first();
        $historyItem = $itemsByCode->get('RM-001');
        $rejectionItem = $itemsByCode->get('RM-003');
        $pendingItem = $itemsByCode->get('RM-005');
        if (! $primaryResinVendor instanceof Vendor
            || ! $historyItem instanceof Item
            || ! $rejectionItem instanceof Item
            || ! $pendingItem instanceof Item) {
            throw new RuntimeException('Supplier listing scenarios require the primary resin vendor and RM-001/RM-003/RM-005 items.');
        }

        $this->ensureApprovedListing(
            $context,
            $primaryResinVendor,
            $historyItem,
            $this->listingCode($primaryResinVendor, $historyItem, 'R1'),
            $this->previousBusinessDay($asOf->subDays(180)),
            $reviewer,
            '1.03',
        );

        foreach ($sourcePairs as $index => $pair) {
            /** @var Vendor $vendor */
            $vendor = $pair['vendor'];
            /** @var Item $item */
            $item = $pair['item'];
            $revision = $vendor->name === $primaryResinVendor->name && $item->code === 'RM-001' ? 'R2' : 'R1';
            $submittedAt = $this->previousBusinessDay($asOf->subDays(14 + ($index % 7)));
            $this->ensureApprovedListing(
                $context,
                $vendor,
                $item,
                $this->listingCode($vendor, $item, $revision),
                $submittedAt,
                $reviewer,
                $this->quoteFactor($vendor),
            );
        }

        $this->assignPreferredSuppliers($context, $sourcePairs, $reviewer, $asOf);

        $this->ensureRejectedListing(
            $context,
            $primaryResinVendor,
            $rejectionItem,
            $this->listingCode($primaryResinVendor, $rejectionItem, 'REJECTED'),
            $this->previousBusinessDay($asOf->subDays(90)),
            $reviewer,
        );

        $pendingVendor = $primaryVendorsByCategory['Resins'] ?? null;
        if (! $pendingVendor instanceof Vendor) {
            throw new RuntimeException('No active resin supplier is available for the live pending listing.');
        }
        $pending = $this->ensurePendingListing(
            $context,
            $pendingVendor,
            $pendingItem,
            $this->listingCode($pendingVendor, $pendingItem, 'LIVE'),
            $this->previousBusinessDay($asOf),
        );

        $this->verifyOwnedListings($sourcePairs, $primaryResinVendor, $historyItem, $rejectionItem, $pending);
    }

    /**
     * @param  Collection<int, Item>  $items
     * @return list<array{vendor:Vendor,item:Item}>
     */
    private function sourcePairs(DemoSeedContext $context, Collection $items): array
    {
        $candidateNames = array_values(array_unique(array_merge(...array_values(self::CATEGORY_SOURCES))));
        $vendors = Vendor::query()
            ->where('is_active', true)
            ->whereIn('name', $candidateNames)
            ->orderBy('id')
            ->get()
            ->keyBy('name');
        $limit = match ($context->options->scale) {
            'small' => 1,
            default => 4,
        };
        $pairs = [];

        foreach ($items as $item) {
            $category = (string) $item->category?->name;
            $candidates = self::CATEGORY_SOURCES[$category] ?? null;
            if ($candidates === null) {
                throw new RuntimeException("No supplier source map is configured for item {$item->code} category {$category}.");
            }

            $available = [];
            foreach ($candidates as $name) {
                $vendor = $vendors->get($name);
                if ($vendor instanceof Vendor && $vendor->isPurchasable()) {
                    $available[] = $vendor;
                }
                if (count($available) >= $limit) {
                    break;
                }
            }
            if ($available === []) {
                throw new RuntimeException("No active supplier is available for item {$item->code}.");
            }
            if ($item->standard_cost === null || Money::cmp((string) $item->standard_cost, '0') <= 0) {
                throw new RuntimeException("Item {$item->code} needs a positive demo standard cost before a supplier quote can be planned.");
            }

            foreach ($available as $vendor) {
                if (! isset(self::VENDOR_QUOTES[$vendor->name])) {
                    throw new RuntimeException("No illustrative quote profile is configured for supplier {$vendor->name}.");
                }
                $pairs[] = ['vendor' => $vendor, 'item' => $item];
            }
        }

        return $pairs;
    }

    /** @param list<array{vendor:Vendor,item:Item}> $sourcePairs */
    private function assignPreferredSuppliers(
        DemoSeedContext $context,
        array $sourcePairs,
        User $reviewer,
        CarbonImmutable $asOf,
    ): void {
        $byItem = [];
        foreach ($sourcePairs as $pair) {
            $item = $pair['item'];
            $byItem[(int) $item->getKey()]['item'] = $item;
            $byItem[(int) $item->getKey()]['vendors'][] = $pair['vendor'];
        }

        $categoryOrdinals = [];
        foreach ($byItem as $itemId => $source) {
            /** @var Item $item */
            $item = $source['item'];
            /** @var list<Vendor> $vendors */
            $vendors = $source['vendors'];
            $category = (string) $item->category?->name;
            $ordinal = $categoryOrdinals[$category] ?? 0;
            $categoryOrdinals[$category] = $ordinal + 1;
            $preferredVendor = $vendors[$ordinal % count($vendors)] ?? null;
            if (! $preferredVendor instanceof Vendor) {
                throw new RuntimeException("No supplier can be preferred for raw-material item {$item->code}.");
            }

            $approved = ApprovedSupplier::query()
                ->qualified()
                ->where('item_id', $itemId)
                ->where('vendor_id', $preferredVendor->id)
                ->first();
            if (! $approved instanceof ApprovedSupplier) {
                throw new RuntimeException("Preferred supplier {$preferredVendor->name} has no qualified listing for {$item->code}.");
            }

            if (! $approved->is_preferred) {
                $context->at($asOf, fn () => $context->asUser($reviewer, fn () => $this->approvedSuppliers->update(
                    $approved,
                    ['is_preferred' => true],
                )));
            }

            $preferredCount = ApprovedSupplier::query()
                ->qualified()
                ->where('item_id', $itemId)
                ->where('is_preferred', true)
                ->count();
            if ($preferredCount !== 1) {
                throw new RuntimeException("Raw-material item {$item->code} must have exactly one qualified preferred supplier.");
            }
        }
    }

    private function ensureApprovedListing(
        DemoSeedContext $context,
        Vendor $vendor,
        Item $item,
        string $listingCode,
        CarbonImmutable $submittedAt,
        User $reviewer,
        string $factor,
    ): SupplierItemListing {
        $listing = $this->ensureSubmittedListing($context, $vendor, $item, $listingCode, $submittedAt, $factor, false);
        if ($listing->status === SupplierListingStatus::Pending) {
            $reviewedAt = $this->nextBusinessDay($submittedAt);
            $listing = $context->at($reviewedAt, fn (): SupplierItemListing => $this->withDeterministicUuids(
                $listingCode.':approve',
                fn (): SupplierItemListing => $context->asUser($reviewer, fn (): SupplierItemListing => $this->listings->approve($listing, $reviewer)),
            ));
        } elseif (! in_array($listing->status, [SupplierListingStatus::Approved, SupplierListingStatus::Superseded], true)) {
            throw new RuntimeException("Demo supplier listing {$listingCode} is {$listing->status->value} and cannot be approved as planned.");
        }

        return $listing->fresh();
    }

    private function ensureRejectedListing(
        DemoSeedContext $context,
        Vendor $vendor,
        Item $item,
        string $listingCode,
        CarbonImmutable $submittedAt,
        User $reviewer,
    ): SupplierItemListing {
        $listing = $this->findOwnedListing($vendor, $item, $listingCode);
        if ($listing === null) {
            $listing = $this->ensureSubmittedListing($context, $vendor, $item, $listingCode, $submittedAt, '1.20', false);
        }
        if ($listing->status === SupplierListingStatus::Pending) {
            $reviewedAt = $this->nextBusinessDay($submittedAt);
            $listing = $context->at($reviewedAt, fn (): SupplierItemListing => $this->withDeterministicUuids(
                $listingCode.':reject',
                fn (): SupplierItemListing => $context->asUser($reviewer, fn (): SupplierItemListing => $this->listings->reject(
                    $listing,
                    $reviewer,
                    'Quoted price exceeds the approved sourcing benchmark.',
                )),
            ));
        } elseif ($listing->status !== SupplierListingStatus::Rejected
            || $listing->rejection_reason !== 'Quoted price exceeds the approved sourcing benchmark.') {
            throw new RuntimeException("Demo supplier listing {$listingCode} does not match its planned rejected state.");
        }

        return $listing->fresh();
    }

    private function ensurePendingListing(
        DemoSeedContext $context,
        Vendor $vendor,
        Item $item,
        string $listingCode,
        CarbonImmutable $submittedAt,
    ): SupplierItemListing {
        $listing = $this->ensureSubmittedListing($context, $vendor, $item, $listingCode, $submittedAt, '1.11', true);
        if ($listing->status !== SupplierListingStatus::Pending || $listing->reviewed_by !== null || $listing->reviewed_at !== null) {
            throw new RuntimeException("Demo live listing {$listingCode} is no longer pending review; restore the demo snapshot before reseeding.");
        }

        return $listing;
    }

    private function ensureSubmittedListing(
        DemoSeedContext $context,
        Vendor $vendor,
        Item $item,
        string $listingCode,
        CarbonImmutable $submittedAt,
        string $factor,
        bool $liveNotification,
    ): SupplierItemListing {
        $existing = $this->findOwnedListing($vendor, $item, $listingCode);
        $offer = $this->offer($vendor, $item, $factor, $submittedAt);
        if ($existing !== null) {
            // A pending supplier offer may still be revised through the
            // application's normal update action. Approved/rejected offers
            // keep their submitted supplier-name snapshot as history.
            if ($existing->status === SupplierListingStatus::Pending
                && $existing->supplier_item_name !== $item->name) {
                $existing = $this->listings->update($existing, ['supplier_item_name' => $item->name]);
            }
            $this->assertListingMatchesOffer($existing, $vendor, $item, $listingCode, $submittedAt, $offer);

            return $existing;
        }

        $submit = fn (): SupplierItemListing => $this->withDeterministicUuids(
            $listingCode.':submit',
            fn (): SupplierItemListing => $this->listings->submit((int) $vendor->id, [
                'item_id' => (int) $item->id,
                'supplier_item_code' => $listingCode,
                'supplier_item_name' => $item->name,
                'price' => $offer['price'],
                'order_uom' => $offer['order_uom'],
                'base_qty_per_order_unit' => $offer['base_qty_per_order_unit'],
                'lead_time_days' => $offer['lead_time_days'],
                'valid_until' => $offer['valid_until'],
            ]),
        );
        $listing = $context->at($submittedAt, fn (): SupplierItemListing => $liveNotification
            ? $submit()
            : $context->withoutNotifications($submit));
        $this->assertListingMatchesOffer($listing, $vendor, $item, $listingCode, $submittedAt, $offer);

        return $listing;
    }

    private function findOwnedListing(Vendor $vendor, Item $item, string $listingCode): ?SupplierItemListing
    {
        $sameCode = SupplierItemListing::query()->where('supplier_item_code', $listingCode)->first();
        if ($sameCode !== null && ((int) $sameCode->vendor_id !== (int) $vendor->id || (int) $sameCode->item_id !== (int) $item->id)) {
            throw new RuntimeException("Supplier item code {$listingCode} is already owned by another vendor/item pair.");
        }

        return $sameCode;
    }

    /**
     * @return array{price:string,order_uom:string,base_qty_per_order_unit:string,lead_time_days:int,valid_until:string}
     */
    private function offer(Vendor $vendor, Item $item, string $factor, CarbonImmutable $submittedAt): array
    {
        $profile = self::VENDOR_QUOTES[$vendor->name];
        $quantity = $this->orderQuantity($item);
        $price = Money::mul(Money::mul((string) $item->standard_cost, $quantity['base_qty_per_order_unit']), $factor);
        $leadDays = max(1, min(365, (int) $item->lead_time_days + $profile['lead_offset']));

        return [
            'price' => $price,
            'order_uom' => $quantity['order_uom'],
            'base_qty_per_order_unit' => $quantity['base_qty_per_order_unit'],
            'lead_time_days' => $leadDays,
            'valid_until' => $submittedAt->addYear()->toDateString(),
        ];
    }

    /** @return array{order_uom:string,base_qty_per_order_unit:string} */
    private function orderQuantity(Item $item): array
    {
        return match ($item->category?->name) {
            'Resins' => ['order_uom' => 'bag', 'base_qty_per_order_unit' => '25.0000'],
            'Colorants' => ['order_uom' => 'bag', 'base_qty_per_order_unit' => '5.0000'],
            'Metal Inserts' => ['order_uom' => 'box', 'base_qty_per_order_unit' => $item->code === 'RM-052' ? '500.0000' : '1000.0000'],
            'Packaging' => match ($item->code) {
                'PKG-001' => ['order_uom' => 'box', 'base_qty_per_order_unit' => '1000.0000'],
                'PKG-002' => ['order_uom' => 'box', 'base_qty_per_order_unit' => '100.0000'],
                'PKG-003' => ['order_uom' => 'roll', 'base_qty_per_order_unit' => '1.0000'],
                'PKG-004' => ['order_uom' => 'pallet', 'base_qty_per_order_unit' => '1.0000'],
                default => throw new RuntimeException("No supplier order-unit conversion is defined for packaging item {$item->code}."),
            },
            'Process Consumables' => ['order_uom' => strtolower((string) $item->unit_of_measure), 'base_qty_per_order_unit' => '1.0000'],
            'Spare Parts' => ['order_uom' => 'pcs', 'base_qty_per_order_unit' => '1.0000'],
            default => throw new RuntimeException("No supplier order-unit conversion is defined for item {$item->code}."),
        };
    }

    private function assertListingMatchesOffer(
        SupplierItemListing $listing,
        Vendor $vendor,
        Item $item,
        string $listingCode,
        CarbonImmutable $submittedAt,
        array $offer,
    ): void {
        $expected = [
            'vendor_id' => (int) $vendor->id,
            'item_id' => (int) $item->id,
            'supplier_item_code' => $listingCode,
            'price' => $offer['price'],
            'order_uom' => $offer['order_uom'],
            'base_qty_per_order_unit' => $offer['base_qty_per_order_unit'],
            'lead_time_days' => $offer['lead_time_days'],
            'valid_until' => $offer['valid_until'],
            'submitted_at' => $submittedAt->toDateTimeString(),
        ];
        foreach ($expected as $field => $value) {
            $actual = match ($field) {
                'valid_until' => $listing->valid_until?->toDateString(),
                'submitted_at' => $listing->submitted_at?->toDateTimeString(),
                default => $listing->getAttribute($field),
            };
            if ((string) ($actual ?? '') !== (string) $value) {
                throw new RuntimeException("Seed-owned supplier listing {$listingCode} differs from its planned {$field} value.");
            }
        }
        if (trim((string) $listing->supplier_item_name) === '') {
            throw new RuntimeException("Seed-owned supplier listing {$listingCode} has no supplier item name.");
        }
    }

    /**
     * @param  list<array{vendor:Vendor,item:Item}>  $sourcePairs
     */
    private function verifyOwnedListings(array $sourcePairs, Vendor $resinVendor, Item $historyItem, Item $rejectionItem, SupplierItemListing $pending): void
    {
        $expectedCodes = [];
        foreach ($sourcePairs as $pair) {
            $vendor = $pair['vendor'];
            $item = $pair['item'];
            $revision = $vendor->name === $resinVendor->name && $item->code === 'RM-001' ? 'R2' : 'R1';
            $expectedCodes[] = $this->listingCode($vendor, $item, $revision);
        }
        $expectedCodes[] = $this->listingCode($resinVendor, $historyItem, 'R1');
        $expectedCodes[] = $this->listingCode($resinVendor, $rejectionItem, 'REJECTED');
        $expectedCodes[] = (string) $pending->supplier_item_code;
        $owned = SupplierItemListing::query()->whereIn('supplier_item_code', array_unique($expectedCodes))->get();
        $expectedStatuses = [
            'approved' => count($sourcePairs),
            'pending' => 1,
            'rejected' => 1,
            'superseded' => 1,
        ];
        $actualStatuses = $owned
            ->groupBy(static fn (SupplierItemListing $listing): string => $listing->status->value)
            ->map(static fn ($group): int => $group->count())
            ->sortKeys()
            ->all();
        ksort($expectedStatuses);
        if ($actualStatuses !== $expectedStatuses) {
            throw new RuntimeException('The demo supplier listing status distribution does not match the planned lifecycle states.');
        }

        foreach ($sourcePairs as $pair) {
            /** @var Vendor $vendor */
            $vendor = $pair['vendor'];
            /** @var Item $item */
            $item = $pair['item'];
            $revision = $vendor->name === $resinVendor->name && $item->code === 'RM-001' ? 'R2' : 'R1';
            $listing = $this->findOwnedListing($vendor, $item, $this->listingCode($vendor, $item, $revision));
            if (! $listing instanceof SupplierItemListing || $listing->status !== SupplierListingStatus::Approved) {
                throw new RuntimeException("Current approved supplier listing is missing for {$vendor->name} / {$item->code}.");
            }
            $approved = ApprovedSupplier::query()
                ->where('vendor_id', $vendor->id)
                ->where('item_id', $item->id)
                ->where('supplier_listing_id', $listing->id)
                ->where('qualification_status', ApprovedSupplier::QUALIFICATION_APPROVED)
                ->first();
            $expectedPrice = Money::round2(Money::div(
                $this->offer($vendor, $item, $this->quoteFactor($vendor), $listing->submitted_at->toImmutable())['price'],
                $this->orderQuantity($item)['base_qty_per_order_unit'],
                6,
            ));
            if (! $approved instanceof ApprovedSupplier || Money::cmp((string) $approved->last_price, $expectedPrice) !== 0) {
                throw new RuntimeException("Approved supplier link or base-unit price is inconsistent for {$vendor->name} / {$item->code}.");
            }
        }

        $oldRevision = $this->findOwnedListing($resinVendor, $historyItem, $this->listingCode($resinVendor, $historyItem, 'R1'));
        $rejected = $this->findOwnedListing($resinVendor, $rejectionItem, $this->listingCode($resinVendor, $rejectionItem, 'REJECTED'));
        if ($oldRevision?->status !== SupplierListingStatus::Superseded || $rejected?->status !== SupplierListingStatus::Rejected) {
            throw new RuntimeException('The supplier listing revision or rejected-offer scenario is missing.');
        }
        if ($pending->status !== SupplierListingStatus::Pending || $pending->reviewed_by !== null || $pending->reviewed_at !== null) {
            throw new RuntimeException('The live supplier listing must remain pending without a reviewer.');
        }
        $hasReviewerNotice = DB::table('notifications')
            ->where('type', 'supplier_listing_submitted')
            ->where('notifiable_type', User::class)
            ->whereRaw("data::jsonb ->> 'subject_id' = ?", [(string) $pending->id])
            ->exists();
        if (! $hasReviewerNotice) {
            throw new RuntimeException('The live pending supplier listing did not create a reviewer inbox notification.');
        }
    }

    private function listingCode(Vendor $vendor, Item $item, string $revision): string
    {
        $profile = self::VENDOR_QUOTES[$vendor->name] ?? null;
        if ($profile === null) {
            throw new RuntimeException("No supplier item-code prefix is defined for {$vendor->name}.");
        }

        return 'D-'.$profile['code'].'-'.$item->code.'-'.$revision;
    }

    private function quoteFactor(Vendor $vendor): string
    {
        return self::VENDOR_QUOTES[$vendor->name]['price_factor'];
    }

    private function previousBusinessDay(CarbonImmutable $date): CarbonImmutable
    {
        $date = $date->setTime(9, 0);
        while ($date->isWeekend() || Holiday::query()->whereDate('date', $date->toDateString())->exists()) {
            $date = $date->subDay();
        }

        return $date;
    }

    private function nextBusinessDay(CarbonImmutable $date): CarbonImmutable
    {
        $date = $date->addDay()->setTime(9, 0);
        while ($date->isWeekend() || Holiday::query()->whereDate('date', $date->toDateString())->exists()) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function withDeterministicUuids(string $identity, Closure $action): mixed
    {
        $index = 0;
        Str::createUuidsUsing(static function () use ($identity, &$index): UuidInterface {
            $hex = substr(hash('sha256', 'demo-supplier-listing:'.$identity.':'.$index++), 0, 32);
            $hex[12] = '4';
            $hex[16] = ['8', '9', 'a', 'b'][hexdec($hex[16]) % 4];
            $uuid = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);

            return Uuid::fromString($uuid);
        });

        try {
            return $action();
        } finally {
            Str::createUuidsNormally();
        }
    }
}
