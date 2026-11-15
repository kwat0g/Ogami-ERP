<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Common\Exceptions\BusinessRuleException;
use App\Common\Support\CacheGeneration;
use App\Modules\Payroll\Enums\ContributionAgency;
use App\Modules\Payroll\Enums\GovernmentScheduleVersionStatus;
use App\Modules\Payroll\Models\GovernmentContributionTable;
use App\Modules\Payroll\Models\GovernmentScheduleVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GovernmentContributionTableImportService
{
    private const CACHE_VERSION_TTL = 604800;

    private const REQUIRED_HEADER = ['bracket_min', 'bracket_max', 'ee_amount', 'er_amount', 'effective_date'];

    /**
     * Validate the complete file before changing a single row. A statutory
     * schedule is one versioned set: partially importing it leaves payroll
     * with a mixture of old and new brackets that can produce wrong statutory
     * deductions while still looking active in the admin screen. Imports are
     * immutable drafts; publication is a separate reviewed operation.
     *
     * @return array{
     *   total: int, imported: int, updated: int, skipped: int,
     *   deactivated_prior: int, version_id: int|null, version_number: int|null,
     *   status: string|null, effective_date: string|null, source_file_sha256: string|null,
     *   idempotent: bool,
     *   errors: array<int, array{row:int, message:string}>
     * }
     */
    public function importFromPath(
        ContributionAgency $agency,
        string $path,
        string $sourceTitle,
        string $sourceUrl,
        ?int $importedBy = null,
        ?string $sourceFileName = null,
    ): array {
        if (! is_readable($path)) {
            throw new BusinessRuleException("CSV not readable at {$path}.");
        }
        $sourceTitle = trim($sourceTitle);
        $sourceUrl = trim($sourceUrl);
        if ($sourceTitle === '' || mb_strlen($sourceTitle) > 200) {
            throw new BusinessRuleException('Source title is required and must be 200 characters or fewer.');
        }
        if (! filter_var($sourceUrl, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($sourceUrl), 'https://')) {
            throw new BusinessRuleException('Source URL must be a valid HTTPS link.');
        }
        $sourceHash = hash_file('sha256', $path);
        if (! is_string($sourceHash)) {
            throw new BusinessRuleException('Could not calculate the uploaded source file hash.');
        }

        $stream = fopen($path, 'r');
        if ($stream === false) {
            throw new RuntimeException("Could not open {$path}.");
        }

        try {
            $header = fgetcsv($stream);
            if (! $header) {
                return $this->blankResult(['row' => 0, 'message' => 'Empty CSV.']);
            }

            $header = array_map(static fn ($value): string => strtolower(trim((string) $value)), $header);
            $missing = array_diff(self::REQUIRED_HEADER, $header);
            if ($agency === ContributionAgency::Sss && ! in_array('ec_amount', $header, true)) {
                $missing[] = 'ec_amount';
            }
            if ($missing) {
                return $this->blankResult([
                    'row' => 1,
                    'message' => 'Missing column(s): '.implode(', ', $missing),
                ]);
            }
            $duplicates = array_keys(array_filter(array_count_values($header), static fn (int $count): bool => $count > 1));
            if ($duplicates !== []) {
                return $this->blankResult([
                    'row' => 1,
                    'message' => 'Duplicate column(s): '.implode(', ', $duplicates),
                ]);
            }
            $idx = array_flip($header);

            $rows = [];
            $errors = [];
            $rowNum = 1;
            $total = 0;

            while (($row = fgetcsv($stream)) !== false) {
                $rowNum++;
                if (count(array_filter($row, static fn ($value): bool => $value !== null && trim((string) $value) !== '')) === 0) {
                    continue;
                }
                $total++;

                try {
                    $rows[] = [
                        'row' => $rowNum,
                            'payload' => $this->normalizeRow($row, $idx, $agency),
                    ];
                } catch (Throwable $e) {
                    $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
                }
            }

            if ($total === 0) {
                return $this->blankResult(['row' => 1, 'message' => 'CSV contains no data rows.']);
            }

            $this->validateSchedule($rows, $agency, $errors);

            // The import is atomic from the operator's point of view. Every
            // row is reported as skipped when validation fails because none of
            // the file was applied, including otherwise valid rows.
            if ($errors !== []) {
                return [
                    'total' => $total,
                    'imported' => 0,
                    'updated' => 0,
                    'skipped' => $total,
                    'deactivated_prior' => 0,
                    'version_id' => null,
                    'version_number' => null,
                    'status' => null,
                    'effective_date' => null,
                    'source_file_sha256' => null,
                    'idempotent' => false,
                    'errors' => $errors,
                ];
            }

            $effectiveDate = $rows[0]['payload']['effective_date'];
            $result = DB::transaction(function () use (
                $agency,
                $rows,
                $effectiveDate,
                $sourceTitle,
                $sourceUrl,
                $sourceHash,
                $sourceFileName,
                $importedBy,
                $path,
            ): array {
                // Lock this agency's version range before allocating the next
                // sequence number. The unique key remains the final race guard.
                GovernmentScheduleVersion::query()
                    ->where('agency', $agency->value)
                    ->whereDate('effective_date', $effectiveDate)
                    ->lockForUpdate()
                    ->get(['id']);

                $sameFile = GovernmentScheduleVersion::query()
                    ->where('agency', $agency->value)
                    ->whereDate('effective_date', $effectiveDate)
                    ->where('source_file_sha256', $sourceHash)
                    ->first();
                if ($sameFile) {
                    if ($sameFile->source_title !== $sourceTitle || $sameFile->source_url !== $sourceUrl) {
                        throw new BusinessRuleException('This exact source file was already imported with different source metadata. Review the existing version instead of creating a duplicate.');
                    }

                    return [
                        'imported' => 0,
                        'updated' => 0,
                        'version' => $sameFile,
                        'idempotent' => true,
                    ];
                }

                $versionNumber = ((int) GovernmentScheduleVersion::query()
                    ->where('agency', $agency->value)
                    ->whereDate('effective_date', $effectiveDate)
                    ->max('version_number')) + 1;

                $version = GovernmentScheduleVersion::query()->create([
                    'agency' => $agency->value,
                    'effective_date' => $effectiveDate,
                    'version_number' => $versionNumber,
                    'status' => GovernmentScheduleVersionStatus::Draft,
                    'source_title' => $sourceTitle,
                    'source_url' => $sourceUrl,
                    'source_file_name' => $sourceFileName ? mb_substr($sourceFileName, 0, 255) : basename($path),
                    'source_file_sha256' => $sourceHash,
                    'row_count' => count($rows),
                    'imported_by' => $importedBy,
                    'imported_at' => now(),
                ]);

                foreach ($rows as $entry) {
                    $payload = $entry['payload'];
                    GovernmentContributionTable::query()->create([
                        ...$payload,
                        'agency' => $agency->value,
                        'schedule_version_id' => $version->id,
                        'is_active' => true,
                    ]);
                }

                DB::afterCommit(function () use ($agency): void {
                    try {
                        $this->bustCache($agency);
                    } catch (Throwable $exception) {
                        // Keep the later durable payroll-table refresh callback
                        // runnable after this already-committed import.
                        Log::warning('Government contribution cache invalidation failed after commit.', [
                            'agency' => $agency->value,
                            'exception' => $exception::class,
                        ]);
                    }
                });
                app(GovernmentTableRealtimeService::class)->stage(
                    'imported',
                    (string) $version->version_number,
                );

                return [
                    'imported' => count($rows),
                    'updated' => 0,
                    'version' => $version,
                    'idempotent' => false,
                ];
            });

            return [
                'total' => $total,
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'skipped' => 0,
                'deactivated_prior' => 0,
                'version_id' => $result['version']->id,
                'version_number' => $result['version']->version_number,
                'status' => $result['version']->status->value,
                'effective_date' => $result['version']->effective_date->toDateString(),
                'source_file_sha256' => $result['version']->source_file_sha256,
                'idempotent' => $result['idempotent'],
                'errors' => [],
            ];
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $idx
     * @return array{bracket_min:string, bracket_max:string, ee_amount:string, er_amount:string, ec_amount:string, effective_date:string}
     */
    private function normalizeRow(array $row, array $idx, ContributionAgency $agency): array
    {
        foreach (self::REQUIRED_HEADER as $field) {
            if (! array_key_exists($idx[$field], $row)) {
                throw new BusinessRuleException("Missing value for {$field}.");
            }
        }

        $bracketMin = $this->decimal($row[$idx['bracket_min']], 2, 'bracket_min');
        $bracketMax = $this->decimal($row[$idx['bracket_max']], 2, 'bracket_max');
        $eeAmount = $this->decimal($row[$idx['ee_amount']], 4, 'ee_amount');
        $erAmount = $this->decimal($row[$idx['er_amount']], 4, 'er_amount');
        if ($agency === ContributionAgency::Sss
            && (! array_key_exists('ec_amount', $idx) || ! array_key_exists($idx['ec_amount'], $row))) {
            throw new BusinessRuleException('Missing value for ec_amount.');
        }
        $ecAmount = array_key_exists('ec_amount', $idx) && array_key_exists($idx['ec_amount'], $row)
            ? $this->decimal($row[$idx['ec_amount']], 4, 'ec_amount')
            : '0.0000';

        if ($agency !== ContributionAgency::Sss && bccomp($ecAmount, '0', 4) !== 0) {
            throw new BusinessRuleException('ec_amount is only valid for SSS schedules.');
        }

        if (bccomp($bracketMax, $bracketMin, 2) < 0) {
            throw new BusinessRuleException('bracket_max < bracket_min');
        }

        $effectiveDate = trim((string) $row[$idx['effective_date']]);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate)) {
            throw new BusinessRuleException('effective_date must use YYYY-MM-DD.');
        }
        try {
            $parsedDate = Carbon::createFromFormat('!Y-m-d', $effectiveDate);
        } catch (Throwable) {
            throw new BusinessRuleException('effective_date is not a valid calendar date.');
        }
        if (! $parsedDate || $parsedDate->format('Y-m-d') !== $effectiveDate) {
            throw new BusinessRuleException('effective_date is not a valid calendar date.');
        }

        return [
            'bracket_min' => $bracketMin,
            'bracket_max' => $bracketMax,
            'ee_amount' => $eeAmount,
            'er_amount' => $erAmount,
            'ec_amount' => $ecAmount,
            'effective_date' => $effectiveDate,
        ];
    }

    /**
     * @param  array<int, array{row:int, payload:array{bracket_min:string, bracket_max:string, ee_amount:string, er_amount:string, ec_amount:string, effective_date:string}}>  $rows
     * @param  array<int, array{row:int, message:string}>  $errors
     */
    private function validateSchedule(array $rows, ContributionAgency $agency, array &$errors): void
    {
        $seen = [];
        $byDate = [];

        foreach ($rows as $entry) {
            $payload = $entry['payload'];
            $identity = implode('|', [
                $agency->value,
                $payload['effective_date'],
                $payload['bracket_min'],
                $payload['bracket_max'],
            ]);
            if (isset($seen[$identity])) {
                $errors[] = [
                    'row' => $entry['row'],
                    'message' => 'Duplicate bracket/effective-date row; the file was not imported.',
                ];
            }
            $seen[$identity] = true;
            $byDate[$payload['effective_date']][] = $entry;
        }

        if (count($byDate) > 1) {
            $errors[] = [
                'row' => 1,
                'message' => 'A schedule version must contain exactly one effective_date. Split the file into one import per date.',
            ];
        }

        foreach ($byDate as $dateRows) {
            usort($dateRows, static function (array $left, array $right): int {
                return bccomp(
                    $left['payload']['bracket_min'],
                    $right['payload']['bracket_min'],
                    2,
                );
            });

            $previous = null;
            foreach ($dateRows as $entry) {
                if ($previous !== null && bccomp(
                    $entry['payload']['bracket_min'],
                    $previous['payload']['bracket_max'],
                    2,
                ) <= 0) {
                    $errors[] = [
                        'row' => $entry['row'],
                        'message' => sprintf(
                            'Bracket overlaps row %d for effective date %s.',
                            $previous['row'],
                            $entry['payload']['effective_date'],
                        ),
                    ];
                }
                $previous = $entry;
            }
        }
    }

    private function decimal(mixed $value, int $scale, string $field): string
    {
        $raw = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            throw new BusinessRuleException("{$field} must be a non-negative decimal.");
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        if (strlen($fraction) > $scale) {
            throw new BusinessRuleException("{$field} supports at most {$scale} decimal places.");
        }

        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        return $whole.'.'.str_pad($fraction, $scale, '0');
    }

    private function bustCache(ContributionAgency $agency): void
    {
        $key = $agency->value;
        CacheGeneration::rotateDefault(
            "gov_table:{$key}:ver",
            self::CACHE_VERSION_TTL,
            'payroll.government_tables.import.'.$key,
        );
        Cache::forget("gov_table:{$key}:active");
    }

    /** @return array{total:int, imported:int, updated:int, skipped:int, deactivated_prior:int, version_id:null, version_number:null, status:null, effective_date:null, source_file_sha256:null, idempotent:false, errors:array<int, array{row:int, message:string}>} */
    private function blankResult(array $error): array
    {
        return [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'deactivated_prior' => 0,
            'version_id' => null,
            'version_number' => null,
            'status' => null,
            'effective_date' => null,
            'source_file_sha256' => null,
            'idempotent' => false,
            'errors' => [$error],
        ];
    }
}
