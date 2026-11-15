<?php

declare(strict_types=1);

namespace App\Modules\B2B\Support;

final class SupplierShipmentDatePolicy
{
    public const MAX_ESTIMATED_ARRIVAL_DAYS = 90;

    public static function latestEstimatedArrivalDate(): string
    {
        return now()->addDays(self::MAX_ESTIMATED_ARRIVAL_DAYS)->toDateString();
    }
}
