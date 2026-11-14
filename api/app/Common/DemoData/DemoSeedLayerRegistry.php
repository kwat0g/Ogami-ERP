<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use RuntimeException;

final class DemoSeedLayerRegistry
{
    /**
     * Return registered layers in dependency order. A module-only selection
     * includes the layers it depends on so it is independently runnable.
     *
     * @return list<DemoSeedLayer>
     */
    public function ordered(?string $only = null): array
    {
        $configured = config('demo.layers', []);
        if (! is_array($configured) || $configured === []) {
            throw new RuntimeException('No demo seed layers are registered yet; no data was changed.');
        }

        $layers = [];
        foreach ($configured as $definition) {
            $layer = is_string($definition) ? app()->make($definition) : $definition;
            if (! $layer instanceof DemoSeedLayer) {
                throw new RuntimeException('Every demo.layers entry must resolve to a DemoSeedLayer.');
            }

            $key = trim($layer->key());
            if ($key === '' || isset($layers[$key])) {
                throw new RuntimeException("Demo seed layer key is empty or duplicated: {$key}");
            }

            $layers[$key] = $layer;
        }

        $selected = $this->selectKeys($layers, $only);
        $ordered = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $key) use (&$visit, &$ordered, &$visiting, &$visited, $layers): void {
            if (isset($visited[$key])) {
                return;
            }

            if (isset($visiting[$key])) {
                throw new RuntimeException("Demo seed layer dependency cycle contains {$key}.");
            }

            $layer = $layers[$key] ?? throw new RuntimeException("Demo seed layer dependency {$key} is not registered.");
            $visiting[$key] = true;

            foreach ($layer->dependencies() as $dependency) {
                if (! is_string($dependency) || trim($dependency) === '') {
                    throw new RuntimeException("Demo seed layer {$key} has an invalid dependency.");
                }

                $visit($dependency);
            }

            unset($visiting[$key]);
            $visited[$key] = true;
            $ordered[] = $layer;
        };

        foreach ($selected as $key) {
            $visit($key);
        }

        return $ordered;
    }

    /**
     * @param  array<string, DemoSeedLayer>  $layers
     * @return list<string>
     */
    private function selectKeys(array $layers, ?string $only): array
    {
        if ($only === null || trim($only) === '') {
            return array_keys($layers);
        }

        $target = trim($only);
        if (isset($layers[$target])) {
            return [$target];
        }

        $matches = [];
        foreach ($layers as $key => $layer) {
            foreach ($layer->modules() as $module) {
                if (is_string($module) && strcasecmp(trim($module), $target) === 0) {
                    $matches[] = $key;
                    break;
                }
            }
        }

        if ($matches === []) {
            throw new RuntimeException("Demo --only={$target} does not match a registered layer or module.");
        }

        return $matches;
    }
}
