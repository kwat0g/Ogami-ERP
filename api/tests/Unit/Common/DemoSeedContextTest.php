<?php

declare(strict_types=1);

namespace Tests\Unit\Common;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeedOptions;
use App\Modules\Auth\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class DemoSeedContextTest extends TestCase
{
    private function context(int $seed = 713): DemoSeedContext
    {
        return new DemoSeedContext(new DemoSeedOptions(
            CarbonImmutable::parse('2026-11-14', 'Asia/Manila'),
            'demo',
            $seed,
        ));
    }

    public function test_faker_output_is_stable_for_a_fixed_seed(): void
    {
        $first = $this->context()->faker()->name();
        $second = $this->context()->faker()->name();

        $this->assertSame($first, $second);
    }

    public function test_controlled_time_is_restored_even_when_the_action_throws(): void
    {
        Carbon::setTestNow('2026-11-01 09:00:00');
        $context = $this->context();

        try {
            try {
                $context->at('2025-05-14 14:30:00', function (): void {
                    $this->assertSame('2025-05-14 14:30:00', now()->format('Y-m-d H:i:s'));
                    $this->assertSame('2025-05-14 14:30:00', CarbonImmutable::now()->format('Y-m-d H:i:s'));
                    throw new \RuntimeException('expected');
                });
            } catch (\RuntimeException $exception) {
                $this->assertSame('expected', $exception->getMessage());
            }

            $this->assertSame('2026-11-01 09:00:00', now()->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-01 09:00:00', CarbonImmutable::now()->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    public function test_as_user_restores_the_previous_authenticated_user(): void
    {
        Auth::shouldUse('web');
        $previous = new User;
        $previous->setAttribute('id', 41);
        Auth::setUser($previous);

        $acting = new User;
        $acting->setAttribute('id', 73);

        $this->assertSame(73, $this->context()->asUser($acting, fn (): int => (int) Auth::id()));
        $this->assertSame(41, (int) Auth::id());
    }

    public function test_registry_keeps_lightweight_model_references(): void
    {
        $user = new User;
        $user->setAttribute('id', 25);

        $context = $this->context();
        $context->records()->remember('actors.hr', $user);

        $this->assertSame(25, $context->records()->id('actors.hr'));
        $this->assertSame(User::class, $context->records()->modelClass('actors.hr'));
    }

    public function test_historical_actions_can_suppress_notifications_and_restore_the_dispatcher(): void
    {
        $context = $this->context();
        $receiver = new NotificationFixtureReceiver;
        $notification = new NotificationFixtureMessage;
        $previousDispatcher = app(Dispatcher::class);

        $context->withoutNotifications(function () use ($receiver, $notification): void {
            NotificationFacade::send($receiver, $notification);
            NotificationFacade::assertSentTo($receiver, NotificationFixtureMessage::class);
        });

        $this->assertSame($previousDispatcher, app(Dispatcher::class));
        $this->assertSame($previousDispatcher, NotificationFacade::getFacadeRoot());
    }
}

final class NotificationFixtureReceiver
{
    use Notifiable;

    public function getKey(): string
    {
        return 'demo-notifiable';
    }
}

final class NotificationFixtureMessage extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }
}
