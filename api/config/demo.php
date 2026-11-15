<?php

declare(strict_types=1);

use Database\Seeders\Demo\BomRoutingCatalogLayer;
use Database\Seeders\Demo\BusinessPartnerCatalogLayer;
use Database\Seeders\Demo\CrmPricingLayer;
use Database\Seeders\Demo\FinishedProductCatalogLayer;
use Database\Seeders\Demo\InventoryCatalogLayer;
use Database\Seeders\Demo\InventoryCountLiveScenarioLayer;
use Database\Seeders\Demo\MaintenanceHistoryLayer;
use Database\Seeders\Demo\MaintenanceMasterDataLayer;
use Database\Seeders\Demo\MaintenanceSparePartsCatalogLayer;
use Database\Seeders\Demo\OpeningStockBalancesLayer;
use Database\Seeders\Demo\OrderToCashHistoryLayer;
use Database\Seeders\Demo\OrganizationReferenceDataLayer;
use Database\Seeders\Demo\ProductionAssetsLayer;
use Database\Seeders\Demo\ProductionExecutionHistoryLayer;
use Database\Seeders\Demo\ProductionHistoryLayer;
use Database\Seeders\Demo\PurchasingHistoryLayer;
use Database\Seeders\Demo\PurchasingReceivingHistoryLayer;
use Database\Seeders\Demo\QualityHistoryLayer;
use Database\Seeders\Demo\QualityInspectionReferencesLayer;
use Database\Seeders\Demo\QualityPlanLayer;
use Database\Seeders\Demo\SupplierListingLayer;
use Database\Seeders\Demo\WorkforceIdentityLayer;

$csv = static function (string $key, string $default = ''): array {
    return array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env($key, $default)),
    ), static fn (string $value): bool => $value !== ''));
};

return [
    'seeding' => [
        'enabled' => (bool) env('DEMO_SEEDING_ENABLED', false),
        'database_allowlist' => $csv('DEMO_SEEDING_DATABASE_ALLOWLIST'),
        'allowed_hosts' => $csv('DEMO_SEEDING_ALLOWED_HOSTS', 'localhost,127.0.0.1,::1,db'),
        'allow_non_demo_database' => (bool) env('DEMO_SEEDING_ALLOW_NON_DEMO_DATABASE', false),
    ],
    'credentials' => [
        'password' => (string) env('DEMO_ACCOUNT_PASSWORD', ''),
    ],
    'snapshot' => [
        'directory' => env('DEMO_SNAPSHOT_DIRECTORY') ?: storage_path('app/demo-snapshots'),
        'pg_dump_binary' => env('DEMO_PG_DUMP_BINARY', 'pg_dump'),
        'pg_restore_binary' => env('DEMO_PG_RESTORE_BINARY', 'pg_restore'),
    ],
    // Enabled only after every required layer exists and demo:verify passes.
    'reset_ready' => false,
    // Reference and transaction layers are added in dependency order as each
    // module group is implemented and verified.
    'layers' => [
        OrganizationReferenceDataLayer::class,
        InventoryCatalogLayer::class,
        FinishedProductCatalogLayer::class,
        ProductionAssetsLayer::class,
        BomRoutingCatalogLayer::class,
        BusinessPartnerCatalogLayer::class,
        WorkforceIdentityLayer::class,
        QualityPlanLayer::class,
        MaintenanceMasterDataLayer::class,
        QualityInspectionReferencesLayer::class,
        MaintenanceSparePartsCatalogLayer::class,
        SupplierListingLayer::class,
        OpeningStockBalancesLayer::class,
        CrmPricingLayer::class,
        PurchasingHistoryLayer::class,
        PurchasingReceivingHistoryLayer::class,
        QualityHistoryLayer::class,
        ProductionHistoryLayer::class,
        ProductionExecutionHistoryLayer::class,
        MaintenanceHistoryLayer::class,
        OrderToCashHistoryLayer::class,
        InventoryCountLiveScenarioLayer::class,
    ],
];
