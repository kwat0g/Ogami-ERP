# HR05-LOAN-UNDER-THRESHOLD — attempt 04: baseline seeding interrupted

**Result:** The guarded split runner began a fresh migration and reference-data seed, then exited 143 before the E2E account seeder reported completion. The selected Playwright run did not start, so this is not a process result.

- **Environment:** Dedicated E2E Compose project and `ogami_e2e` only.
- **Observed:** The E2E containers stopped during the interrupted command. Host memory inspection showed 10 GiB available and no matching OOM-killer record; Docker later reported normal/stop-signal exit codes, including SPA 143 and Reverb/scheduler 131. The initiating process remains unknown.
- **Disposition:** Partial baseline discarded. The next guarded `runner test` rebuilt and seeded a clean baseline before running the journey.
- **Classification:** Environment interruption under ENV-E2E-002.
