<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Accounting\Enums\WithholdingTaxType;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\CustomerService;
use App\Modules\Accounting\Services\VendorService;
use App\Modules\CRM\Models\CustomerProductWarrantyOption;
use App\Modules\CRM\Models\Product;
use RuntimeException;

final class BusinessPartnerCatalogLayer extends DemoSeeder
{
    /** @var list<array{name: string, email: string, terms: int, credit: string, address: string, delivery: string, latitude: string, longitude: string, group: string, active: bool}> */
    private const CUSTOMERS = [
        ['name' => 'Amihan Vehicle Systems Corporation', 'email' => 'purchasing@amihan-vehicle.ogami-demo.test', 'terms' => 45, 'credit' => '2500000.00', 'address' => 'Unit 4, Demo Technology Park, Governor\'s Drive, Dasmariñas, Cavite', 'delivery' => 'Receiving Bay 2, Demo Technology Park, Dasmariñas, Cavite', 'latitude' => '14.3261000', 'longitude' => '120.9388000', 'group' => 'electrical', 'active' => true],
        ['name' => 'Hiraya Motion Components Incorporated', 'email' => 'materials@hiraya-motion.ogami-demo.test', 'terms' => 30, 'credit' => '1800000.00', 'address' => 'Lot 8, Illustrative Commerce Estate, Biñan, Laguna', 'delivery' => 'Gate 3, Illustrative Commerce Estate, Biñan, Laguna', 'latitude' => '14.3295000', 'longitude' => '121.0809000', 'group' => 'motion', 'active' => true],
        ['name' => 'Sampaguita Motorcycle Systems Corporation', 'email' => 'supply@sampaguita-moto.ogami-demo.test', 'terms' => 30, 'credit' => '1400000.00', 'address' => 'Building C, Sample Industrial Compound, Carmona, Cavite', 'delivery' => 'Inbound Dock C, Sample Industrial Compound, Carmona, Cavite', 'latitude' => '14.3132000', 'longitude' => '121.0562000', 'group' => 'motorcycle', 'active' => true],
        ['name' => 'Silangan Electrical Assemblies Corporation', 'email' => 'sourcing@silangan-electrical.ogami-demo.test', 'terms' => 60, 'credit' => '3200000.00', 'address' => 'Block 2, South Luzon Demo Park, Cabuyao, Laguna', 'delivery' => 'Warehouse 2, South Luzon Demo Park, Cabuyao, Laguna', 'latitude' => '14.2786000', 'longitude' => '121.1264000', 'group' => 'electrical-motion', 'active' => true],
        ['name' => 'Bayani Interior Trim Systems Incorporated', 'email' => 'procurement@bayani-trim.ogami-demo.test', 'terms' => 45, 'credit' => '2100000.00', 'address' => 'Unit 11, Demonstration Manufacturing Center, Santa Rosa, Laguna', 'delivery' => 'Dock 1, Demonstration Manufacturing Center, Santa Rosa, Laguna', 'latitude' => '14.3129000', 'longitude' => '121.1110000', 'group' => 'electrical', 'active' => true],
        ['name' => 'Luntian Ride Components Corporation', 'email' => 'materials@luntian-ride.ogami-demo.test', 'terms' => 30, 'credit' => '1250000.00', 'address' => 'Lot 6, Sample Industrial Compound, General Trias, Cavite', 'delivery' => 'Receiving Area A, Sample Industrial Compound, General Trias, Cavite', 'latitude' => '14.3867000', 'longitude' => '120.8816000', 'group' => 'motorcycle', 'active' => true],
        ['name' => 'Mabini Vehicle Controls Incorporated', 'email' => 'sourcing@mabini-controls.ogami-demo.test', 'terms' => 60, 'credit' => '2800000.00', 'address' => 'Building 5, Demo Logistics Estate, Santo Tomas, Batangas', 'delivery' => 'Inbound Gate 5, Demo Logistics Estate, Santo Tomas, Batangas', 'latitude' => '14.1072000', 'longitude' => '121.1413000', 'group' => 'electrical', 'active' => true],
        ['name' => 'Tala Mobility Products Corporation', 'email' => 'purchasing@tala-mobility.ogami-demo.test', 'terms' => 45, 'credit' => '1900000.00', 'address' => 'Unit 9, Illustrative Trade Center, Antipolo, Rizal', 'delivery' => 'Warehouse 9, Illustrative Trade Center, Antipolo, Rizal', 'latitude' => '14.5867000', 'longitude' => '121.1760000', 'group' => 'electrical-motorcycle', 'active' => true],
        ['name' => 'Dalisay Powertrain Plastics Incorporated', 'email' => 'materials@dalisay-powertrain.ogami-demo.test', 'terms' => 30, 'credit' => '1600000.00', 'address' => 'Block 7, Sample Commerce Park, Lipa, Batangas', 'delivery' => 'Dock 4, Sample Commerce Park, Lipa, Batangas', 'latitude' => '13.9411000', 'longitude' => '121.1622000', 'group' => 'motion-motorcycle', 'active' => true],
        ['name' => 'Silay Auto Enclosure Systems Corporation', 'email' => 'procurement@silay-enclosures.ogami-demo.test', 'terms' => 60, 'credit' => '3500000.00', 'address' => 'Building 3, Demo Industrial Estate, Parañaque, Metro Manila', 'delivery' => 'Receiving Dock 3, Demo Industrial Estate, Parañaque, Metro Manila', 'latitude' => '14.4793000', 'longitude' => '121.0198000', 'group' => 'all', 'active' => false],
        ['name' => 'Habagat Vehicle Components Corporation', 'email' => 'supply@habagat-components.ogami-demo.test', 'terms' => 45, 'credit' => '1700000.00', 'address' => 'Lot 12, Illustrative Industrial Park, Imus, Cavite', 'delivery' => 'Gate 1, Illustrative Industrial Park, Imus, Cavite', 'latitude' => '14.4299000', 'longitude' => '120.9367000', 'group' => 'motion', 'active' => false],
        ['name' => 'Tibay Manufacturing Systems Incorporated', 'email' => 'procurement@tibay-manufacturing.ogami-demo.test', 'terms' => 30, 'credit' => '1550000.00', 'address' => 'Unit 2, Demo Industrial Park, Calamba, Laguna', 'delivery' => 'Inbound Bay 2, Demo Industrial Park, Calamba, Laguna', 'latitude' => '14.2117000', 'longitude' => '121.1653000', 'group' => 'motorcycle', 'active' => true],
        ['name' => 'Salinlahi Automotive Electronics Corporation', 'email' => 'sourcing@salinlahi-electronics.ogami-demo.test', 'terms' => 60, 'credit' => '3000000.00', 'address' => 'Lot 3, Sample Technology Estate, Muntinlupa, Metro Manila', 'delivery' => 'Receiving Gate 3, Sample Technology Estate, Muntinlupa, Metro Manila', 'latitude' => '14.4081000', 'longitude' => '121.0415000', 'group' => 'electrical-motion', 'active' => true],
    ];

