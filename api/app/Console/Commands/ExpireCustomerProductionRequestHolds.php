<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\CRM\Services\CustomerProductionRequestStockHoldService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireCustomerProductionRequestHolds extends Command
{
    protected $signature = 'customer-production-requests:expire-holds {--limit=200}';
    protected $description = 'Release customer production request stock holds after their 48-hour window';

    public function handle(CustomerProductionRequestStockHoldService $holds): int
    {
        try {
            $count = $holds->expireDue(max(1, min((int) $this->option('limit'), 1000)));
            $this->info("Expired {$count} customer production request(s).");
            return self::SUCCESS;
        } catch (\Throwable $error) {
            Log::error('customer-production-requests:expire-holds failed', ['error' => $error->getMessage()]);
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
