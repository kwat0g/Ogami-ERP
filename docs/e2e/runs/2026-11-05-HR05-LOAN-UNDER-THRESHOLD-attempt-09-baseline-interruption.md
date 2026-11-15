# HR05-LOAN-UNDER-THRESHOLD — attempt 09: baseline reset interrupted

**Result:** Environment interruption during baseline seeding. No browser or business journey ran.

- **Started:** 2026-11-05 00:05:16 UTC, from the captured Docker event for the `migrate:fresh --force --seed` command.
- **Runner:** Guarded `node scripts/e2e/runner.mjs test --grep HR05-LOAN-UNDER-THRESHOLD`; exit 143. Six actors were selected through `E2E_AUTH_ACTORS`.
- **Baseline:** Migration and reference seeding began. Captured output reached `WarehouseSeeder`, then the services received stop signals before `E2EAccountSeeder` reported completion. The partial baseline was discarded.
- **Interruption point:** No Playwright image-build, TypeScript, auth setup, or Chromium output was emitted.
- **Service evidence:** The exact E2E Compose services stopped. API and PostgreSQL exited 0; both queue workers exited 137; SPA exited 143; Reverb and scheduler exited 131. Read-only `docker inspect` reported `OOMKilled=false` for all inspected services. A Docker event recorded signal 3 to the API container during the seed, but does not identify the initiating process.
- **Classification:** ENV-E2E-002. Shutdown source is unknown; no product or test result is inferred.
- **Artifacts:** No Playwright trace, screenshot, or journey context exists because the browser did not start. Evidence is the captured runner output and exact-project container status/inspection.

The interrupted baseline was discarded. A later run must start from a new guarded baseline and will be recorded separately.
