<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use App\Common\Services\NotificationService;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\B2B\Models\SupplierPortalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Collection;

/** Suppresses persisted and queued notifications during historical demo actions. */
final class DemoNotificationService extends NotificationService
{
    public function sendPortalInApp(iterable $recipients, string $type, array $data, ?string $dedupeKey = null): void {}

    public function stagePortalInboxChange(
        CustomerPortalUser|SupplierPortalUser $user,
        string $action,
        ?string $notificationId = null,
    ): void {}

    public function send(
        User|Collection|array $recipients,
        string $type,
        array $data,
        ?string $dedupeKey = null,
        array $recipientReasons = [],
    ): void {}

    public function sendInApp(
        User|Collection|array $recipients,
        string $type,
        array $data,
        ?string $dedupeKey = null,
        array $recipientReasons = [],
    ): void {}

    public function sendForSubject(
        Model $subject,
        User|Collection|array $recipients,
        string $type,
        array $data,
        ?string $dedupeKey = null,
        array $recipientReasons = [],
        bool $emailEnabled = true,
    ): void {}

    public function sendLaravelNotification(User|Collection|array $recipients, LaravelNotification $notification): void {}

    public function sendDssRecommendationInApp(User $recipient, array $data, string $eventIdentity): bool
    {
        return false;
    }
}