    /** @var list<array{name: string, email: string, terms: int, tax_type: WithholdingTaxType, address: string, active: bool}> */
    private const VENDORS = [
        ['name' => 'Luzon Polymer Resins Corporation', 'email' => 'sales@luzon-polymer.ogami-demo.test', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Lot 2, Demo Polymer Trade Center, Valenzuela, Metro Manila', 'active' => true],
        ['name' => 'Hiraya Colorants and Compounds Incorporated', 'email' => 'orders@hiraya-colorants.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Unit 5, Sample Chemical Park, Calamba, Laguna', 'active' => true],
        ['name' => 'Cavite Industrial Packaging Supply Corporation', 'email' => 'sales@cavite-packaging.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Warehouse 8, Illustrative Commerce Estate, Dasmariñas, Cavite', 'active' => true],
        ['name' => 'Mabini Precision Metal Inserts Incorporated', 'email' => 'quotes@mabini-inserts.ogami-demo.test', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Block 4, Demo Metalworks Park, Carmona, Cavite', 'active' => true],
        ['name' => 'South Luzon Industrial Spares Corporation', 'email' => 'sales@southluzon-spares.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Unit 6, Sample Industrial Supply Center, Biñan, Laguna', 'active' => true],
        ['name' => 'Bayan Precision Fasteners Supply Incorporated', 'email' => 'orders@bayan-fasteners.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Lot 1, Demonstration Hardware Estate, Bacoor, Cavite', 'active' => true],
        ['name' => 'Talisay Engineering Components Corporation', 'email' => 'sales@talisay-engineering.ogami-demo.test', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Building 4, Illustrative Industrial Park, Santo Tomas, Batangas', 'active' => true],
        ['name' => 'Monarch Tooling Services Incorporated', 'email' => 'service@monarch-tooling.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Services, 'address' => 'Unit 3, Demo Tooling Center, General Trias, Cavite', 'active' => true],
        ['name' => 'Kalinaw Industrial Oils and Lubricants Corporation', 'email' => 'sales@kalinaw-lubricants.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Lot 9, Sample Industrial Supply Park, Cabuyao, Laguna', 'active' => true],
        ['name' => 'Lakandula Machine Components Incorporated', 'email' => 'orders@lakandula-machine.ogami-demo.test', 'terms' => 60, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Block 8, Illustrative Machinery Estate, Quezon City, Metro Manila', 'active' => true],
        ['name' => 'Verde Polymer Trading Corporation', 'email' => 'sales@verde-polymer.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Warehouse 3, Demo Resin Center, Santa Rosa, Laguna', 'active' => true],
        ['name' => 'Carmona Packaging Systems Incorporated', 'email' => 'quotes@carmona-packaging.ogami-demo.test', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Unit 12, Sample Logistics Estate, Carmona, Cavite', 'active' => true],
        ['name' => 'Batangas Industrial Controls Corporation', 'email' => 'sales@batangas-controls.ogami-demo.test', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Lot 5, Demonstration Controls Park, Lipa, Batangas', 'active' => true],
        ['name' => 'Makati Calibration Services Incorporated', 'email' => 'service@makati-calibration.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Services, 'address' => 'Suite 204, Demo Technical Center, Makati, Metro Manila', 'active' => true],
        ['name' => 'Sampaguita Logistics and Pallet Supply Corporation', 'email' => 'dispatch@sampaguita-logistics.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Services, 'address' => 'Warehouse 2, Illustrative Freight Park, Parañaque, Metro Manila', 'active' => true],
        ['name' => 'Sierra Pacific Maintenance Supply Incorporated', 'email' => 'orders@sierra-maintenance.ogami-demo.test', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Block 11, Sample Maintenance Estate, Imus, Cavite', 'active' => true],
        ['name' => 'Pagsibol Thermoplastic Solutions Corporation', 'email' => 'sales@pagsibol-thermoplastic.ogami-demo.test', 'terms' => 60, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Lot 14, Demo Materials Park, Calamba, Laguna', 'active' => true],
        ['name' => 'San Roque Precision Metals Incorporated', 'email' => 'quotes@sanroque-metals.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Unit 7, Illustrative Metal Components Center, Dasmariñas, Cavite', 'active' => false],
        ['name' => 'Haraya Industrial Safety Systems Incorporated', 'email' => 'orders@haraya-safety.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Building 6, Demo Safety Supply Park, Muntinlupa, Metro Manila', 'active' => true],
        ['name' => 'Silangan Process Equipment Corporation', 'email' => 'sales@silangan-process.ogami-demo.test', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Lot 10, Sample Equipment Estate, Santo Tomas, Batangas', 'active' => true],
        ['name' => 'Balangay Technical Contractors Incorporated', 'email' => 'service@balangay-contractors.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Services, 'address' => 'Suite 3, Illustrative Engineering Center, Taguig, Metro Manila', 'active' => true],
        ['name' => 'Dapithapon Industrial Consumables Corporation', 'email' => 'orders@dapithapon-consumables.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Unit 1, Demo Supply Compound, Bacoor, Cavite', 'active' => true],
        ['name' => 'Mayon Polymer Distribution Incorporated', 'email' => 'sales@mayon-polymer.ogami-demo.test', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Warehouse 10, Sample Polymer Park, Valenzuela, Metro Manila', 'active' => true],
        ['name' => 'Bituin Industrial Instrumentation Corporation', 'email' => 'service@bituin-instrumentation.ogami-demo.test', 'terms' => 30, 'tax_type' => WithholdingTaxType::Services, 'address' => 'Lot 2, Illustrative Instrument Center, Santa Rosa, Laguna', 'active' => true],
    ];

    /** @var array<string, list<string>> */
    private const PRODUCT_GROUPS = [
        'electrical' => ['PC-001', 'PC-002', 'RC-001', 'RC-002', 'CH-001', 'FC-001', 'TC-001', 'HR-001'],
        'motion' => ['WB-001', 'WB-002', 'BB-001', 'BU-001'],
        'motorcycle' => ['MH-001', 'HS-001', 'AD-001', 'BC-001'],
        'electrical-motion' => ['PC-001', 'PC-002', 'RC-001', 'RC-002', 'CH-001', 'FC-001', 'TC-001', 'HR-001', 'WB-001', 'WB-002', 'BB-001', 'BU-001'],
        'electrical-motorcycle' => ['PC-001', 'PC-002', 'RC-001', 'RC-002', 'CH-001', 'FC-001', 'TC-001', 'HR-001', 'MH-001', 'HS-001', 'AD-001', 'BC-001'],
        'motion-motorcycle' => ['WB-001', 'WB-002', 'BB-001', 'BU-001', 'MH-001', 'HS-001', 'AD-001', 'BC-001'],
        'all' => ['WB-001', 'WB-002', 'PC-001', 'PC-002', 'RC-001', 'RC-002', 'BB-001', 'BU-001', 'CH-001', 'FC-001', 'MH-001', 'HS-001', 'AD-001', 'TC-001', 'BC-001', 'HR-001'],
    ];

    public function __construct(
        private readonly CustomerService $customers,
        private readonly VendorService $vendors,
    ) {}

    public function key(): string
    {
        return 'business-partners';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['Accounting', 'CRM', 'Purchasing'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['finished-product-catalog'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $context->at($context->options->asOf, function () use ($context): void {
            $scale = $context->options->scale;
            $customerCount = match ($scale) {
                'small' => 3,
                'full' => count(self::CUSTOMERS),
                default => 10,
            };
            $vendorCount = match ($scale) {
                'small' => 5,
                'full' => count(self::VENDORS),
                default => 18,
            };

            $products = Product::query()->active()->orderBy('part_number')->get()->keyBy('part_number');
            if ($products->count() < 16) {
                throw new RuntimeException('The finished-product catalog must be seeded before business partners.');
            }

            foreach (array_slice(self::CUSTOMERS, 0, $customerCount) as $index => $definition) {
                $productCodes = self::PRODUCT_GROUPS[$definition['group']] ?? null;
                if ($productCodes === null) {
                    throw new RuntimeException("No product access group is defined for customer {$definition['name']}.");
                }
                $customerProducts = [];
                foreach ($productCodes as $productCode) {
                    $product = $products->get($productCode);
                    if (! $product instanceof Product) {
                        throw new RuntimeException("Finished product {$productCode} required by business partners is missing.");
                    }
                    $customerProducts[] = $product;
                }

                $faker = $context->faker();
                $contact = $faker->name();
                $phone = sprintf('+63 000 555 01%02d', $index + 1);
                $payload = [
                    'name' => $definition['name'],
                    'contact_person' => $contact,
                    'contact_person_role' => 'Procurement Manager',
                    'email' => $definition['email'],
                    'phone' => $phone,
                    'address' => $definition['address'],
                    'delivery_address' => $definition['delivery'],
                    'delivery_latitude' => $definition['latitude'],
                    'delivery_longitude' => $definition['longitude'],
                    'delivery_contact_person' => $contact,
                    'delivery_contact_phone' => $phone,
                    'allowed_payment_methods' => ['bank_transfer', 'check'],
                    'shipping_payer' => $index % 3 === 0 ? 'company' : 'customer',
                    'warranty_duration_days' => 365,
                    'warranty_report_window_days' => 30,
                    'warranty_start_basis' => 'delivery',
                    'warranty_covered_issues' => ['manufacturing_defect', 'shipping_damage'],
                    'warranty_remedies' => ['repair', 'replace', 'credit'],
                    'credit_limit' => $definition['credit'],
                    'payment_terms_days' => $definition['terms'],
                    'portal_catalog_mode' => 'restricted',
                    'is_active' => $definition['active'],
                    'portal_product_ids' => array_map(static fn (Product $product): int => (int) $product->getKey(), $customerProducts),
                    'product_warranties' => array_map(static fn (Product $product): array => [
                        'product_id' => (int) $product->getKey(),
                        'label' => '12-Month Manufacturing Warranty',
                        'warranty_duration_days' => 365,
                        'warranty_report_window_days' => 30,
                        'warranty_start_basis' => 'delivery',
                        'warranty_covered_issues' => ['manufacturing_defect', 'shipping_damage'],
                        'warranty_remedies' => ['repair', 'replace', 'credit'],
                        'additional_terms' => 'Illustrative demo terms only; no legal offer or customer commitment.',
                    ], $customerProducts),
                ];

                $customer = Customer::withTrashed()->where('name', $definition['name'])->first();
                if ($customer === null) {
                    $customer = $this->customers->create($payload);
                } else {
                    if ($customer->trashed()) {
                        $customer->restore();
                        $customer->refresh();
                    }
                    $customer = $this->updateCustomerIfNeeded($customer, $payload);
                    $this->assertCustomerRelations($customer, $payload);
                }

                $context->records()->remember('business-partner.customer.'.$definition['name'], $customer);
            }

            foreach (array_slice(self::VENDORS, 0, $vendorCount) as $index => $definition) {
                $faker = $context->faker();
                $payload = [
                    'name' => $definition['name'],
                    'contact_person' => $faker->name(),
                    'email' => $definition['email'],
                    'phone' => sprintf('+63 000 555 02%02d', $index + 1),
                    'address' => $definition['address'],
                    // Leave tax IDs blank. Withholding classifications use the
                    // current application's enum; no statutory rate is guessed.
                    'payment_terms_days' => $definition['terms'],
                    'withholding_tax_type' => $definition['tax_type'],
                    'is_active' => $definition['active'],
                ];

                $vendor = Vendor::withTrashed()->where('name', $definition['name'])->first();
                if ($vendor === null) {
                    $vendor = $this->vendors->create($payload);
                } else {
                    if ($vendor->trashed()) {
                        $vendor = $this->vendors->restore($vendor);
                    }
                    $changes = $this->changedAttributes($vendor, $payload);
                    if ($changes !== []) {
                        $vendor = $this->vendors->update($vendor, $changes);
                    }
                }

                $context->records()->remember('business-partner.vendor.'.$definition['name'], $vendor);
            }
        });
    }

    /** @param array<string, mixed> $payload */
    private function updateCustomerIfNeeded(Customer $customer, array $payload): Customer
    {
        unset($payload['portal_product_ids'], $payload['product_warranties']);
        $changes = $this->changedAttributes($customer, $payload);

        return $changes === [] ? $customer : $this->customers->update($customer, $changes);
    }

    /** @param array<string, mixed> $payload */
    private function assertCustomerRelations(Customer $customer, array $payload): void
    {
        $expectedProducts = array_map('intval', $payload['portal_product_ids']);
        sort($expectedProducts);
        $actualProducts = $customer->portalProducts()->orderBy('products.id')->pluck('products.id')
            ->map(static fn ($id): int => (int) $id)->all();
        sort($actualProducts);

        if ($actualProducts !== $expectedProducts) {
            throw new RuntimeException("Customer product access for {$customer->name} differs from the demo catalog; reset the demo database before reseeding changed definitions.");
        }

        foreach ($payload['product_warranties'] as $expected) {
            $exists = CustomerProductWarrantyOption::query()
                ->where('customer_id', $customer->id)
                ->where('product_id', $expected['product_id'])
                ->where('label', $expected['label'])
                ->exists();
            if (! $exists) {
                throw new RuntimeException("Customer warranty options for {$customer->name} differ from the demo catalog; reset the demo database before reseeding changed definitions.");
            }
        }
    }

    /** @param array<string, mixed> $expected
     * @return array<string, mixed>
     */
    private function changedAttributes(Customer|Vendor $model, array $expected): array
    {
        $changes = [];
        foreach ($expected as $attribute => $value) {
            if (in_array($attribute, ['portal_product_ids', 'product_warranties'], true)) {
                continue;
            }
            if ($model->getAttribute($attribute) != $value) {
                $changes[$attribute] = $value;
            }
        }

        return $changes;
    }
}
