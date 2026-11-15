# HR05-PARTIAL-PAYMENT — attempt 01: baseline reset interrupted

**Result:** Environment interruption during baseline seeding. The Playwright journey did not start.

- **Started:** Guarded E2E stack startup at 2026-11-05 00:41:29 UTC. `test-split` then began a fresh `migrate:fresh --seed` baseline for `HR05-PARTIAL-PAYMENT`.
- **Runner:** `E2E_AUTH_ACTORS='employee,department_head,hr_officer,finance_officer,vice_president' node scripts/e2e/runner.mjs test-split --grep HR05-PARTIAL-PAYMENT`; exit 143.
- **Baseline:** Migrations completed and reference seeding reached `VehicleSeeder`; captured output ended at the following `INFO Seeding database` boundary. `E2EAccountSeeder` did not report completion.
- **Interruption point:** No E2E TypeScript container check, actor UI login, Playwright startup, or Chromium journey output occurred. No loan or repayment record was created.
- **Service evidence:** Docker events show signal 3 (`SIGQUIT`) sent to the E2E scheduler, Nginx, queue workers, Reverb, and API at 2026-11-05 00:43:29 UTC, followed by service stop/removal. The initiating process is not identified. Exact-project Compose inspection afterward found no containers. Host `MemAvailable` was approximately 10 GiB; swap was not configured, and OOM is not established.
- **Classification:** ENV-E2E-002. The run does not confirm or refute the source-discovered manual repayment UI gap `GAP-HR-05-03`; no browser step reached the loan page.
- **Artifacts:** Captured runner output and Docker events only. No Playwright trace, screenshot, or journey context exists because the browser did not start.

The partial E2E baseline was discarded. Do not count this as a process outcome. A later run must begin from a new guarded baseline and be recorded separately.
