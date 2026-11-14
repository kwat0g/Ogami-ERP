<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use Carbon\CarbonImmutable;
use Closure;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

final class DemoSeedContext
{
    private Generator $faker;

    private DemoRecordRegistry $records;

    public function __construct(
        public readonly DemoSeedOptions $options,
        ?DemoRecordRegistry $records = null,
        ?string $layerKey = null,
    ) {
        $this->records = $records ?? new DemoRecordRegistry;
        $this->faker = FakerFactory::create((string) config('demo.faker_locale', 'en_PH'));
        $this->faker->seed($this->derivedSeed($layerKey));
    }

    public function faker(): Generator
    {
        return $this->faker;
    }

    public function records(): DemoRecordRegistry
    {
        return $this->records;
    }

    public function forLayer(string $layerKey): self
    {
        return new self($this->options, $this->records, $layerKey);
    }

    /**
     * Run an action at a historical instant and restore both mutable and
     * immutable Carbon clocks even when the action throws.
     *
     * @template T
     *
     * @param  Closure(): T  $action
     * @return T
     */
    public function at(CarbonImmutable|string $instant, Closure $action): mixed
    {
        $time = is_string($instant)
            ? CarbonImmutable::parse($instant, 'Asia/Manila')
            : $instant->setTimezone('Asia/Manila');

        $previousMutable = Carbon::getTestNow();
        $previousImmutable = CarbonImmutable::getTestNow();

        try {
            Carbon::setTestNow($time);
            CarbonImmutable::setTestNow($time);

            return $action();
        } finally {
            Carbon::setTestNow($previousMutable);
            CarbonImmutable::setTestNow($previousImmutable);
        }
    }

    /**
     * Perform a service action as the authorized user, restoring the caller
     * identity after the callback.
     *
     * @template T
     *
     * @param  Closure(): T  $action
     * @return T
     */
    public function asUser(Authenticatable $user, Closure $action): mixed
    {
        $guard = Auth::guard();
        $previous = $guard->user();

        Auth::setUser($user);

        try {
            return $action();
        } finally {
            if ($previous instanceof Authenticatable) {
                Auth::setUser($previous);
            } else {
                $guard->forgetUser();
            }
        }
    }

    /**
     * Run historical domain actions without retaining their notification rows.
     * Use live notification delivery for the in-flight scenario layer.
     *
     * @template T
     *
     * @param  Closure(): T  $action
     * @return T
     */
    public function withoutNotifications(Closure $action): mixed
    {
        $previous = app(NotificationDispatcher::class);
        Notification::fake();

        try {
            return $action();
        } finally {
            Notification::swap($previous);
        }
    }

    private function derivedSeed(?string $layerKey): int
    {
        $material = (string) $this->options->seed.':'.($layerKey ?? 'root');
        $bytes = hash('sha256', $material, true);
        $unpacked = unpack('Nseed', substr($bytes, 0, 4));

        return (int) ($unpacked['seed'] ?? $this->options->seed);
    }
}
