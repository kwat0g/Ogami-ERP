<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use App\Common\Support\DemoSeedingGuard;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DemoSeedRunner
{
    public function __construct(
        private readonly DemoSeedingGuard $guard,
        private readonly DemoSeedLayerRegistry $layers,
        private readonly DemoSideEffectScope $sideEffects,
    ) {}

    /**
     * @param  (callable(array{event: string, layer: string, elapsed_ms?: int, peak_memory_bytes?: int}): void)|null  $progress
     * @return list<array{layer: string, elapsed_ms: int, peak_memory_bytes: int}>
     */
    public function run(DemoSeedOptions $options, ?callable $progress = null): array
    {
        $this->guard->assertAllowed();
        $layers = $this->layers->ordered($options->only);
        $context = new DemoSeedContext($options);
        $connection = DB::connection();
        $wasLogging = $connection->logging();
        $connection->disableQueryLog();

        try {
            return $this->sideEffects->run(function () use ($layers, $context, $progress): array {
                $results = [];

                foreach ($layers as $layer) {
                    $key = $layer->key();
                    $started = hrtime(true);
                    if ($progress !== null) {
                        $progress(['event' => 'layer_started', 'layer' => $key]);
                    }

                    try {
                        $layer->run($context->forLayer($key));
                    } catch (Throwable $exception) {
                        throw new DemoSeedLayerException($key, $exception);
                    }

                    $result = [
                        'layer' => $key,
                        'elapsed_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                        'peak_memory_bytes' => memory_get_peak_usage(true),
                    ];
                    $results[] = $result;
                    if ($progress !== null) {
                        $progress([
                            'event' => 'layer_finished',
                            'layer' => $key,
                            'elapsed_ms' => $result['elapsed_ms'],
                            'peak_memory_bytes' => $result['peak_memory_bytes'],
                        ]);
                    }
                    gc_collect_cycles();
                }

                return $results;
            });
        } finally {
            if ($wasLogging) {
                $connection->enableQueryLog();
            }
        }
    }
}
