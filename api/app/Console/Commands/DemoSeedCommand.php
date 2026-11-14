<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Common\DemoData\DemoSeedLayerException;
use App\Common\DemoData\DemoSeedLayerRegistry;
use App\Common\DemoData\DemoSeedOptionsParser;
use App\Common\DemoData\DemoSeedRunner;
use App\Common\Support\DemoSeedingGuard;
use Illuminate\Console\Command;
use Throwable;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed
        {--as-of= : Business as-of date in YYYY-MM-DD format (default: today in Asia/Manila)}
        {--scale=demo : Dataset scale: small, demo, or full}
        {--seed=20261114 : Fixed integer used for deterministic Faker data}
        {--only= : Run a layer key or module and its dependencies}
        {--no-history : Omit long historical transaction layers}';

    protected $description = 'Seed registered demo layers in controlled time with side effects suppressed.';

    public function handle(
        DemoSeedingGuard $guard,
        DemoSeedLayerRegistry $registry,
        DemoSeedRunner $runner,
        DemoSeedOptionsParser $optionsParser,
    ): int {
        try {
            $guard->assertAllowed();
            $options = $optionsParser->parse(
                is_string($this->option('as-of')) ? $this->option('as-of') : null,
                (string) $this->option('scale'),
                (string) $this->option('seed'),
                is_string($this->option('only')) ? $this->option('only') : null,
                (bool) $this->option('no-history'),
            );
            $registry->ordered($options->only);

            $results = $runner->run($options, function (array $event): void {
                if ($event['event'] === 'layer_started') {
                    $this->line("Starting demo layer: {$event['layer']}");

                    return;
                }

                $time = number_format(((int) $event['elapsed_ms']) / 1000, 2);
                $memory = number_format(((int) $event['peak_memory_bytes']) / 1048576, 1);
                $this->info("Finished {$event['layer']} in {$time}s; peak memory {$memory} MiB.");
            });
        } catch (DemoSeedLayerException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Demo seeding complete: '.count($results).' layer(s).');

        return self::SUCCESS;
    }
}
