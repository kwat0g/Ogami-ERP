<?php

declare(strict_types=1);

namespace App\Modules\CRM\Listeners;

use App\Modules\CRM\Services\CustomerProductionRequestStockHoldService;
use App\Modules\Quality\Events\InspectionPassed;
use App\Modules\Quality\Events\OutgoingQuantityReleased;
use Illuminate\Contracts\Queue\ShouldQueue;

final class MarkCustomerProductionRequestReady implements ShouldQueue
{
    public function handle(InspectionPassed|OutgoingQuantityReleased $event): void
    {
        app(CustomerProductionRequestStockHoldService::class)->handleQualityRelease($event);
    }
}
