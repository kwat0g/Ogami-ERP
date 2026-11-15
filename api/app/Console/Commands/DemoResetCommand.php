<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Common\DemoData\DemoResetGate;
use App\Common\DemoData\DemoSeedLayerRegistry;
use App\Common\DemoData\DemoSeedOptionsParser;
use App\Common\DemoData\DemoSeedRunner;
use App\Common\DemoData\DemoSideEffectScope;
use App\Common\Support\DemoSeedingGuard;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset
        {--as-of= : Business as-of date in YYYY-MM-DD format (default: today in Asia/Manila)}
        {--scale=demo : Dataset scale: small, demo, or full}
        {--seed=20261114 : Fixed integer used for deterministic Faker data}
        {--no-history : Omit long historical transaction layers}';

    protected $description = 'Rebuild the allowlisted demo database from migrations, baseline data and registered demo layers.';

    public function handle(
        DemoSeedingGuard $guard,
        DemoSeedLayerRegistry $registry,
        DemoResetGate $resetGate,
        DemoSeedOptionsParser $optionsParser,
        DemoSeedRunner $runner,
        DemoSideEffectScope $sideEffects,
    ): int {
        try {
            $guard->assertAllowed();
            $options = $optionsParser->parse(
                is_string($this->option('as-of')) ? $this->option('as-of') : null,
                (string) $this->option('scale'),
                (string) $this->option('seed'),
                noHistory: (bool) $this->option('no-history'),
            );
            // Fail before migrate:fresh if the approved plan has not registered
            // its data layers yet.
            $registry->ordered();
            $resetGate->assertReady();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $previousDemo = config('app.seed_demo_data');
        $previousReference = config('app.seed_reference_data');

        try {
            $results = $sideEffects->run(function () use ($runner, $options, $previousDemo, $previousReference): array {
                config([
                    'app.seed_demo_data' => false,
                    'app.seed_reference_data' => false,
                ]);

                try {
                    $this->info('Resetting the allowlisted demo database and applying migrations.');
                    if ($this->call('migrate:fresh', ['--force' => true]) !== self::SUCCESS) {
                        throw new RuntimeException('migrate:fresh failed during demo reset.');
                    }
                    if ($this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]) !== self::SUCCESS) {
                        throw new RuntimeException('System baseline seeding failed during demo reset.');
                    }

                    return $runner->run($options, function (array $event): void {
                        if ($event['event'] === 'layer_started') {
                            $this->line("Starting demo layer: {$event['layer']}");
                        } else {
                            $this->info("Finished {$event['layer']} in ".number_format(((int) $event['elapsed_ms']) / 1000, 2).'s.');
                        }
                    });
                } finally {
                    config([
                        'app.seed_demo_data' => $previousDemo,
                        'app.seed_reference_data' => $previousReference,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Demo reset complete: '.count($results).' layer(s).');

        return self::SUCCESS;
    }
}
