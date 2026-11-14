<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use Closure;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Support\Facades\Queue;

/** Keep demo seeding away from external mail, broadcast and queue services. */
final class DemoSideEffectScope
{
    private static int $activeDepth = 0;

    public static function isActive(): bool
    {
        return self::$activeDepth > 0;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $action
     * @return T
     */
    public function run(Closure $action): mixed
    {
        $configKeys = [
            'mail.default' => 'array',
            'mail.driver' => 'array',
            'mail.mailers' => $this->fakeDrivers((array) config('mail.mailers', []), 'transport', 'array'),
            'broadcasting.default' => 'log',
            'broadcasting.connections' => $this->fakeDrivers((array) config('broadcasting.connections', []), 'driver', 'log'),
            'queue.default' => 'sync',
            'queue.connections' => $this->fakeDrivers((array) config('queue.connections', []), 'driver', 'sync'),
            'cache.default' => 'array',
            'cache.stores' => $this->fakeDrivers((array) config('cache.stores', []), 'driver', 'array'),
        ];
        $previous = [];
        foreach ($configKeys as $key => $value) {
            $previous[$key] = config($key);
        }
        config($configKeys);
        self::$activeDepth++;

        try {
            $this->forgetResolvedDrivers($previous);

            return $action();
        } finally {
            try {
                config($previous);
                $this->forgetResolvedDrivers($previous);
            } finally {
                self::$activeDepth--;
            }
        }
    }

    /** @param array<string, mixed> $drivers
     * @return array<string, array<string, string|bool>>
     */
    private function fakeDrivers(array $drivers, string $driverKey, string $driver): array
    {
        $fakes = [];
        foreach ($drivers as $name => $settings) {
            if (is_string($name)) {
                $fakes[$name] = [$driverKey => $driver];
            }
        }

        $safeName = match ($driverKey) {
            'transport' => 'array',
            default => $driver,
        };
        $fakes[$safeName] = [$driverKey => $driver];

        return $fakes;
    }

    /** @param array<string, mixed> $previous */
    private function forgetResolvedDrivers(array $previous): void
    {
        app('mail.manager')->forgetMailers();
        app(BroadcastManager::class)->forgetDrivers();
        app()->forgetInstance(BroadcasterContract::class);
        app()->forgetInstance('queue.connection');
        app()->forgetInstance('queue');
        Queue::clearResolvedInstance('queue');
        app()->forgetInstance('cache.store');
        app()->forgetInstance('cache.psr6');

        $cacheStores = array_keys((array) ($previous['cache.stores'] ?? []));
        app('cache')->forgetDriver(array_merge($cacheStores, ['array']));
    }
}
