<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use App\Common\Support\ProductionAssertions;
use Illuminate\Database\Seeder;

abstract class DemoSeeder extends Seeder implements DemoSeedLayer
{
    /** @return list<string> */
    public function modules(): array
    {
        return [];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return [];
    }

    final public function run(DemoSeedContext $context): void
    {
        ProductionAssertions::assertDemoSeedingAllowed();

        $this->seedDemoData($context);
    }

    abstract protected function seedDemoData(DemoSeedContext $context): void;
}
