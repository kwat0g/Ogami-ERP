<?php

declare(strict_types=1);

namespace App\Modules\CRM\Enums;

enum CustomerOrderLimitIncreaseRequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Awaiting Sales review',
            self::Approved => 'Approved',
            self::Rejected => 'Declined',
            self::Cancelled => 'Cancelled',
        };
    }
}
