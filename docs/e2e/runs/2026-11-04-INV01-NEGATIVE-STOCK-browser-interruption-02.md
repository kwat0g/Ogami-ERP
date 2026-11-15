# INV01-NEGATIVE-STOCK — Browser startup interrupted

**Date:** 2026-11-04 UTC  
**Classification:** Environment interruption; not a business-journey result  
**Environment:** Dedicated `ogami-erp-e2e` Compose project and freshly reseeded `ogami_e2e`

The guarded E2E stack reached healthy status, `migrate:fresh --seed` completed, the E2E seeder created its 37 isolated role accounts, and the container TypeScript preflight passed. The direct Playwright process printed `Running 2 tests using 1 worker`, then exited 137 before the selected actor auth setup reported a result. No business step or transactional action ran.

Docker events show the E2E services were sent shutdown signals first (SIGQUIT for the API/DB-adjacent services and SIGTERM for SPA/Redis/Mailpit); the one-off browser container then received SIGKILL about 34 seconds later. No Docker OOM event was recorded. The browser container was started with its configured 1,280 MiB cap. The runner interruption remains unexplained and is tracked under [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated).

The baseline had been freshly reseeded and no process transaction was created. The next attempt will reseed in one bounded orchestration call, then start the isolated stack and run the selected browser journey in a separate call to stay within the execution window.
