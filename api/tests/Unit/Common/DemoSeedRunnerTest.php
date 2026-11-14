<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedLayer;
use App\Common\DemoData\DemoSeedLayerException;
use App\Common\DemoData\DemoSeedOptions;
use App\Common\DemoData\DemoSeedRunner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class DemoSeedRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RunnerFixtureLayer::$events = [];
        RunnerFixtureLayer::$transportClasses = [];
        RunnerFixtureLayer::$namedDrivers = [];
        RunnerFixtureLayer::$managerClasses = [];
        RunnerFixtureLayer::$queryLogging = [];
        RunnerFixtureLayer::$fail = false;
        config([
            'demo.seeding.enabled' => true,
            'demo.seeding.database_allowlist' => ['ogami_test'],
            'demo.layers' => [RunnerFixtureLayer::class],
            'mail.default' => 'smtp',
            'broadcasting.default' => 'reverb',
            'queue.default' => 'redis',
            'cache.default' => 'redis',
            'mail.mailers.smtp.transport' => 'smtp',
            'broadcasting.connections.reverb.driver' => 'reverb',
            'queue.connections.redis.driver' => 'redis',
            'cache.stores.redis.driver' => 'redis',
            'broadcasting.connections.reverb.key' => 'demo-key',
            'broadcasting.connections.reverb.secret' => 'demo-secret',
            'broadcasting.connections.reverb.app_id' => 'demo-app',
        ]);
    }

    public function test_it_runs_layers_with_suppressed_external_effects_and_restores_configuration(): void
    {
        // Resolve the configured mailer before entering the suppression scope.
        // The runner must discard this cached transport before a layer sends.
        $this->assertSame('EsmtpTransport', class_basename(Mail::mailer()->getSymfonyTransport()));
        Mail::mailer('smtp');
        Broadcast::connection('reverb');
        app(BroadcasterContract::class);
        Queue::connection('redis');
        Cache::store('redis');
        app('cache.store');
        $connection = DB::connection();
        $connection->enableQueryLog();

        $runner = app(DemoSeedRunner::class);
        $progress = [];
        $results = $runner->run(
            new DemoSeedOptions(CarbonImmutable::parse('2026-11-14', 'Asia/Manila'), 'small', 17, noHistory: true),
            static function (array $event) use (&$progress): void {
                $progress[] = $event;
            },
        );

        $this->assertSame('baseline', $results[0]['layer']);
        $this->assertSame(['layer_started', 'layer_finished'], array_column($progress, 'event'));
        $this->assertSame([['array', 'log', 'sync', 'array', true]], RunnerFixtureLayer::$events);
        $this->assertSame(['ArrayTransport', 'ArrayTransport'], RunnerFixtureLayer::$transportClasses);
        $this->assertSame(['array', 'log', 'sync', 'array'], RunnerFixtureLayer::$namedDrivers);
        $this->assertSame(['LogBroadcaster', 'LogBroadcaster', 'SyncQueue', 'ArrayStore', 'ArrayStore'], RunnerFixtureLayer::$managerClasses);
        $this->assertSame([false], RunnerFixtureLayer::$queryLogging);
        $this->assertTrue($connection->logging());
        $connection->disableQueryLog();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('reverb', config('broadcasting.default'));
        $this->assertSame('redis', config('queue.default'));
        $this->assertSame('redis', config('cache.default'));
        $this->assertSame('smtp', config('mail.mailers.smtp.transport'));
        $this->assertSame('reverb', config('broadcasting.connections.reverb.driver'));
        $this->assertSame('redis', config('queue.connections.redis.driver'));
        $this->assertSame('redis', config('cache.stores.redis.driver'));
    }

    public function test_it_names_the_failing_layer_and_restores_side_effect_configuration(): void
    {
        RunnerFixtureLayer::$fail = true;

        try {
            app(DemoSeedRunner::class)->run(
                new DemoSeedOptions(CarbonImmutable::parse('2026-11-14', 'Asia/Manila'), 'demo', 17),
            );
            $this->fail('A failed layer should stop the pipeline.');
        } catch (DemoSeedLayerException $exception) {
            $this->assertSame('baseline', $exception->layer);
            $this->assertSame('fixture error', $exception->getPrevious()->getMessage());
        }

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('reverb', config('broadcasting.default'));
        $this->assertSame('redis', config('queue.default'));
        $this->assertSame('redis', config('cache.default'));
    }
}

final class RunnerFixtureLayer implements DemoSeedLayer
{
    /** @var list<array{string, string, string, string, bool}> */
    public static array $events = [];

    /** @var list<string> */
    public static array $transportClasses = [];

    /** @var list<string> */
    public static array $namedDrivers = [];

    /** @var list<string> */
    public static array $managerClasses = [];

    /** @var list<bool> */
    public static array $queryLogging = [];

    public static bool $fail = false;

    public function key(): string
    {
        return 'baseline';
    }

    public function modules(): array
    {
        return ['system'];
    }

    public function dependencies(): array
    {
        return [];
    }

    public function run(DemoSeedContext $context): void
    {
        self::$transportClasses[] = class_basename(Mail::mailer()->getSymfonyTransport());
        self::$transportClasses[] = class_basename(Mail::mailer('smtp')->getSymfonyTransport());
        self::$namedDrivers = [
            (string) config('mail.mailers.smtp.transport'),
            (string) config('broadcasting.connections.reverb.driver'),
            (string) config('queue.connections.redis.driver'),
            (string) config('cache.stores.redis.driver'),
        ];
        self::$managerClasses = [
            class_basename(Broadcast::connection('reverb')),
            class_basename(app(BroadcasterContract::class)),
            class_basename(Queue::connection('redis')),
            class_basename(Cache::store('redis')->getStore()),
            class_basename(app('cache.store')->getStore()),
        ];
        self::$queryLogging[] = DB::connection()->logging();
        self::$events[] = [
            (string) Config::get('mail.default'),
            (string) Config::get('broadcasting.default'),
            (string) Config::get('queue.default'),
            (string) Config::get('cache.default'),
            $context->options->noHistory,
        ];

        if (self::$fail) {
            throw new RuntimeException('fixture error');
        }
    }
}
