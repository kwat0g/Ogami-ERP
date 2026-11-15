# HR05-LOAN-UNDER-THRESHOLD — attempt 10: baseline reset interrupted

**Result:** Environment interruption during baseline seeding. No browser or business journey ran.

- **Started:** 2026-11-05 00:29:46 UTC for guarded stack startup; the selected journey command began after the stack was healthy and attempted a fresh `test-split` baseline.
- **Runner:** `E2E_AUTH_ACTORS='employee,department_head,hr_officer,finance_officer,finance_officer__2,vice_president' node scripts/e2e/runner.mjs test-split --grep HR05-LOAN-UNDER-THRESHOLD`; exit 143.
- **Baseline:** `migrate:fresh --seed` progressed through `WarehouseSeeder`, `CustomerSeeder`, `ProductSeeder`, `PriceAgreementSeeder`, `BomSeeder`, `MachineSeeder`, `MoldSeeder`, and `MoldCompatibilitySeeder`. Captured output ended while `ProductRoutingSeeder` was running. `E2EAccountSeeder` did not complete.
- **Interruption point:** No TypeScript check, selected actor authentication, Playwright startup, or Chromium output occurred. No loan or other journey transaction was created.
- **Service evidence:** Docker events show signal 3 (`SIGQUIT`) sent to the E2E Nginx, queue workers, scheduler, and Reverb, followed by service removal; the API also received signal 3. Exact-project Compose inspection afterward found no containers. The Docker event stream does not identify the initiating process. Host `MemAvailable` was approximately 10 GiB and swap was not configured; OOM cause is not established.
- **Classification:** ENV-E2E-002. Shutdown source is unknown; no product or test result is inferred. This interruption follows attempts 08–09; the same journey was not rerun again in this batch.
- **Artifacts:** Captured runner output and Docker events only. No Playwright trace, screenshot, or journey context exists because the browser did not start.

The partial E2E baseline was discarded. Any later run must begin with a new guarded baseline and be recorded separately.
