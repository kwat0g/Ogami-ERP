<?php

declare(strict_types=1);

namespace App\Modules\MRP\Events;

use App\Common\Events\BroadcastEventEnvelope;
use App\Common\Events\Concerns\BroadcastsOnDedicatedQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** Content-free plan refresh signal; recipients refetch through their own API scope. */
final class MrpPlanChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use BroadcastsOnDedicatedQueue;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $eventId;

    public string $timestamp;

    public function __construct(
        public string $planHashId,
        public ?string $salesOrderHashId,
        public string $action,
        public string $version,
        public ?string $actorHashId = null,
        ?string $eventId = null,
        ?string $timestamp = null,
        public ?string $sourceEntityType = null,
        public ?string $sourceEntityHashId = null,
    ) {
        $this->eventId = $eventId ?? (string) Str::uuid();
        $this->timestamp = $timestamp ?? now()->toISOString();
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("mrp.plan.{$this->planHashId}"),
            new PrivateChannel('entity.mrp_plan.visible'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chain.refresh';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $sourceHashId = $this->sourceEntityHashId ?? $this->salesOrderHashId;
        $sourceType = $this->sourceEntityType ?? 'sales_order';

        return BroadcastEventEnvelope::refresh(
            $this->eventId,
            'mrp_plan',
            $this->planHashId,
            $this->action,
            $this->version,
            $this->actorHashId,
            $this->timestamp,
            $sourceHashId ? [['entity_type' => $sourceType, 'hash_id' => $sourceHashId]] : [],
        ) + ['hash_id' => $this->planHashId];
    }
}
