<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Enums;

enum GovernmentScheduleVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Superseded = 'superseded';
    case LegacyUnverified = 'legacy_unverified';
}
