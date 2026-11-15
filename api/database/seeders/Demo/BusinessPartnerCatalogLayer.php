<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Common\Exceptions\BusinessRuleException;
use App\Common\Services\DocumentSequenceService;
use App\Modules\Accounting\Enums\WithholdingTaxType;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\CustomerService;
use App\Modules\Accounting\Services\VendorService;
use App\Modules\Auth\Models\User;
use App\Modules\CRM\Models\CustomerProductWarrantyOption;
use App\Modules\CRM\Models\Product;
use Illuminate\Support\Str;
use RuntimeException;

final class BusinessPartnerCatalogLayer extends DemoSeeder
{
    /** @var list<string> */
    private const CUSTOMER_CONTACTS = [
        'Mikaela Santos', 'Paolo Bautista', 'Angelica Dizon', 'Rafael Mendoza',
        'Camille Reyes', 'Noel Garcia', 'Teresa Navarro', 'Miguel Aquino',
        'Leah Mercado', 'Daniel Villanueva',
    ];

    /** @var list<string> */
    private const VENDOR_CONTACTS = [
        'Mariel Santos', 'Akira Nakamura', 'Masato Aikawa', 'Janelle Cruz',
        'Kenji Tanigawa', 'Edwin Ramos',
    ];

    /** @var list<array{name: string, terms: int, credit: string, address: string, delivery: string, latitude: string, longitude: string, group: string, active: bool}> */
    private const CUSTOMERS = [
        ['name' => 'Mitsuba Philippines Corp. Plant 3', 'terms' => 45, 'credit' => '3500000.00', 'address' => 'LIMA Technology Center, Special Economic Zone, Lipa City, Batangas', 'delivery' => 'LIMA Technology Center, Special Economic Zone, Lipa City, Batangas', 'latitude' => '13.9520000', 'longitude' => '121.1670000', 'group' => 'electrical-motion', 'active' => true],
        ['name' => 'Mitsuba Philippines Corp. Plant 2', 'terms' => 45, 'credit' => '3500000.00', 'address' => 'Lot 1&2 Blk. 8 Phase 2, FCIE, Brgy. Langkaan, Dasmariñas, Cavite', 'delivery' => 'Lot 1&2 Blk. 8 Phase 2, FCIE, Brgy. Langkaan, Dasmariñas, Cavite', 'latitude' => '14.3261000', 'longitude' => '120.9388000', 'group' => 'electrical-motion', 'active' => true],
        ['name' => 'Mitsuba Philippines Corp. Plant 1', 'terms' => 45, 'credit' => '3500000.00', 'address' => 'Lot 1 Blk. 14 Phase 2, FCIE, Brgy. Langkaan, Dasmariñas, Cavite', 'delivery' => 'Lot 1 Blk. 14 Phase 2, FCIE, Brgy. Langkaan, Dasmariñas, Cavite', 'latitude' => '14.3261000', 'longitude' => '120.9388000', 'group' => 'electrical-motion', 'active' => true],
        ['name' => 'Hiblow Philippines Inc.', 'terms' => 30, 'credit' => '1800000.00', 'address' => 'Blk. 4 Lot 2, SEPZ-FCIE, Brgy. Langkaan, Dasmariñas, Cavite 4126', 'delivery' => 'Blk. 4 Lot 2, SEPZ-FCIE, Brgy. Langkaan, Dasmariñas, Cavite 4126', 'latitude' => '14.3261000', 'longitude' => '120.9388000', 'group' => 'electrical', 'active' => true],
        ['name' => 'Sanac Philippines Inc.', 'terms' => 30, 'credit' => '1400000.00', 'address' => 'Lot 9&10 Phase 2, FCIE, Brgy. Langkaan, Dasmariñas, Cavite', 'delivery' => 'Lot 9&10 Phase 2, FCIE, Brgy. Langkaan, Dasmariñas, Cavite', 'latitude' => '14.3261000', 'longitude' => '120.9388000', 'group' => 'electrical', 'active' => true],
        ['name' => 'Yazaki EDS Manufacturing, Inc.', 'terms' => 60, 'credit' => '3200000.00', 'address' => 'EMI-Special Economic Zone, Anabu 2-A, City of Imus 4103', 'delivery' => 'EMI-Special Economic Zone, Anabu 2-A, City of Imus 4103', 'latitude' => '14.4079000', 'longitude' => '120.9458000', 'group' => 'electrical', 'active' => true],
        ['name' => 'Yazaki-Torres Manufacturing Inc.', 'terms' => 45, 'credit' => '2100000.00', 'address' => 'Brgy. Makiling, Calamba, Laguna', 'delivery' => 'Brgy. Makiling, Calamba, Laguna', 'latitude' => '14.1810000', 'longitude' => '121.1720000', 'group' => 'electrical-motion', 'active' => true],
        ['name' => 'YGP PTE. LTD. Yazaki Global Purchasing', 'terms' => 60, 'credit' => '5000000.00', 'address' => 'Gateway East, 152 Beach Road, #31-01, Singapore 189721', 'delivery' => 'Gateway East, 152 Beach Road, #31-01, Singapore 189721', 'latitude' => '1.2930000', 'longitude' => '103.8560000', 'group' => 'all', 'active' => true],
        ['name' => 'OGAMI CORPORATION JAPAN', 'terms' => 30, 'credit' => '5000000.00', 'address' => '330 Fukushima, Chiyoda-Town, Ouragun Gunma Prefecture 370-0501', 'delivery' => '330 Fukushima, Chiyoda-Town, Ouragun Gunma Prefecture 370-0501', 'latitude' => '36.2180000', 'longitude' => '139.4540000', 'group' => 'all', 'active' => true],
        ['name' => 'Honda Philippines Inc.', 'terms' => 45, 'credit' => '4000000.00', 'address' => 'Lot 34 Phase 1-B Road 3, FPIP, Tanauan City, Batangas', 'delivery' => 'Lot 34 Phase 1-B Road 3, FPIP, Tanauan City, Batangas', 'latitude' => '14.1160000', 'longitude' => '121.1370000', 'group' => 'all', 'active' => true],
    ];

