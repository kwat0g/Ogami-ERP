<?php

declare(strict_types=1);

namespace App\Modules\CRM\Enums;

enum CustomerProductionRequestStatus: string
{
    case Requested = 'requested';
    case Planning = 'planning';
    case DateOffered = 'date_offered';
    case Accepted = 'accepted';
    case InProduction = 'in_production';
    case ReadyToOrder = 'ready_to_order';
    case Converted = 'converted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Planning => 'Planning',
            self::DateOffered => 'Date offered',
            self::Accepted => 'Accepted',
            self::InProduction => 'In production',
            self::ReadyToOrder => 'Ready to order',
            self::Converted => 'Converted to order',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Converted, self::Declined, self::Cancelled, self::Expired], true);
    }
}
