# HR05-LOAN-UNDER-THRESHOLD — attempt 08: Playwright build interrupted

**Result:** Environment interruption before browser setup. No business journey ran.

- **Started:** 2026-11-05 UTC; exact start time was not retained in the interactive output.
- **Runner:** Guarded `node scripts/e2e/runner.mjs test --grep HR05-LOAN-UNDER-THRESHOLD`; `E2E_AUTH_ACTORS` selected Employee, Department Head, HR Officer, both Finance Officers, and Vice President. The command exited 143.
- **Baseline:** `migrate:fresh --seed` completed in the dedicated `ogami_e2e` database and the E2E account seeder reported 38 isolated actors. The new reference account was present in the baseline seed code. No transaction was submitted.
- **Interruption point:** The guarded runner began building the isolated Playwright image; its final output was `#10 CANCELED`. It did not print TypeScript or Playwright setup output, and no Chromium/browser process started.
- **Service evidence:** The exact E2E Compose project stopped at the same time. API, PostgreSQL, Redis, Mailpit, Nginx and SPA exited 0/143; both queue workers exited 137; Reverb and scheduler exited 131. `docker inspect` reported `OOMKilled=false` for the API, PostgreSQL, SPA and both queue workers. Host memory then showed 9 GiB available. The guarded readiness command refused to proceed because the E2E stack was no longer fully running.
- **Classification:** ENV-E2E-002. The stop-signal source is unknown; no product or test result is inferred.
- **Artifacts:** No Playwright trace, screenshot, or journey context exists because the browser did not start. Evidence is the captured runner output, exact-project Compose status, and read-only container inspection.

The interrupted baseline was discarded. A later run must start from a new guarded baseline and will be recorded separately.
