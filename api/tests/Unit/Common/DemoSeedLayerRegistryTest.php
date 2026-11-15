<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedLayer;
use App\Common\DemoData\DemoSeedLayerRegistry;
use RuntimeException;
use Tests\TestCase;

class DemoSeedLayerRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.layers' => [
            InventoryFixtureLayer::class,
            CatalogFixtureLayer::class,
            BaselineFixtureLayer::class,
        ]]);
    }

    public function test_it_orders_dependencies_and_includes_them_for_module_filter(): void
    {
        $layers = app(DemoSeedLayerRegistry::class)->ordered('inventory');

        $this->assertSame(
            ['baseline', 'catalog', 'inventory'],
            array_map(static fn (DemoSeedLayer $layer): string => $layer->key(), $layers),
        );
    }

    public function test_it_fails_clearly_when_no_layers_are_registered(): void
    {
        config(['demo.layers' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No demo seed layers are registered');

        app(DemoSeedLayerRegistry::class)->ordered();
    }

    public function test_it_rejects_unknown_layer_or_module_filter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not match a registered layer or module');

        app(DemoSeedLayerRegistry::class)->ordered('payroll');
    }
}

final class BaselineFixtureLayer implements DemoSeedLayer
{
    public function key(): string
    {
        return 'baseline';
    }

    public function modules(): array
    {
        return ['admin'];
    }

    public function dependencies(): array
    {
        return [];
    }

    public function run(DemoSeedContext $context): void {}
}

final class CatalogFixtureLayer implements DemoSeedLayer
{
    public function key(): string
    {
        return 'catalog';
    }

    public function modules(): array
    {
        return ['inventory'];
    }

    public function dependencies(): array
    {
        return ['baseline'];
    }

    public function run(DemoSeedContext $context): void {}
}

final class InventoryFixtureLayer implements DemoSeedLayer
{
    public function key(): string
    {
        return 'inventory';
    }

    public function modules(): array
    {
        return ['inventory', 'mrp'];
    }

    public function dependencies(): array
    {
        return ['catalog'];
    }

    public function run(DemoSeedContext $context): void {}
}
