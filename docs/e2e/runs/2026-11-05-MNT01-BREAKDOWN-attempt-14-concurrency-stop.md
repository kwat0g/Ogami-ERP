# MNT01-BREAKDOWN — attempt 14: stopped after PHP-test overlap was detected

**Result:** Interrupted by the E2E operator to enforce the no-overlap rule. This attempt is not a journey pass and does not count toward variant coverage.

- **Environment:** The guarded E2E environment check and Compose validation passed. The isolated stack started, `migrate:fresh --seed` and the E2E account seeder completed, and the process TypeScript check passed. The runner started one headless Chromium test with one worker.
- **Interruption:** A separate development-stack PHP feature test (`php artisan test --filter=test_detail_projects_main_return_actions_for_the_current_viewer`) appeared after the E2E preflight. I detected it while the Playwright test was active, stopped only the dedicated E2E Playwright container, and then stopped the dedicated E2E stack through the guarded runner. I left the PHP test untouched. A brief overlap occurred, which violated the resource rule; this is recorded under [ENV-E2E-008](../FINDINGS.md#env-e2e-008-a-php-test-started-during-a-guarded-e2e-run).
- **Journey evidence:** Playwright left sanitized trace recordings, but did not flush a current JSON result or `journey-context.json`. The available `process-results.json` predates this attempt and is stale. The last completed business step and any partial UI writes therefore cannot be established. No status or business side effect is claimed.
- **Data handling:** The next attempt must run the guarded fresh baseline before continuing, so any partial transaction from this run is discarded through the E2E reset path. The E2E containers are stopped and their isolated volumes are preserved.
- **Trace evidence:** [Sanitized Playwright recordings](assets/mnt01-breakdown-attempt-14/trace-sanitized.zip). The artifact check returned clean; the runner log contained no encrypted-cookie markers, bearer values, or cookie headers.

**Classification:** Environment / run-coordination interruption. No application code changed.