    /** @var array<string, list<string>> */
    private const CUSTOMER_ALIASES = [
        'Honda Philippines Inc.' => ['Honda Philippines, Inc.'],
    ];

    /** @var list<array{name: string, terms: int, tax_type: WithholdingTaxType, address: string, active: bool}> */
    private const VENDORS = [
        ['name' => 'Inabata Philippines Inc.', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Makati City, Metro Manila', 'active' => true],
        ['name' => 'Philippine Nakamura', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'FCIE, Dasmariñas, Cavite', 'active' => true],
        ['name' => 'Aikawa Philippines Inc.', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Carmona Industrial Estate, Carmona, Cavite', 'active' => true],
        ['name' => 'Junlec Printing Supply', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Malinta, Valenzuela City, Metro Manila', 'active' => true],
        ['name' => 'Tanigawa Seisakusho', 'terms' => 45, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Carmona, Cavite', 'active' => true],
        ['name' => 'Dyna Pack & Malinta', 'terms' => 30, 'tax_type' => WithholdingTaxType::Goods, 'address' => 'Malinta, Valenzuela City, Metro Manila', 'active' => true],
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
        private readonly DocumentSequenceService $sequences,
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
                'small' => 3,
                'full' => count(self::VENDORS),
                default => count(self::VENDORS),
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

                $contact = self::CUSTOMER_CONTACTS[$index];
                $email = $this->personEmail($contact);
                $this->assertContactEmailDoesNotCollideWithEmployeeAccount($email);
                $phone = sprintf('+63 000 555 01%02d', $index + 1);
                $payload = [
                    'name' => $definition['name'],
                    'contact_person' => $contact,
                    'contact_person_role' => 'Procurement Manager',
                    'email' => $email,
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
                        'additional_terms' => 'Warranty coverage is subject to the executed customer agreement and current product requirements.',
                    ], $customerProducts),
                ];

                $customer = Customer::withTrashed()->where('name', $definition['name'])->first();
                if ($customer === null) {
                    foreach (self::CUSTOMER_ALIASES[$definition['name']] ?? [] as $alias) {
                        $customer = Customer::withTrashed()->where('name', $alias)->first();
                        if ($customer !== null) {
                            break;
                        }
                    }
                }
                if ($customer === null) {
                    $customer = $this->customers->create($payload);
                } else {
                    if ($customer->trashed()) {
                        $customer->restore();
                        $customer->refresh();
                    }
                    $customer = $this->updateCustomerIfNeeded($customer, $payload);
                    $this->syncCustomerRelations($customer, $payload);
                    $this->assertCustomerRelations($customer, $payload);
                }

                if (! is_string($customer->code) || trim($customer->code) === '') {
                    $customer = $this->customers->update($customer, [
                        'code' => $this->sequences->generate('customer'),
                    ]);
                }

                $context->records()->remember('business-partner.customer.'.$definition['name'], $customer);
            }

            foreach (array_slice(self::VENDORS, 0, $vendorCount) as $index => $definition) {
                $contact = self::VENDOR_CONTACTS[$index];
                $email = $this->personEmail($contact);
                $this->assertContactEmailDoesNotCollideWithEmployeeAccount($email);
                $payload = [
                    'name' => $definition['name'],
                    'contact_person' => $contact,
                    'email' => $email,
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

            if ($scale !== 'small') {
                $this->retireUnlistedCustomers();
                $this->retireUnlistedVendors();
            }
        });
    }

    private function retireUnlistedCustomers(): void
    {
        $keep = array_column(self::CUSTOMERS, 'name');

        Customer::query()
            ->whereNotIn('name', $keep)
            ->orderBy('id')
            ->chunkById(100, function ($customers): void {
                foreach ($customers as $customer) {
                    try {
                        $this->customers->delete($customer);
                    } catch (BusinessRuleException) {
                        $this->customers->update($customer, ['is_active' => false]);
                    }
                }
            });
    }

    private function retireUnlistedVendors(): void
    {
        $keep = array_column(self::VENDORS, 'name');

        Vendor::query()
            ->whereNotIn('name', $keep)
            ->orderBy('id')
            ->chunkById(100, function ($vendors): void {
                foreach ($vendors as $vendor) {
                    try {
                        $this->vendors->delete($vendor);
                    } catch (BusinessRuleException) {
                        $this->vendors->update($vendor, ['is_active' => false]);
                    }
                }
            });
    }

    private function personEmail(string $name): string
    {
        $parts = preg_split('/\s+/', trim(Str::ascii($name))) ?: [];
        if (count($parts) < 2) {
            throw new RuntimeException("A first and last name are required to create a demo contact email for {$name}.");
        }

        $surname = [array_pop($parts)];
        $lastGivenNamePart = strtolower((string) end($parts));
        if (in_array($lastGivenNamePart, ['de', 'del', 'dela', 'da', 'van', 'von', 'san'], true)) {
            array_unshift($surname, array_pop($parts));
        }

        $email = Str::slug(implode('-', $surname), '.')
            .'.'.Str::slug(implode('-', $parts), '.')
            .'@gmail.com';

        return strtolower(str_replace('-', '.', $email));
    }

    private function assertContactEmailDoesNotCollideWithEmployeeAccount(string $email): void
    {
        if (User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
            throw new RuntimeException("Demo business-partner contact email {$email} collides with an employee login account.");
        }
    }

    /** @param array<string, mixed> $payload */
    private function syncCustomerRelations(Customer $customer, array $payload): void
    {
        $productIds = array_map('intval', $payload['portal_product_ids']);
        $customer->portalProducts()->sync($productIds);

        foreach ($payload['product_warranties'] as $warranty) {
            CustomerProductWarrantyOption::query()->firstOrCreate(
                [
                    'customer_id' => $customer->id,
                    'product_id' => $warranty['product_id'],
                    'label' => $warranty['label'],
                ],
                [
                    ...$warranty,
                    'customer_id' => $customer->id,
                ],
            );
        }
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
