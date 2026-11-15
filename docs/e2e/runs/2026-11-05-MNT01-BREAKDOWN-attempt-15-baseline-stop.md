# MNT01-BREAKDOWN — attempt 15: baseline stopped after PHP-test overlap was detected

**Result:** Interrupted during E2E baseline migration. No Playwright journey ran and no process coverage is credited.

- **Environment:** The guarded E2E stack started. The process TypeScript check and Playwright did not run. A fresh `migrate:fresh --force --seed` had started against the isolated E2E database when an external filtered PHP feature test appeared.
- **Interruption:** The PHP test was `test_repeated_submit_returns_a_state_conflict` on the development stack. It started after the runner's pre-migration check. I stopped the dedicated E2E API container, waited for the guarded runner to exit, then stopped the remaining E2E stack. The PHP test was left untouched. No E2E actor seeding or browser activity followed.
- **Rule impact:** The two separate test systems briefly overlapped during baseline migration. The process-boundary guard cannot prevent a PHP test from starting while a synchronous baseline command is already running. This is recorded under [ENV-E2E-008](../FINDINGS.md#env-e2e-008-a-php-test-started-during-a-guarded-e2e-run).
- **Results and data:** No current Playwright JSON, journey context, or trace was produced. Any partially reset E2E database state is disposable and must be replaced by another guarded fresh baseline before resuming. E2E containers are stopped; their isolated volumes are preserved.

**Classification:** Environment / run-coordination interruption. No application code changed.
