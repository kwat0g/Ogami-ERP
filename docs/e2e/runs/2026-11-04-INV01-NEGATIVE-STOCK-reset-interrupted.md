# INV01-NEGATIVE-STOCK — E2E reset interrupted

**Date:** 2026-11-04 UTC  
**Classification:** Environment issue; no Playwright journey ran  
**Environment:** Dedicated `ogami-erp-e2e` Compose project only

`node scripts/e2e/runner.mjs reset` removed and recreated only the dedicated E2E volumes, started the isolated stack, and ran `migrate:fresh --force --seed`. Migrations reached completion and Laravel began `RolePermissionSeeder`, but the reset command exited 143 before the E2E account seeder or baseline completion.

Docker events show the E2E PostgreSQL container received signal 2 and the API received signal 3; both exited cleanly with `OOMKilled=false`. The SPA received signal 15. `queue-main` exited 137 with `OOMKilled=false` while the stack was stopping. These events do not identify what issued the stop. No Playwright container or browser journey ran, and no process record was created. The database is left incomplete and must be freshly seeded before any browser test.

This occurrence is tracked under [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated). It is not counted as a business result.
