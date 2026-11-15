<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Payroll\Enums\ContributionAgency;
use App\Modules\Payroll\Services\GovernmentContributionTableImportService;
use Illuminate\Console\Command;

class ImportGovContributionTable extends Command
{
    protected $signature = 'gov:import-contributions
                            {agency : sss|philhealth|pagibig|bir}
                            {path : Filesystem path to the CSV}
                            {--source-title= : Human-readable citation for the schedule}
                            {--source-url= : HTTPS URL of the official source}';

    protected $description = 'T1.8 — Import a government contribution table CSV.';

    public function handle(GovernmentContributionTableImportService $service): int
    {
        $agencyValue = (string) $this->argument('agency');
        $agency = ContributionAgency::tryFrom($agencyValue);
        if (! $agency) {
            $this->error("Unknown agency: {$agencyValue}. Use one of: sss, philhealth, pagibig, bir.");

            return self::FAILURE;
        }

        try {
            $r = $service->importFromPath(
                $agency,
                (string) $this->argument('path'),
                (string) $this->option('source-title'),
                (string) $this->option('source-url'),
                null,
                basename((string) $this->argument('path')),
            );
        } catch (\Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s import: total=%d imported=%d skipped=%d version=%s status=%s',
            $agency->value,
            $r['total'],
            $r['imported'],
            $r['skipped'],
            $r['version_number'] ?? 'none',
            $r['status'] ?? 'rejected',
        ));
        if (! empty($r['errors'])) {
            $this->warn(sprintf('Errors: %d', count($r['errors'])));
            foreach (array_slice($r['errors'], 0, 10) as $err) {
                $this->line("  row {$err['row']}: {$err['message']}");
            }
        }

        return self::SUCCESS;
    }
}
