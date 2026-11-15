<?php

declare(strict_types=1);

namespace App\Common\DemoData;

interface DemoSeedLayer
{
    public function key(): string;

    /** @return list<string> */
    public function modules(): array;

    /** @return list<string> */
    public function dependencies(): array;

    public function run(DemoSeedContext $context): void;
}
